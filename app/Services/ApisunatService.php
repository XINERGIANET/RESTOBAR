<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchElectronicBillingConfig;
use App\Models\BranchParameter;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\TaxRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApisunatService
{
    /**
     * Normaliza un número local o comprobante a su correlativo entero para SUNAT/Apisunat (máx. 8 dígitos),
     * quitando prefijo de serie (ej: "B001-00000057" -> 57) y el "1" inicial heredado que algunos números locales arrastran (ej. "100000381" -> 381).
     */
    public function normalizeCorrelative(mixed $number): int
    {
        $str = (string) $number;
        if (str_contains($str, '-')) {
            $str = substr($str, strrpos($str, '-') + 1);
        }
        $raw = preg_replace('/\D+/', '', $str) ?: '';
        if (strlen($raw) >= 9 && str_starts_with($raw, '1')) {
            $raw = substr($raw, 1);
        }

        return (int) $raw;
    }

    public function isEligibleDocument(Movement $sale): bool
    {
        $docName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');

        return str_contains($docName, 'boleta') || str_contains($docName, 'factura');
    }

    public function resolveConfigForBranch(?Branch $branch): ?BranchElectronicBillingConfig
    {
        if (! $branch) {
            return null;
        }

        $branch->loadMissing('electronicBillingConfig');
        $config = $branch->electronicBillingConfig;

        if (! $config) {
            return null;
        }

        return $config;
    }

    public function isConfiguredForBranch(?Branch $branch): bool
    {
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            return false;
        }

        return $this->resolveApiUrl($config) !== ''
            && trim((string) $config->persona_id) !== ''
            && trim((string) $config->persona_token) !== '';
    }

    /**
     * Valida y ajusta la fecha de emisión a los 2 días máximos permitidos por SUNAT (RS 000003-2023/SUNAT)
     * conservando la fecha original histórica en la base de datos local.
     */
    public function resolveSunatIssueDate(Movement $sale): array
    {
        $saleDate = $sale->moved_at
            ? \Illuminate\Support\Carbon::parse($sale->moved_at)
            : ($sale->created_at ? \Illuminate\Support\Carbon::parse($sale->created_at) : now());

        $minAllowedDate = now()->subDays(2)->startOfDay();
        $maxAllowedDate = now()->endOfDay();

        if ($saleDate->lt($minAllowedDate)) {
            // Si la fecha es de hace más de 2 días, enviamos a SUNAT con el límite máximo permitido (hace 2 días)
            $issueDate = $minAllowedDate->format('Y-m-d');
            $issueTime = now()->format('H:i:s');
            $adjusted = true;
        } elseif ($saleDate->gt($maxAllowedDate)) {
            // Si la fecha es futura, usamos el día de hoy
            $issueDate = now()->format('Y-m-d');
            $issueTime = now()->format('H:i:s');
            $adjusted = true;
        } else {
            $issueDate = $saleDate->format('Y-m-d');
            $issueTime = $saleDate->format('H:i:s');
            $adjusted = false;
        }

        return [
            'issue_date' => $issueDate,
            'issue_time' => $issueTime,
            'adjusted' => $adjusted,
        ];
    }

    public function emitSale(Movement $sale): array
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.taxRate',
            'orderMovement.details.taxRate',
        ]);

        if (! $this->isEligibleDocument($sale)) {
            return [
                'status' => 'SKIPPED',
                'message' => 'El tipo de documento no requiere envío electrónico.',
            ];
        }

        if ($sale->electronic_invoice_external_id) {
            return [
                'status' => 'SENT',
                'message' => 'El comprobante electrónico ya fue emitido.',
                'data' => $this->movementElectronicData($sale),
            ];
        }

        $branch = $sale->branch;
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene configurada la facturación electrónica.');
        }

        $catalog = $this->resolveDocumentCatalog($sale, $config);
        $customerDocument = $this->resolveCustomerDocument($sale, $catalog['type']);
        $customerDocType = $this->resolveCustomerDocumentType($customerDocument, $catalog['type']);
        $totals = $this->resolveMovementTotals($sale);
        $apiUrl = $this->resolveApiUrl($config);

        $correlativeResp = Http::timeout(20)->post($apiUrl.'/personas/lastDocument', [
            'personaId' => (string) $config->persona_id,
            'personaToken' => (string) $config->persona_token,
            'type' => $catalog['type'],
            'serie' => $catalog['serie'],
        ]);

        // La numeracion remota combinada con el máximo emitido localmente es la fuente de verdad.
        $suggested = $this->normalizeCorrelative(data_get($correlativeResp->json(), 'suggestedNumber', 0));
        $last = $this->normalizeCorrelative(data_get($correlativeResp->json(), 'lastNumber', 0));
        $targetNum = $suggested > 0 ? $suggested : ($last > 0 ? $last + 1 : 1);

        $maxEmittedLocal = 0;
        $emittedLocalSales = Movement::where('branch_id', $branch->id)
            ->where('movement_type_id', 2)
            ->where('document_type_id', $sale->document_type_id)
            ->where('electronic_invoice_status', 'SENT')
            ->whereNotNull('electronic_invoice_external_id')
            ->where('electronic_invoice_external_id', '!=', '')
            ->where('electronic_invoice_external_id', '!=', '0')
            ->get();

        foreach ($emittedLocalSales as $emitted) {
            $num = $this->normalizeCorrelative($emitted->number);
            if ($num > $maxEmittedLocal && $num < 100000) {
                $maxEmittedLocal = $num;
            }
        }

        $targetNum = max($targetNum, $maxEmittedLocal + 1);

        $attempts = 0;
        $sendResp = null;
        $number = '';
        $fileName = '';

        // 5. Bucle de reintento automático por numeración repetida en APISUNAT (hasta 25 intentos)
        while ($attempts < 25) {
            $attempts++;
            $number = str_pad((string) $targetNum, 8, '0', STR_PAD_LEFT);
            $fileName = trim((string) ($branch?->ruc ?? '0')).'-'.$catalog['type'].'-'.$catalog['serie'].'-'.$number;
            $documentBody = $this->buildDocumentBody($sale, $catalog, $customerDocument, $customerDocType, $totals, $number);
            $this->validateDocumentBodyForSunat($documentBody);

            $sendResp = Http::timeout(35)->post($apiUrl.'/personas/v1/sendBill', [
                'personaId' => (string) $config->persona_id,
                'personaToken' => (string) $config->persona_token,
                'fileName' => $fileName,
                'documentBody' => $documentBody,
            ]);

            if ($sendResp->successful()) {
                break;
            }

            $rawBody = (string) $sendResp->body();
            $errorObj = $sendResp->object();
            $errorJson = $sendResp->json();

            $errorMessage = data_get($errorObj, 'error.message')
                ?: data_get($errorObj, 'message')
                ?: data_get($errorObj, 'description')
                ?: data_get($errorJson, 'error.message')
                ?: data_get($errorJson, 'message')
                ?: data_get($errorJson, 'description')
                ?: $rawBody;

            $lowerErr = mb_strtolower($errorMessage, 'UTF-8');

            if (
                str_contains($lowerErr, 'repetida') ||
                str_contains($lowerErr, 'ya existe') ||
                str_contains($lowerErr, 'registrado') ||
                str_contains($lowerErr, 'duplicate') ||
                str_contains($lowerErr, 'exist')
            ) {
                $targetNum = $this->fetchLastDocumentNumber($branch, $catalog['type']);
                if ($targetNum <= 0 || $targetNum > 99999999) {
                    throw new \RuntimeException('No se pudo recuperar el siguiente correlativo luego de detectar un duplicado.');
                }
                continue;
            }

            throw new \RuntimeException('Error enviando comprobante a Apisunat: '.$errorMessage);
        }

        if (! $sendResp || $sendResp->failed()) {
            $rawBody = (string) ($sendResp ? $sendResp->body() : 'Sin respuesta de APISUNAT.');
            $errorObj = $sendResp?->object();
            $errorJson = $sendResp?->json();

            $errorMessage = data_get($errorObj, 'error.message')
                ?: data_get($errorObj, 'message')
                ?: data_get($errorObj, 'description')
                ?: data_get($errorJson, 'error.message')
                ?: data_get($errorJson, 'message')
                ?: data_get($errorJson, 'description')
                ?: $rawBody;

            throw new \RuntimeException('Error enviando comprobante a Apisunat: '.$errorMessage);
        }

        $result = $sendResp->object();
        $documentId = trim((string) data_get($result, 'documentId', ''));
        if ($documentId === '') {
            throw new \RuntimeException('Apisunat no devolvió documentId.');
        }

        $extraDocumentData = [];
        $urls = [];
        try {
            $extraDocumentData = $this->getDocumentById($documentId, $branch);
            $urls = $this->extractDocumentUrls($extraDocumentData);
        } catch (\Throwable $e) {
            // Se continúa si no se pudo consultar el detalle extra de forma inmediata
        }

        // Sincronizar el número emitido en APISUNAT con la venta local en base de datos
        $sale->number = $number;
        $sale->electronic_invoice_provider = 'apisunat';
        $sale->electronic_invoice_external_id = $documentId;
        $sale->electronic_invoice_series = $catalog['serie'];
        $sale->electronic_invoice_number = $catalog['serie'].'-'.$number;
        $sale->electronic_invoice_file_name = $fileName.'.pdf';
        $sale->electronic_invoice_pdf_ticket_url = $apiUrl.'/documents/'.$documentId.'/getPDF/ticket80mm/'.$fileName.'.pdf';
        $sale->electronic_invoice_pdf_a4_url = $apiUrl.'/documents/'.$documentId.'/getPDF/A4/'.$fileName.'.pdf';
        $sale->electronic_invoice_xml_url = $urls['xml_url'] ?? null;
        $sale->electronic_invoice_cdr_url = $urls['cdr_url'] ?? null;
        $sale->electronic_invoice_status = 'SENT';
        $sale->electronic_invoice_response = (array) $result;
        $sale->save();

        if ($sale->salesMovement) {
            $sale->salesMovement->series = $catalog['serie'];
            $sale->salesMovement->save();
        }

        return [
            'status' => 'SENT',
            'message' => 'Comprobante enviado correctamente a Apisunat.',
            'data' => [
                'provider' => 'apisunat',
                'external_id' => $documentId,
                'series' => $catalog['serie'],
                'correlative' => $number,
                'full_number' => $catalog['serie'].'-'.$number,
                'file_name' => $fileName.'.pdf',
                'pdf_ticket_80mm' => $apiUrl.'/documents/'.$documentId.'/getPDF/ticket80mm/'.$fileName.'.pdf',
                'pdf_a4' => $apiUrl.'/documents/'.$documentId.'/getPDF/A4/'.$fileName.'.pdf',
                'xml_url' => $urls['xml_url'] ?? null,
                'cdr_url' => $urls['cdr_url'] ?? null,
                'response' => [
                    'send' => $sendResp->json(),
                    'document' => $extraDocumentData,
                ],
            ],
        ];
    }

    public function fetchLastDocumentNumber(?Branch $branch, string $type = '03'): int
    {
        $config = $this->resolveConfigForBranch($branch);
        if (! $config || ! $config->enabled) {
            return 0;
        }

        $apiUrl = $this->resolveApiUrl($config);
        $serie = $type === '01'
            ? trim((string) ($config->series_factura ?: config('apisunat.series.factura', 'F001')))
            : trim((string) ($config->series_boleta ?: config('apisunat.series.boleta', 'B001')));

        $res = Http::timeout(10)->post($apiUrl.'/personas/lastDocument', [
            'personaId' => (string) $config->persona_id,
            'personaToken' => (string) $config->persona_token,
            'type' => $type,
            'serie' => $serie,
        ]);

        if ($res->successful()) {
            $obj = $res->object();
            $sug = (int) data_get($obj, 'suggestedNumber', 0);
            $last = (int) data_get($obj, 'lastNumber', 0);

            return $sug > 0 ? $sug : ($last > 0 ? $last + 1 : 1);
        }

        return 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchAllDocuments(?Branch $branch, string $type, string $series): array
    {
        $config = $this->resolveConfigForBranch($branch);
        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene APISUNAT configurado.');
        }

        $documents = [];
        $limit = 100;
        for ($skip = 0; $skip < 10000; $skip += $limit) {
            $response = Http::timeout(30)->get($this->resolveApiUrl($config).'/documents/getAll', [
                'personaId' => (string) $config->persona_id,
                'personaToken' => (string) $config->persona_token,
                'type' => $type,
                'serie' => $series,
                'limit' => $limit,
                'skip' => $skip,
                'order' => 'ASC',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('No se pudo descargar el listado de comprobantes de APISUNAT.');
            }

            $page = $this->documentListFromResponse($response->json());
            $documents = array_merge($documents, $page);
            if (count($page) < $limit) {
                break;
            }
        }

        return $documents;
    }

    /**
     * Enlaza el inventario real de APISUNAT y luego resecuencia solamente las
     * ventas pendientes. Ante cualquier ambiguedad, ese tipo no se resecuencia.
     *
     * @return array{success:bool,message:string}
     */
    public function reconcileBranchDocuments(Branch $branch): array
    {
        $config = $this->resolveConfigForBranch($branch);
        if (! $config || ! $this->isConfiguredForBranch($branch)) {
            throw new \RuntimeException('La sucursal no tiene APISUNAT configurado.');
        }

        $documentTypes = DocumentType::where(function ($query) {
            $query->where('name', 'like', '%boleta%')->orWhere('name', 'like', '%factura%');
        })->get();
        $summary = [];
        $problems = [];

        foreach ($documentTypes as $documentType) {
            $name = mb_strtolower((string) $documentType->name, 'UTF-8');
            $type = str_contains($name, 'factura') ? '01' : '03';
            $series = trim((string) ($type === '01' ? $config->series_factura : $config->series_boleta));

            $typeProblems = [];
            $remoteDocuments = [];
            try {
                $remoteDocuments = $this->fetchAllDocuments($branch, $type, $series);
            } catch (\Throwable $e) {
                $typeProblems[] = "Aviso al consultar APISUNAT ({$series}): " . $e->getMessage();
            }

            $next = 0;
            try {
                $next = $this->fetchLastDocumentNumber($branch, $type);
            } catch (\Throwable $e) {
                // Se usará el número local más alto como base
            }

            $movements = Movement::with('salesMovement')
                ->where('branch_id', $branch->id)
                ->where('movement_type_id', 2)
                ->where('document_type_id', $documentType->id)
                ->orderBy('moved_at')->orderBy('id')->get();
            $linked = 0;
            $seenRemote = [];

            DB::transaction(function () use ($remoteDocuments, $movements, $branch, $config, $type, $series, &$linked, &$seenRemote, &$typeProblems) {
                foreach ($remoteDocuments as $document) {
                    $remote = $this->remoteDocumentMetadata($document);
                    if ($remote['status'] === 'EXCEPCION') {
                        continue;
                    }
                    if (($remote['type'] !== '' && $remote['type'] !== $type)
                        || ($remote['series'] !== '' && strcasecmp($remote['series'], $series) !== 0)) {
                        continue;
                    }
                    if ($remote['number'] <= 0 || $remote['external_id'] === '') {
                        $typeProblems[] = 'documento remoto sin ID o correlativo';
                        continue;
                    }
                    if (isset($seenRemote[$remote['number']])) {
                        $typeProblems[] = "{$series}-{$remote['number_padded']} esta duplicado en APISUNAT";
                        continue;
                    }
                    $seenRemote[$remote['number']] = true;

                    $fullNumber = $series.'-'.$remote['number_padded'];
                    $candidates = $movements->filter(
                        fn (Movement $movement) => $movement->electronic_invoice_external_id === $remote['external_id']
                    );
                    if ($candidates->isEmpty()) {
                        $candidates = $movements->filter(
                            fn (Movement $movement) => $movement->electronic_invoice_number === $fullNumber
                        );
                    }
                    if ($candidates->isEmpty()) {
                        $candidates = $movements->filter(
                            fn (Movement $movement) => (empty($movement->electronic_invoice_external_id) || $movement->electronic_invoice_external_id === $remote['external_id'])
                                && $movement->number === $remote['number_padded']
                        );
                    }
                    if ($candidates->isEmpty()) {
                        $candidates = $movements->filter(
                            fn (Movement $movement) => (empty($movement->electronic_invoice_external_id) || $movement->electronic_invoice_external_id === $remote['external_id'])
                                && $this->normalizeCorrelative($movement->number) === $remote['number']
                        );
                    }

                    $unlinkedCandidates = $candidates->filter(
                        fn (Movement $movement) => empty($movement->electronic_invoice_external_id)
                            || $movement->electronic_invoice_external_id === $remote['external_id']
                    );

                    if ($unlinkedCandidates->isEmpty()) {
                        $typeProblems[] = "{$fullNumber} no tiene venta local candidata disponible";
                        continue;
                    }

                    /** @var Movement $movement */
                    $movement = $unlinkedCandidates->first();
                    if ($movement->electronic_invoice_external_id
                        && $movement->electronic_invoice_external_id !== $remote['external_id']) {
                        $typeProblems[] = "venta {$movement->id} ya enlazada a otro documento";
                        continue;
                    }

                    $fileName = preg_replace('/\.pdf$/i', '', $remote['file_name']) ?: trim((string) $branch->ruc)."-{$type}-{$fullNumber}";
                    $apiUrl = rtrim((string) ($config->api_url ?: config('apisunat.url')), '/');
                    $movement->forceFill([
                        'number' => $remote['number_padded'],
                        'electronic_invoice_provider' => 'apisunat',
                        'electronic_invoice_status' => 'SENT',
                        'electronic_invoice_external_id' => $remote['external_id'],
                        'electronic_invoice_series' => $series,
                        'electronic_invoice_number' => $fullNumber,
                        'electronic_invoice_file_name' => $fileName.'.pdf',
                        'electronic_invoice_pdf_ticket_url' => $apiUrl.'/documents/'.$remote['external_id'].'/getPDF/ticket80mm/'.$fileName.'.pdf',
                        'electronic_invoice_pdf_a4_url' => $apiUrl.'/documents/'.$remote['external_id'].'/getPDF/A4/'.$fileName.'.pdf',
                        'electronic_invoice_xml_url' => $remote['xml_url'],
                        'electronic_invoice_cdr_url' => $remote['cdr_url'],
                        'electronic_invoice_response' => $remote['payload'],
                    ])->save();
                    $movement->salesMovement?->update(['series' => preg_replace('/^[A-Z]+/i', '', $series)]);
                    $linked++;
                }
            });

            $missingRemote = [];
            for ($number = 1; $number < $next; $number++) {
                if (! isset($seenRemote[$number])) {
                    $missingRemote[] = str_pad((string) $number, 8, '0', STR_PAD_LEFT);
                }
            }
            if ($missingRemote !== []) {
                $typeProblems[] = "{$series} tiene huecos remotos: ".implode(', ', array_slice($missingRemote, 0, 10));
            }

            if ($typeProblems !== []) {
                $problems = array_merge($problems, $typeProblems);
            }

            $maxLinkedNumber = 0;
            $updatedMovements = Movement::where('branch_id', $branch->id)
                ->where('movement_type_id', 2)
                ->where('document_type_id', $documentType->id)
                ->get();

            $emittedIds = [];
            foreach ($updatedMovements as $m) {
                $extId = trim((string) $m->electronic_invoice_external_id);
                $status = strtoupper(trim((string) $m->electronic_invoice_status));
                if ($extId !== '' && $extId !== '0' && $status === 'SENT') {
                    $emittedIds[] = $m->id;
                    $num = $this->normalizeCorrelative($m->number);
                    if ($num > $maxLinkedNumber && $num < 100000) {
                        $maxLinkedNumber = $num;
                    }
                }
            }

            $startSequence = max($next, $maxLinkedNumber + 1);

            $pending = Movement::with('salesMovement')
                ->where('branch_id', $branch->id)
                ->where('movement_type_id', 2)
                ->where('document_type_id', $documentType->id)
                ->whereNotIn('id', $emittedIds ?: [0])
                ->orderBy('moved_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            DB::transaction(function () use ($pending, $startSequence, $series) {
                $sequence = $startSequence;
                foreach ($pending as $movement) {
                    $movement->forceFill([
                        'number' => str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
                        'electronic_invoice_series' => null,
                        'electronic_invoice_number' => null,
                    ])->save();
                    $movement->salesMovement?->update(['series' => preg_replace('/^[A-Z]+/i', '', $series)]);
                    $sequence++;
                }
            });
            $summary[] = "{$series}: {$linked} enlazados; {$pending->count()} pendientes reordenados desde ".str_pad((string) $startSequence, 8, '0', STR_PAD_LEFT);
        }

        return [
            'success' => $problems === [],
            'message' => 'Conciliacion APISUNAT: '.implode(' | ', $summary)
                .($problems ? '. Revisar: '.implode('; ', array_slice(array_unique($problems), 0, 10)) : ''),
        ];
    }

    /** @return array<string, mixed> */
    public function remoteDocumentMetadata(array $document): array
    {
        $fileName = trim((string) (data_get($document, 'fileName') ?: data_get($document, 'file_name', '')));
        $type = trim((string) data_get($document, 'type', ''));
        $series = strtoupper(trim((string) (data_get($document, 'serie') ?: data_get($document, 'series', ''))));
        $number = $this->normalizeCorrelative(data_get($document, 'number', ''));

        if (preg_match('/-(\d{2})-([A-Z0-9]{4})-(\d{1,8})(?:\.\w+)?$/i', $fileName, $matches) === 1) {
            $type = $type !== '' ? $type : $matches[1];
            $series = $series !== '' ? $series : strtoupper($matches[2]);
            $number = $number > 0 ? $number : (int) $matches[3];
        }

        return [
            'external_id' => trim((string) (data_get($document, 'documentId') ?: data_get($document, '_id') ?: data_get($document, 'id', ''))),
            'type' => $type,
            'series' => $series,
            'number' => $number,
            'number_padded' => $number > 0 ? str_pad((string) $number, 8, '0', STR_PAD_LEFT) : '',
            'file_name' => $fileName,
            'status' => strtoupper(trim((string) data_get($document, 'status', ''))),
            'xml_url' => $this->findUrlByKeyword($document, ['xml']),
            'cdr_url' => $this->findUrlByKeyword($document, ['cdr']),
            'payload' => $document,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function documentListFromResponse(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        foreach (['documents', 'data', 'payload', 'data.documents', 'payload.documents'] as $key) {
            $candidate = data_get($payload, $key);
            if (is_array($candidate) && array_is_list($candidate)) {
                return array_values(array_filter($candidate, 'is_array'));
            }
        }

        return [];
    }

    public function consultDocument(?Branch $branch, string $document): array
    {
        $document = trim($document);
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene configurada la consulta documental.');
        }

        $apiUrl = $this->resolveApiUrl($config);
        if (strlen($document) === 8) {
            $url = $apiUrl.'/personas/'.trim((string) $config->persona_id).'/getDNI?dni='.$document.'&personaToken='.rawurlencode((string) $config->persona_token);
        } elseif (strlen($document) === 11) {
            $url = $apiUrl.'/personas/'.trim((string) $config->persona_id).'/getRUC?ruc='.$document.'&personaToken='.rawurlencode((string) $config->persona_token);
        } else {
            throw new \RuntimeException('Documento inválido.');
        }

        $response = Http::timeout(20)->get($url);
        if ($response->failed()) {
            throw new \RuntimeException('No se pudo consultar el documento.');
        }

        return (array) ($response->json('data') ?? []);
    }

    public function getDocumentById(string $documentId, ?Branch $branch = null): array
    {
        $apiUrl = $this->resolveApiUrl($this->resolveConfigForBranch($branch));
        $response = Http::timeout(20)->get($apiUrl.'/documents/'.$documentId.'/getById');

        if ($response->failed()) {
            throw new \RuntimeException('No se pudo consultar el comprobante electrónico.');
        }

        return $response->json() ?? [];
    }

    public function extractDocumentUrls(array $payload): array
    {
        return [
            'xml_url' => $this->findUrlByKeyword($payload, ['xml']),
            'cdr_url' => $this->findUrlByKeyword($payload, ['cdr']),
            'pdf_a4_url' => $this->findUrlByKeyword($payload, ['pdf', 'a4']),
            'pdf_ticket_url' => $this->findUrlByKeyword($payload, ['pdf', 'ticket']),
        ];
    }

    private function movementElectronicData(Movement $sale): array
    {
        return [
            'provider' => $sale->electronic_invoice_provider,
            'external_id' => $sale->electronic_invoice_external_id,
            'series' => $sale->electronic_invoice_series,
            'full_number' => $sale->electronic_invoice_number,
            'file_name' => $sale->electronic_invoice_file_name,
            'pdf_ticket_80mm' => $sale->electronic_invoice_pdf_ticket_url,
            'pdf_a4' => $sale->electronic_invoice_pdf_a4_url,
            'xml_url' => $sale->electronic_invoice_xml_url,
            'cdr_url' => $sale->electronic_invoice_cdr_url,
            'response' => $sale->electronic_invoice_response,
        ];
    }

    private function resolveApiUrl(?BranchElectronicBillingConfig $config): string
    {
        $url = trim((string) ($config?->api_url ?: config('apisunat.url')));

        return rtrim($url, '/');
    }

    private function resolveDocumentCatalog(Movement $sale, BranchElectronicBillingConfig $config): array
    {
        $docName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');

        if (str_contains($docName, 'factura')) {
            return [
                'type' => '01',
                'serie' => trim((string) ($config->series_factura ?: config('apisunat.series.factura', 'F001'))),
            ];
        }

        if (str_contains($docName, 'boleta')) {
            return [
                'type' => '03',
                'serie' => trim((string) ($config->series_boleta ?: config('apisunat.series.boleta', 'B001'))),
            ];
        }

        throw new \RuntimeException('Solo boleta y factura pueden enviarse a Apisunat.');
    }

    private function resolveCustomerDocument(Movement $sale, string $documentTypeCode): string
    {
        $document = preg_replace('/\D+/', '', (string) ($sale->person?->document_number ?? '')) ?: '';

        if ($documentTypeCode === '01') {
            if (strlen($document) !== 11) {
                throw new \RuntimeException('La factura requiere un cliente con RUC válido.');
            }

            return $document;
        }

        return $document !== '' ? $document : '0';
    }

    private function resolveCustomerDocumentType(string $document, string $documentTypeCode): string
    {
        if ($documentTypeCode === '01') {
            return '6';
        }

        if (strlen($document) === 11) {
            return '6';
        }
        if (strlen($document) === 8) {
            return '1';
        }

        return '0';
    }

    private function resolveMovementTotals(Movement $sale): array
    {
        $subtotal = round((float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0), 2);
        $tax = round((float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0), 2);
        $total = round((float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0), 2);

        return compact('subtotal', 'tax', 'total');
    }

    private function buildDocumentBody(Movement $sale, array $catalog, string $customerDocument, string $customerDocType, array $totals, string $number): array
    {
        $branch = $sale->branch;
        $customerName = trim((string) ($sale->person_name ?: 'CLIENTES VARIOS'));
        $details = $this->resolveDetailsForSale($sale);
        $defaultTaxPercent = $this->resolveDefaultTaxPercentForBranch($branch);

        $dates = $this->resolveSunatIssueDate($sale);

        $supplierAddress = trim((string) ($branch?->address ?? ''));
        if ($supplierAddress === '' || $supplierAddress === '-') {
            $supplierAddress = 'AV. PRINCIPAL S/N - CHICLAYO';
        }

        $documentBody = [
            'cbc:UBLVersionID' => ['_text' => '2.1'],
            'cbc:CustomizationID' => ['_text' => '2.0'],
            'cbc:ID' => ['_text' => $catalog['serie'].'-'.$number],
            'cbc:IssueDate' => ['_text' => $dates['issue_date']],
            'cbc:IssueTime' => ['_text' => $dates['issue_time']],
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['listID' => '0101'],
                '_text' => $catalog['type'],
            ],
            'cbc:Note' => [],
            'cbc:DocumentCurrencyCode' => ['_text' => 'PEN'],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => '6'],
                            '_text' => trim((string) ($branch?->ruc ?? '0')),
                        ],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => ['_text' => trim((string) ($branch?->legal_name ?? config('app.name')))],
                        'cac:RegistrationAddress' => [
                            'cbc:AddressTypeCode' => ['_text' => '0000'],
                            'cac:AddressLine' => [
                                'cbc:Line' => ['_text' => $supplierAddress],
                            ],
                        ],
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $customerDocType],
                            '_text' => $customerDocument,
                        ],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => [
                            '_text' => $this->sanitizeCustomerRegistrationName($customerName, $customerDocType),
                        ],
                    ],
                ],
            ],
            'cac:InvoiceLine' => [],
        ];

        if ($catalog['type'] === '01') {
            $documentBody['cac:PaymentTerms'] = [[
                'cbc:ID' => ['_text' => 'FormaPago'],
                'cbc:PaymentMeansID' => ['_text' => 'Contado'],
            ]];
        }

        $lineIndex = 1;
        $headerSubtotal = 0.0;
        $headerTax = 0.0;
        $headerTotal = 0.0;
        foreach ($details as $detail) {
            $qty = (float) ($detail->quantity ?? 0);
            $courtesyQty = (float) ($detail->courtesy_quantity ?? 0);
            $billableQty = max(0, $qty - min($qty, $courtesyQty));
            if ($billableQty <= 0) {
                continue;
            }

            $lineTotal = round((float) ($detail->amount ?? 0), 2);
            if ($lineTotal <= 0) {
                continue;
            }

            $taxPercent = 18.0;
            $taxFactor = 0.18;
            $lineSubtotal = round($lineTotal / (1 + $taxFactor), 2);
            $lineIgv = round($lineTotal - $lineSubtotal, 2);
            $grossUnitPrice = round($lineTotal / $billableQty, 2);
            $unitValue = round($lineSubtotal / $billableQty, 2);

            $description = trim((string) ($detail->description ?? 'Producto'));
            $complements = collect($detail->complements ?? [])
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->values();
            if ($complements->isNotEmpty()) {
                $description .= ' - '.implode(', ', $complements->all());
            }

            $documentBody['cac:InvoiceLine'][] = [
                'cbc:ID' => ['_text' => $lineIndex],
                'cbc:InvoicedQuantity' => [
                    '_attributes' => ['unitCode' => 'NIU'],
                    '_text' => $billableQty,
                ],
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $lineSubtotal,
                ],
                'cac:PricingReference' => [
                    'cac:AlternativeConditionPrice' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $grossUnitPrice,
                        ],
                        'cbc:PriceTypeCode' => ['_text' => '01'],
                    ],
                ],
                'cac:TaxTotal' => [
                    'cbc:TaxAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $lineIgv,
                    ],
                    'cac:TaxSubtotal' => [[
                        'cbc:TaxableAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $lineSubtotal,
                        ],
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $lineIgv,
                        ],
                        'cac:TaxCategory' => [
                            'cbc:Percent' => ['_text' => 18],
                            'cbc:TaxExemptionReasonCode' => ['_text' => '10'],
                            'cac:TaxScheme' => [
                                'cbc:ID' => ['_text' => '1000'],
                                'cbc:Name' => ['_text' => 'IGV'],
                                'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                            ],
                        ],
                    ]],
                ],
                'cac:Item' => [
                    'cbc:Description' => ['_text' => $description],
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $unitValue,
                    ],
                ],
            ];

            $headerSubtotal += $lineSubtotal;
            $headerTax += $lineIgv;
            $headerTotal += $lineTotal;

            $lineIndex++;
        }

        $headerSubtotal = round($headerSubtotal, 2);
        $headerTax = round($headerTax, 2);
        $headerTotal = round($headerTotal, 2);

        if ($headerTotal <= 0) {
            $headerSubtotal = round((float) ($totals['subtotal'] ?? 0), 2);
            $headerTax = round((float) ($totals['tax'] ?? 0), 2);
            $headerTotal = round((float) ($totals['total'] ?? 0), 2);
        }

        $documentBody['cac:TaxTotal'] = [
            'cbc:TaxAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerTax,
            ],
            'cac:TaxSubtotal' => [[
                'cbc:TaxableAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerSubtotal,
                ],
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerTax,
                ],
                'cac:TaxCategory' => [
                    'cbc:Percent' => ['_text' => 18],
                    'cac:TaxScheme' => [
                        'cbc:ID' => ['_text' => '1000'],
                        'cbc:Name' => ['_text' => 'IGV'],
                        'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                    ],
                ],
            ]],
        ];

        $documentBody['cac:LegalMonetaryTotal'] = [
            'cbc:LineExtensionAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerSubtotal,
            ],
            'cbc:TaxInclusiveAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerTotal,
            ],
            'cbc:PayableAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerTotal,
            ],
        ];

        return $documentBody;
    }

    private function validateDocumentBodyForSunat(array &$documentBody): void
    {
        $lines = &$documentBody['cac:InvoiceLine'];
        if (! is_array($lines) || count($lines) === 0) {
            throw new \RuntimeException('No se puede emitir electrónicamente: el comprobante no tiene líneas válidas para SUNAT.');
        }

        // Armonización y validación SUNAT (Regla 3462): La tasa del IGV debe ser idéntica a la vigencial (18) en todas las líneas del comprobante
        foreach ($lines as &$line) {
            $taxReasonCode = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:TaxExemptionReasonCode._text', '10'));
            if ($taxReasonCode === '10') {
                data_set($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:Percent._text', 18);
            }
        }
        unset($line);

        // Cabecera del IGV obligatoria al 18% para SUNAT 3462
        data_set($documentBody, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:Percent._text', 18);

        foreach ($lines as $idx => $line) {
            $lineNumber = $idx + 1;
            $taxSchemeId = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:ID._text', ''));
            $taxSchemeName = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:Name._text', ''));
            $taxTypeCode = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:TaxTypeCode._text', ''));
            $taxReasonCode = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:TaxExemptionReasonCode._text', ''));
            $taxPercentRaw = data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:Percent._text');
            $taxAmountRaw = data_get($line, 'cac:TaxTotal.cbc:TaxAmount._text');
            $grossUnitPriceRaw = data_get($line, 'cac:PricingReference.cac:AlternativeConditionPrice.cbc:PriceAmount._text');
            $lineSubtotalRaw = data_get($line, 'cbc:LineExtensionAmount._text');

            if ($taxSchemeId === '' || $taxSchemeName === '' || $taxTypeCode === '' || $taxReasonCode === '') {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' no tiene tributo IGV válido. Verifique configuración tributaria del producto.');
            }

            if (! is_numeric((string) $taxPercentRaw) || ! is_numeric((string) $taxAmountRaw)) {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' tiene datos tributarios inválidos (porcentaje/monto IGV).');
            }

            if (! is_numeric((string) $grossUnitPriceRaw) || (float) $grossUnitPriceRaw <= 0 || ! is_numeric((string) $lineSubtotalRaw) || (float) $lineSubtotalRaw <= 0) {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' tiene importe cero o inválido para SUNAT.');
            }
        }

        $headerTaxSchemeId = trim((string) data_get($documentBody, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:ID._text', ''));
        if ($headerTaxSchemeId === '') {
            throw new \RuntimeException('No se puede emitir electrónicamente: el resumen tributario del comprobante está incompleto.');
        }
    }

    private function resolveDetailsForSale(Movement $sale): Collection
    {
        if ($sale->salesMovement) {
            return $sale->salesMovement->details->where('status', '!=', 'C')->values();
        }

        if ($sale->orderMovement) {
            return $sale->orderMovement->details->where('status', '!=', 'C')->values();
        }

        return collect();
    }

    private function resolveDefaultTaxPercentForBranch(?Branch $branch): float
    {
        $val = null;

        if ($branch?->id) {
            $val = BranchParameter::query()
                ->join('parameters as p', 'p.id', '=', 'branch_parameters.parameter_id')
                ->where('branch_parameters.branch_id', $branch->id)
                ->whereRaw('LOWER(p.description) = ?', ['igv_defecto'])
                ->value('branch_parameters.value');
        }

        if ($val !== null && is_numeric($val)) {
            $taxRate = TaxRate::query()->whereKey((int) $val)->first();
            if ($taxRate && is_numeric($taxRate->tax_rate) && (float) $taxRate->tax_rate > 0) {
                return (float) $taxRate->tax_rate;
            }
            if ((float) $val > 0) {
                return (float) $val;
            }
        }

        $taxRate = TaxRate::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->first();

        return ($taxRate && is_numeric($taxRate->tax_rate) && (float) $taxRate->tax_rate > 0)
            ? (float) $taxRate->tax_rate
            : 18.0;
    }

    private function sanitizeCustomerRegistrationName(string $name, string $docType): string
    {
        $name = trim($name);

        // Si es boleta sin DNI (docType 0) o está vacío
        if ($name === '' || $name === '-' || $docType === '0') {
            return 'CLIENTES VARIOS';
        }

        // Si tiene menos de 3 caracteres (ej. "Dj"), SUNAT rechaza con el Error 2022 (estándar mínimo 3 caracteres)
        if (mb_strlen($name, 'UTF-8') < 3) {
            return mb_strtoupper($name, 'UTF-8') . ' CLIENTE';
        }

        return mb_strtoupper($name, 'UTF-8');
    }

    private function findUrlByKeyword(array $payload, array $keywords): ?string
    {
        $urls = [];
        array_walk_recursive($payload, function ($value) use (&$urls) {
            if (is_string($value) && Str::startsWith($value, ['http://', 'https://'])) {
                $urls[] = $value;
            }
        });

        foreach ($urls as $url) {
            $normalized = Str::lower($url);
            $matched = true;
            foreach ($keywords as $keyword) {
                if (! str_contains($normalized, Str::lower($keyword))) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $url;
            }
        }

        return null;
    }
}
