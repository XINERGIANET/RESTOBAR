<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchElectronicBillingConfig;
use App\Models\BranchParameter;
use App\Models\Movement;
use App\Models\TaxRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApisunatService
{
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

        if ($correlativeResp->failed()) {
            throw new \RuntimeException('Error consultando correlativo en Apisunat: '.$correlativeResp->body());
        }

        $suggestedNumber = trim((string) data_get($correlativeResp->object(), 'suggestedNumber', ''));
        if ($suggestedNumber === '' || ! ctype_digit($suggestedNumber)) {
            throw new \RuntimeException('Apisunat devolvió un correlativo inválido.');
        }

        $number = str_pad($suggestedNumber, 8, '0', STR_PAD_LEFT);
        $fileName = trim((string) ($branch?->ruc ?? '0')).'-'.$catalog['type'].'-'.$catalog['serie'].'-'.$number;
        $documentBody = $this->buildDocumentBody($sale, $catalog, $customerDocument, $customerDocType, $totals, $number);
        $this->validateDocumentBodyForSunat($documentBody);

        $sendResp = Http::timeout(35)->post($apiUrl.'/personas/v1/sendBill', [
            'personaId' => (string) $config->persona_id,
            'personaToken' => (string) $config->persona_token,
            'fileName' => $fileName,
            'documentBody' => $documentBody,
        ]);

        if ($sendResp->failed()) {
            $errorMessage = data_get($sendResp->object(), 'error.message')
                ?: data_get($sendResp->json(), 'error.message')
                ?: $sendResp->body();

            throw new \RuntimeException('Error enviando comprobante a Apisunat: '.$errorMessage);
        }

        $result = $sendResp->object();
        $documentId = trim((string) data_get($result, 'documentId', ''));
        if ($documentId === '') {
            throw new \RuntimeException('Apisunat no devolvió documentId.');
        }

        $extraDocumentData = $this->getDocumentById($documentId, $branch);
        $urls = $this->extractDocumentUrls($extraDocumentData);

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

        $documentBody = [
            'cbc:UBLVersionID' => ['_text' => '2.1'],
            'cbc:CustomizationID' => ['_text' => '2.0'],
            'cbc:ID' => ['_text' => $catalog['serie'].'-'.$number],
            'cbc:IssueDate' => ['_text' => now()->format('Y-m-d')],
            'cbc:IssueTime' => ['_text' => now()->format('H:i:s')],
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
                                'cbc:Line' => ['_text' => trim((string) ($branch?->address ?? '-'))],
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
                            '_text' => $customerName !== '' ? $customerName : 'CLIENTES VARIOS',
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

            $taxPercent = $defaultTaxPercent;
            $taxFactor = $taxPercent > 0 ? ($taxPercent / 100) : 0.18;
            $lineSubtotal = round($taxFactor > 0 ? ($lineTotal / (1 + $taxFactor)) : $lineTotal, 2);
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
                            'cbc:Percent' => ['_text' => round($taxFactor * 100, 2)],
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
            'cac:TaxSubtotal' => [
                'cbc:TaxableAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerSubtotal,
                ],
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerTax,
                ],
                'cac:TaxCategory' => [
                    'cac:TaxScheme' => [
                        'cbc:ID' => ['_text' => '1000'],
                        'cbc:Name' => ['_text' => 'IGV'],
                        'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                    ],
                ],
            ],
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

    private function validateDocumentBodyForSunat(array $documentBody): void
    {
        $lines = data_get($documentBody, 'cac:InvoiceLine', []);
        if (! is_array($lines) || count($lines) === 0) {
            throw new \RuntimeException('No se puede emitir electrónicamente: el comprobante no tiene líneas válidas para SUNAT.');
        }

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

        $headerTaxSchemeId = trim((string) data_get($documentBody, 'cac:TaxTotal.cac:TaxSubtotal.cac:TaxCategory.cac:TaxScheme.cbc:ID._text', ''));
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
        $taxRateId = null;

        if ($branch?->id) {
            $taxRateId = BranchParameter::query()
                ->join('parameters as p', 'p.id', '=', 'branch_parameters.parameter_id')
                ->where('branch_parameters.branch_id', $branch->id)
                ->whereRaw('LOWER(p.description) = ?', ['igv_defecto'])
                ->value('branch_parameters.value');
        }

        $taxRate = $taxRateId
            ? TaxRate::query()->whereKey((int) $taxRateId)->first()
            : null;

        if (! $taxRate) {
            $taxRate = TaxRate::query()
                ->where('status', true)
                ->orderBy('order_num')
                ->first();
        }

        return $taxRate ? (float) $taxRate->tax_rate : 18.0;
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
