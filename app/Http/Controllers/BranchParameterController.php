<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\BranchParameter;
use App\Models\Branch;
use App\Models\ParameterCategories;
use App\Models\Operation;
use App\Models\DocumentType;
use App\Models\TaxRate;
use App\Models\PaymentMethod;
use App\Models\Parameters;
use App\Models\PrinterBranch;

class BranchParameterController extends Controller
{
    private function syncQzCertificateFiles(Request $request, int $branchId): void
    {
        $hasCertificate = $request->hasFile('qz_certificate_file');
        $hasPrivateKey = $request->hasFile('qz_private_key_file');
        if (! $hasCertificate && ! $hasPrivateKey) return;

        if (! $hasCertificate || ! $hasPrivateKey) {
            throw ValidationException::withMessages([
                'qz_certificate_file' => 'Debe cargar juntos el certificado digital y la clave privada de QZ Tray.',
            ]);
        }

        $certificateFile = $request->file('qz_certificate_file');
        $privateKeyFile = $request->file('qz_private_key_file');
        if (($certificateFile?->getSize() ?? 0) > 65536 || ($privateKeyFile?->getSize() ?? 0) > 65536) {
            throw ValidationException::withMessages(['qz_certificate_file' => 'Cada archivo QZ debe pesar como máximo 64 KB.']);
        }

        $certificate = trim((string) file_get_contents($certificateFile->getRealPath()));
        $privateKey = trim((string) file_get_contents($privateKeyFile->getRealPath()));
        $certResource = openssl_x509_read($certificate);
        $keyResource = openssl_pkey_get_private($privateKey);
        if ($certResource === false || $keyResource === false) {
            throw ValidationException::withMessages([
                'qz_certificate_file' => 'Los archivos no contienen un certificado y una clave privada PEM válidos.',
            ]);
        }

        $certPublic = openssl_pkey_get_public($certificate);
        $certDetails = $certPublic !== false ? openssl_pkey_get_details($certPublic) : false;
        $keyDetails = openssl_pkey_get_details($keyResource);
        if (! $certDetails || ! $keyDetails || ($certDetails['key'] ?? '') !== ($keyDetails['key'] ?? '')) {
            throw ValidationException::withMessages([
                'qz_private_key_file' => 'La clave privada no corresponde al certificado digital seleccionado.',
            ]);
        }

        $basePath = 'qz/branches/'.$branchId;
        $certificatePath = $basePath.'/digital-certificate.pem';
        $privateKeyPath = $basePath.'/private-key.pem';
        if (! Storage::disk('local')->put($certificatePath, $certificate) ||
            ! Storage::disk('local')->put($privateKeyPath, $privateKey)) {
            throw ValidationException::withMessages(['qz_certificate_file' => 'No se pudieron guardar los archivos QZ.']);
        }

        foreach ([
            'Certificado digital QZ Tray' => $certificatePath,
            'Clave privada QZ Tray' => $privateKeyPath,
        ] as $description => $path) {
            $parameterId = Parameters::query()->where('description', $description)->value('id');
            if (! $parameterId) continue;
            BranchParameter::query()->updateOrCreate(
                ['branch_id' => $branchId, 'parameter_id' => (int) $parameterId],
                ['value' => $path, 'deleted_at' => null]
            );
        }
    }

    /**
     * Sincroniza métodos de pago permitidos para la sucursal.
     * Vacío o "todos los activos" elimina filas del pivote (sin restricción = se muestran todos en POS).
     */
    private function syncBranchPaymentMethodsFromRequest(Request $request, int $branchId): void
    {
        if (!$request->has('branch_payment_methods_include')) {
            return;
        }

        $selected = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->input('branch_payment_method_ids', [])
        ))));

        $allActiveIds = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        sort($selected);

        if ($selected === [] || $selected === $allActiveIds) {
            DB::table('branch_payment_methods')->where('branch_id', $branchId)->delete();

            return;
        }

        DB::table('branch_payment_methods')->where('branch_id', $branchId)->delete();
        $now = now();
        foreach ($selected as $pid) {
            if (! in_array($pid, $allActiveIds, true)) {
                continue;
            }
            DB::table('branch_payment_methods')->insert([
                'branch_id' => $branchId,
                'payment_method_id' => $pid,
                'status' => 'E',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function index(Request $request)
    {
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();

        if ($viewId && $branchId && $profileId) {
            $operaciones = Operation::query()
                ->select('operations.*')
                ->join('branch_operation', function ($join) use ($branchId) {
                    $join->on('branch_operation.operation_id', '=', 'operations.id')
                        ->where('branch_operation.branch_id', $branchId)
                        ->where('branch_operation.status', 1)
                        ->whereNull('branch_operation.deleted_at');
                })
                ->join('operation_profile_branch', function ($join) use ($branchId, $profileId) {
                    $join->on('operation_profile_branch.operation_id', '=', 'operations.id')
                        ->where('operation_profile_branch.branch_id', $branchId)
                        ->where('operation_profile_branch.profile_id', $profileId)
                        ->where('operation_profile_branch.status', 1)
                        ->whereNull('operation_profile_branch.deleted_at');
                })
                ->where('operations.status', 1)
                ->where('operations.view_id', $viewId)
                ->whereNull('operations.deleted_at')
                ->orderBy('operations.id')
                ->distinct()
                ->get();
        }

        // ==========================================
        // TODAS las categorías con TODOS los parámetros activos.
        // LEFT JOIN branch_parameters: si no existe para esta sucursal, se muestra con valor por defecto.
        // ==========================================
        $categories = collect();

        if ($branchId) {
            $categories = ParameterCategories::whereHas('parameters', function ($query) {
                $query->where('parameters.status', 1)->whereNull('parameters.deleted_at');
            })
                ->with(['parameters' => function ($query) use ($branchId) {
                    $query->where('parameters.status', 1)
                        ->whereNull('parameters.deleted_at')
                        ->leftJoin('branch_parameters', function ($join) use ($branchId) {
                            $join->on('parameters.id', '=', 'branch_parameters.parameter_id')
                                ->where('branch_parameters.branch_id', $branchId)
                                ->whereNull('branch_parameters.deleted_at');
                        })
                        ->select(
                            'parameters.id',
                            'parameters.description',
                            'parameters.value',
                            'parameters.parameter_category_id',
                            'parameters.status',
                            'parameters.created_at',
                            'parameters.updated_at',
                            'parameters.deleted_at',
                            DB::raw('COALESCE(branch_parameters.value, parameters.value) as branch_value'),
                            'branch_parameters.id as branch_parameter_id'
                        )
                        ->orderBy('parameters.id');
                }])
                ->orderBy('id')
                ->get();
        }

        $tiposVenta = DocumentType::where('movement_type_id', 2)->get();

        $igv = TaxRate::where('status', true)->get();

        $paymentMethods = PaymentMethod::where('status', true)->orderBy('order_num')->get();
        $printers = $branchId
            ? PrinterBranch::query()->where('branch_id', $branchId)->where('status', 'E')->orderBy('name')->get(['id', 'name', 'ip', 'width'])
            : collect();

        $branchPaymentMethodIds = [];
        if ($branchId) {
            $pivotIds = DB::table('branch_payment_methods')
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->pluck('payment_method_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $allActiveIds = PaymentMethod::query()
                ->where('status', true)
                ->orderBy('order_num')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $branchPaymentMethodIds = $pivotIds === []
                ? $allActiveIds
                : $pivotIds;
        }

        return view('branch_parameters.index', [
            'title' => 'Parámetros de Sucursal',
            'categories' => $categories,
            'operaciones' => $operaciones,
            'tiposVenta' => $tiposVenta,
            'igv' => $igv,
            'paymentMethods' => $paymentMethods,
            'branchPaymentMethodIds' => $branchPaymentMethodIds,
            'printers' => $printers,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = $request->session()->get('branch_id');

        if (!$branchId) {
            return redirect()->back()->with('error', 'No se detectó una sucursal activa en la sesión.');
        }

        $parameters = $request->input('parameters');

        if (is_array($parameters) || $request->hasFile('qz_certificate_file') || $request->hasFile('qz_private_key_file')) {
            DB::beginTransaction();
            try {
                $this->syncBranchPaymentMethodsFromRequest($request, (int) $branchId);
                $this->syncQzCertificateFiles($request, (int) $branchId);

                foreach ((array) $parameters as $paramKey => $value) {
                    $valorSeguro = $value ?? '';
                    $parameterIdForHook = null;

                    // Clave puede ser branch_parameter_id (numérico) o "p{parameter_id}" para nuevos
                    if (is_numeric($paramKey)) {
                        $branchParam = BranchParameter::where('id', $paramKey)
                            ->where('branch_id', $branchId)
                            ->first();
                        if ($branchParam) {
                            $branchParam->update(['value' => $valorSeguro]);
                            $parameterIdForHook = (int) $branchParam->parameter_id;
                        }
                    } elseif (str_starts_with((string) $paramKey, 'p') && is_numeric(substr($paramKey, 1))) {
                        $parameterId = (int) substr($paramKey, 1);
                        $parameterIdForHook = $parameterId;
                        $branchParam = BranchParameter::where('parameter_id', $parameterId)
                            ->where('branch_id', $branchId)
                            ->whereNull('deleted_at')
                            ->first();
                        if ($branchParam) {
                            $branchParam->update(['value' => $valorSeguro]);
                        } else {
                            BranchParameter::create([
                                'parameter_id' => $parameterId,
                                'branch_id' => $branchId,
                                'value' => $valorSeguro,
                            ]);
                        }
                    }

                    // Hook: si el parámetro corresponde a "Permitir vender con stock 0",
                    // sincronizar también el flag real en branches.allow_zero_stock_sales.
                    if ($parameterIdForHook) {
                        $param = Parameters::query()->where('id', (int) $parameterIdForHook)->first(['id', 'description']);
                        $desc = mb_strtolower(trim((string) ($param?->description ?? '')), 'UTF-8');
                        $isAllowZeroStockSalesParam =
                            str_contains($desc, 'permitir') &&
                            str_contains($desc, 'stock') &&
                            (str_contains($desc, '0') || str_contains($desc, 'cero'));
                        if ($isAllowZeroStockSalesParam) {
                            $raw = mb_strtolower(trim((string) $valorSeguro), 'UTF-8');
                            $bool = in_array($raw, ['1', 'si', 'sí', 'true', 'on'], true);
                            Branch::query()
                                ->where('id', (int) $branchId)
                                ->update(['allow_zero_stock_sales' => $bool]);
                        }
                    }
                }
                DB::commit();

                return redirect()->back()->with('success', 'Parámetros actualizados correctamente.');
            } catch (ValidationException $e) {
                DB::rollBack();
                throw $e;
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Ocurrió un error al actualizar los parámetros: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('warning', 'No se enviaron datos para actualizar.');
    }
}
