<?php

namespace App\Http\Controllers;

use App\Exports\SalesExport;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Card;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\CashShiftRelation;
use App\Models\Category;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Operation;
use App\Models\OrderMovement;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\PrinterBranch;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\Recipe;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Table;
use App\Models\User;
use App\Services\AccountReceivablePayableService;
use App\Services\ApisunatService;
use App\Services\KardexSyncService;
use App\Services\ThermalNetworkPrintService;
use App\Support\InsensitiveSearch;
use App\Support\LocalNetworkClient;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $branchId = session('branch_id');
        $profileId = session('profile_id') ?? $request->user()?->profile_id;
        $viewId = $request->input('view_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $personId = $request->input('person_id');
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $cashRegisterId = effective_cash_register_id($branchId ? (int) $branchId : null);
        $cashShiftRelationId = $request->input('cash_shift_relation_id');
        $saleType = $request->input('sale_type');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

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

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num')
            ->get(['id', 'description']);
        $branchClients = $this->branchClientsForBranch($branchId ? (int) $branchId : null);
        $convertibleDocumentTypes = DocumentType::query()
            ->where('movement_type_id', 2)
            ->where(function ($query) {
                $query->where('name', 'like', '%boleta%')
                    ->orWhere('name', 'like', '%factura%');
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $cashRegisters = $branchId
            ? CashRegister::query()->where('branch_id', $branchId)->orderBy('number')->get(['id', 'number'])
            : CashRegister::query()->whereRaw('1 = 0')->get(['id', 'number']);

        $effectiveCashRegisterId = $cashRegisterId;

        // Sesiones (turnos) por caja para filtrar el listado y "limpiar" al abrir una nueva
        $cashShiftSessions = collect();
        if ($branchId && $effectiveCashRegisterId) {
            $cashShiftSessions = CashShiftRelation::query()
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCashRegisterId) {
                    $q->where('cash_register_id', $effectiveCashRegisterId);
                })
                ->with(['cashMovementStart.shift', 'cashMovementEnd'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        // Por defecto: filtrar por el turno actual (o último) de la caja seleccionada/en sesión
        if (($cashShiftRelationId === null || $cashShiftRelationId === '') && $branchId && $effectiveCashRegisterId) {
            $lastShift = CashShiftRelation::query()
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCashRegisterId) {
                    $q->where('cash_register_id', $effectiveCashRegisterId);
                })
                ->latest('id')
                ->first();
            if ($lastShift) {
                $cashShiftRelationId = (string) $lastShift->id;
            }
        }

        $query = Movement::query()
            ->select('movements.*')
            ->join('sales_movements', 'sales_movements.movement_id', '=', 'movements.id')
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement', 'orderMovement.table', 'movement.orderMovement.table'])
            ->where('movements.movement_type_id', 2)
            ->when($branchId, fn ($q) => $q->where('movements.branch_id', $branchId))
            ->when(! $branchId, fn ($q) => $q->whereRaw('1 = 0'));

        if ($documentTypeId !== null && $documentTypeId !== '' && is_numeric($documentTypeId)) {
            $query->where('movements.document_type_id', (int) $documentTypeId);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('person_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        // Filtros Adicionales
        if ($personId !== null && $personId !== '') {
            $query->where('movements.person_id', $personId);
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('movements.moved_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('movements.moved_at', '<=', $dateTo.' 23:59:59');
        }
        if ($paymentMethodId) {
            $query->whereIn('movements.id', function ($sub) use ($paymentMethodId) {
                $sub->select('m.parent_movement_id')
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                    ->where('cmd.payment_method_id', $paymentMethodId)
                    ->whereNotNull('m.parent_movement_id')
                    ->whereNull('cm.deleted_at')
                    ->whereNull('cmd.deleted_at');
            });
        }
        if ($effectiveCashRegisterId) {
            $query->whereIn('movements.id', function ($sub) use ($effectiveCashRegisterId) {
                $sub->select('m.parent_movement_id')
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->where('cm.cash_register_id', $effectiveCashRegisterId)
                    ->whereNotNull('m.parent_movement_id')
                    ->whereNull('cm.deleted_at');
            });
        }

        // Filtro por turno (CashShiftRelation): ventana temporal por movimientos
        if ($cashShiftRelationId !== null && $cashShiftRelationId !== '' && $branchId && $effectiveCashRegisterId) {
            $csrApplied = CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', (int) $cashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCashRegisterId) {
                    $q->where('cash_register_id', $effectiveCashRegisterId);
                })
                ->first();

            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $query->where('movements.id', '>=', $startMid);
                if ($csrApplied->cashMovementEnd) {
                    $query->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if ($saleType !== null && $saleType !== '') {
            $query->where('sales_movements.detail_type', $saleType);
        }

        $sales = $query->orderBy('movements.moved_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $thermalPrintersIndex = $branchId
            ? PrinterBranch::query()
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->whereNotNull('ip')
                ->where('ip', '!=', '')
                ->orderBy('id')
                ->get(['id', 'name', 'ip', 'width'])
            : collect();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'sales' => $sales,
                'pagination' => [
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                ],
            ]);
        }

        $branchForClients = null;
        $departments = collect();
        $provinces = collect();
        $districts = collect();
        if ($branchId) {
            $branchForClients = Branch::query()->with('company')->find((int) $branchId);
            $departments = Location::query()
                ->where('type', 'department')
                ->orderBy('name')
                ->get(['id', 'name']);
            $provinces = Location::query()
                ->where('type', 'province')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_location_id']);
            $districts = Location::query()
                ->where('type', 'district')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_location_id']);
        }

        $viewData = [
            'sales' => $sales,
            'search' => $search,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'operaciones' => $operaciones,
            'viewId' => $viewId,
            'branchId' => $branchId,
            'branch' => $branchForClients,
            'departments' => $departments,
            'provinces' => $provinces,
            'districts' => $districts,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'documentTypeId' => $documentTypeId,
            'documentTypes' => $documentTypes,
            'branchClients' => $branchClients,
            'convertibleDocumentTypes' => $convertibleDocumentTypes,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethods' => $paymentMethods,
            'cashRegisterId' => $effectiveCashRegisterId,
            'cashRegisters' => $cashRegisters,
            'personId' => $personId,
            'saleType' => $saleType,
            'cashShiftRelationId' => $cashShiftRelationId,
            'cashShiftSessions' => $cashShiftSessions,
            'clientOnLocalNetwork' => LocalNetworkClient::isOnLocalNetwork($request),
            'thermalPrinters' => $thermalPrintersIndex,
            'thermalPrintEnabled' => (bool) config('local_network.thermal_print_enabled', true),
        ];

        return view('sales.index', $viewData);
    }

    /**
     * Página de reportes de ventas (mismo listado con filtros y opción de exportar PDF).
     */
    public function reportSales(Request $request)
    {
        return $this->index($request);
    }

    // Obtener caja desde sesión
    public function getSessionCashRegister(Request $request)
    {
        $cashRegisterId = effective_cash_register_id(session('branch_id') ? (int) session('branch_id') : null);

        return response()->json([
            'success' => true,
            'cash_register_id' => $cashRegisterId,
        ]);
    }

    // POS: vista de cobro
    public function charge(Request $request)
    {
        $personId = session('person_id');
        $person = Person::find($personId);

        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->where('movement_type_id', 2)
            ->get(['id', 'name']);

        $branchId = session('branch_id');
        $defaultDocumentTypeId = effective_default_sale_document_type_id($branchId ? (int) $branchId : null, [2]);

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $paymentGateways = PaymentGateways::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $cards = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon', 'order_num']);

        $digitalWallets = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $banks = Bank::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $cashRegisters = CashRegister::query()
            ->orderByRaw("CASE WHEN status = 'A' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);
        $people = $this->branchClientsForBranch($branchId);

        $defaultClientInList = $people->first(function ($p) {
            $first = mb_strtoupper(trim((string) ($p->first_name ?? '')));
            $last = mb_strtoupper(trim((string) ($p->last_name ?? '')));
            return $first === 'CLIENTES' && $last === 'VARIOS';
        });
        $defaultClientId = $defaultClientInList?->id;

        if (! $defaultClientId) {
            $defaultClientId = Person::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereRaw('UPPER(first_name) = ?', ['CLIENTES'])
                ->whereRaw('UPPER(last_name) = ?', ['VARIOS'])
                ->value('id');
        }
        if ($defaultClientId && ! $people->contains(fn ($p) => (int) $p->id === (int) $defaultClientId)) {
            $defaultClient = Person::query()->find($defaultClientId);
            if ($defaultClient) {
                $people = $people->prepend($defaultClient)->unique('id')->values();
            }
        }

        // Si se pasa un movement_id, cargar la venta pendiente o con pago parcial
        $draftSale = null;
        $pendingAmount = 0;
        if ($request->has('movement_id')) {
            $movement = Movement::with(['salesMovement.details.product', 'cashMovement'])
                ->where('id', $request->movement_id)
                ->whereIn('status', ['P', 'A']) // Cargar si está pendiente o activo (puede tener deuda)
                ->first();

            if ($movement && $movement->salesMovement) {
                // Calcular el monto pendiente si hay una deuda
                $relatedCashMovement = $movement->cashMovement ?: $this->resolveCashMovementBySaleMovement($movement->id);
                if ($relatedCashMovement) {
                    $debt = DB::table('cash_movement_details')
                        ->where('cash_movement_id', $relatedCashMovement->id)
                        ->where('type', 'DEUDA')
                        ->where('status', 'A')
                        ->sum('amount');
                    $pendingAmount = $debt ?? 0;
                }

                $defaultTaxRate = TaxRate::where('status', true)->orderBy('order_num')->first();
                $defaultTaxPct = $defaultTaxRate ? (float) $defaultTaxRate->tax_rate : 18;
                $sm = $movement->salesMovement;
                $draftSale = [
                    'id' => $movement->id,
                    'number' => $movement->number,
                    'clientId' => $movement->person_id,
                    'sale_payment_mode' => (($sm->payment_type ?? '') === 'CREDIT') ? 'CREDITO' : 'CONTADO',
                    'credit_days' => max(0, (int) ($sm->credit_days ?? 0)),
                    'items' => $movement->salesMovement->details->map(function ($detail) use ($defaultTaxPct) {
                        $taxRatePct = $defaultTaxPct;
                        if ($detail->tax_rate_snapshot && isset($detail->tax_rate_snapshot['tax_rate'])) {
                            $taxRatePct = (float) $detail->tax_rate_snapshot['tax_rate'];
                        }
                        $amountWithTax = (float) $detail->amount;
                        $quantity = (float) $detail->quantity ?: 1;
                        $priceWithTax = $quantity > 0 ? $amountWithTax / $quantity : 0;

                        return [
                            'pId' => $detail->product_id,
                            'name' => $detail->product->description ?? 'Producto #'.$detail->product_id,
                            'qty' => $quantity,
                            'price' => $priceWithTax,
                            'tax_rate' => $taxRatePct,
                            'note' => $detail->comment ?? '',
                            'product_note' => $detail->product->note ?? null,
                        ];
                    })->toArray(),
                    'clientName' => $movement->person_name ?? 'Público General',
                    'notes' => $movement->comment ?? '',
                    'pendingAmount' => $pendingAmount,
                    'product_notes' => $movement->salesMovement->details->pluck('product.note')->toArray(),
                ];
            }
        }

        // Obtener todos los productos para poder mostrar sus nombres cuando se carga desde localStorage
        $products = Product::pluck('description', 'id')->toArray();

        $productBranches = ProductBranch::query()
            ->where('branch_id', $branchId ?? 0)
            ->with(['product.productType', 'taxRate'])
            ->get()
            ->filter(function ($pb) {
                $pt = $pb->product?->productType;

                return $pt === null || $pt->isSellable();
            })
            ->map(fn ($pb) => [
                'product_id' => (int) $pb->product_id,
                'price' => (float) $pb->price,
                'tax_rate' => $pb->taxRate ? (float) $pb->taxRate->tax_rate : null,
            ])
            ->values();

        $branch = $branchId ? Branch::find($branchId) : null;
        $company = $branch ? \App\Models\Company::find($branch->company_id) : null;

        return view('sales.charge', [
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'banks' => $banks,
            'cashRegisters' => $cashRegisters,
            'people' => $people,
            'defaultClientId' => $defaultClientId,
            'draftSale' => $draftSale,
            'pendingAmount' => $pendingAmount,
            'products' => $products,
            'productBranches' => $productBranches,
            'person' => $person,
            'branch' => $branch,
            'company' => $company,
        ]);
    }

    // POS: procesar venta
    public function processSale(Request $request)
    {
        try {
            $branchId = session('branch_id');
            $restrictedPmIds = PaymentMethod::paymentMethodIdsForBranchOrNull($branchId ? (int) $branchId : null);
            $paymentMethodIdRules = [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where('status', true),
            ];
            if ($restrictedPmIds !== null) {
                $paymentMethodIdRules[] = Rule::in($restrictedPmIds);
            }

            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.pId' => 'required|integer|exists:products,id',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.courtesyQty' => 'nullable|numeric|min:0',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'document_type_id' => 'required|integer|exists:document_types,id',
                'cash_register_id' => 'nullable|integer|exists:cash_registers,id',
                'person_id' => 'nullable|integer|exists:people,id',
                'payment_methods' => 'nullable|array',
                'payment_methods.*.payment_method_id' => array_merge(['nullable', 'integer'], array_slice($paymentMethodIdRules, 1)),
                'payment_methods.*.amount' => 'nullable|numeric|min:0',
                'payment_methods.*.payment_gateway_id' => 'nullable|integer|exists:payment_gateways,id',
                'payment_methods.*.card_id' => 'nullable|integer|exists:cards,id',
                'payment_methods.*.digital_wallet_id' => 'nullable|integer|exists:digital_wallets,id',
                'payment_methods.*.bank_id' => 'nullable|integer|exists:banks,id',
                'sale_payment_mode' => 'nullable|string|in:CONTADO,CREDITO',
                'credit_days' => 'nullable|integer|min:0|max:3650',
                'notes' => 'nullable|string',
                'movement_id' => 'nullable|integer|exists:movements,id', // ID del borrador a completar
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        // ── Validar caja ANTES de iniciar la transacción ──────────────────────
        $branchIdForCheck = (int) session('branch_id');
        $cashRegisterId = effective_cash_register_id($branchIdForCheck);

        if (! $cashRegisterId) {
            return response()->json(['success' => false, 'message' => 'Selecciona una caja antes de registrar la venta.'], 422);
        }

        $cashRegisterCheck = CashRegister::where('id', $cashRegisterId)->where('status', true)->first();
        if (! $cashRegisterCheck) {
            return response()->json(['success' => false, 'message' => 'La caja seleccionada no está habilitada.'], 422);
        }

        $activeShiftCheck = CashShiftRelation::query()
            ->where('branch_id', $branchIdForCheck)
            ->where('status', '1')
            ->whereNull('ended_at')
            ->whereNull('cash_movement_end_id')
            ->whereHas('cashMovementStart', function ($q) use ($cashRegisterId) {
                $q->where('cash_register_id', $cashRegisterId);
            })
            ->latest('id')
            ->first();

        if (! $activeShiftCheck) {
            return response()->json([
                'success' => false,
                'message' => 'La caja "'.$cashRegisterCheck->number.'" no tiene un turno abierto. Realice una Apertura de Caja primero.',
            ], 422);
        }
        // ──────────────────────────────────────────────────────────────────────

        $calcPreview = $this->calculateSubtotalAndTaxFromItems($request->items, $branchIdForCheck);
        $totalPreview = round((float) $calcPreview['total'], 2);

        $isDraft = $request->has('movement_id') && $request->movement_id;
        $salePaymentMode = strtoupper((string) $request->input('sale_payment_mode', 'CONTADO'));
        if (! in_array($salePaymentMode, ['CONTADO', 'CREDITO'], true)) {
            $salePaymentMode = 'CONTADO';
        }
        $isCredit = ($salePaymentMode === 'CREDITO');
        $creditDays = max(0, (int) $request->input('credit_days', 0));

        $pmRaw = $request->input('payment_methods', []);
        if (! is_array($pmRaw)) {
            $pmRaw = [];
        }
        $pmSum = 0.0;
        foreach ($pmRaw as $row) {
            if (! is_array($row)) {
                return response()->json(['success' => false, 'message' => 'Formato inválido en métodos de pago.'], 422);
            }
            $amt = round((float) ($row['amount'] ?? 0), 2);
            $pmSum += $amt;
            if ($amt > 0.02 && empty($row['payment_method_id'])) {
                return response()->json(['success' => false, 'message' => 'Indique el método de pago en cada línea con importe mayor a cero.'], 422);
            }
        }
        $pmSum = round($pmSum, 2);

        if ($isCredit) {
            if (empty($validated['person_id'])) {
                return response()->json(['success' => false, 'message' => 'Para venta a crédito debe seleccionar un cliente identificado.'], 422);
            }
            if ($pmSum > $totalPreview + 0.02) {
                return response()->json(['success' => false, 'message' => 'El total abonado no puede superar el importe de la venta.'], 422);
            }
        } else {
            if (count($pmRaw) < 1) {
                return response()->json(['success' => false, 'message' => 'Agregue al menos un método de pago.'], 422);
            }
            if (abs($pmSum - $totalPreview) > 0.02) {
                return response()->json([
                    'success' => false,
                    'message' => 'La suma de los métodos de pago (S/ '.number_format($pmSum, 2, '.', '').') debe ser igual al total (S/ '.number_format($totalPreview, 2, '.', '').').',
                ], 422);
            }
            foreach ($pmRaw as $row) {
                $amt = round((float) ($row['amount'] ?? 0), 2);
                if ($amt < 0.01) {
                    return response()->json(['success' => false, 'message' => 'Cada método de pago debe tener un importe mayor a cero.'], 422);
                }
                $pid = (int) ($row['payment_method_id'] ?? 0);
                if ($pid <= 0) {
                    return response()->json(['success' => false, 'message' => 'Seleccione un método de pago válido en cada línea.'], 422);
                }
                $pmCheck = PaymentMethod::query()->where('id', $pid)->where('status', true)->first();
                if (! $pmCheck) {
                    return response()->json(['success' => false, 'message' => 'Método de pago no válido.'], 422);
                }
                if ($restrictedPmIds !== null && ! in_array($pid, $restrictedPmIds, true)) {
                    return response()->json(['success' => false, 'message' => 'Método de pago no permitido en esta sucursal.'], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);

            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();

            // Si no hay turno de la sucursal, usar el primero disponible
            if (! $shift) {
                $shift = Shift::first();
            }

            if (! $shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }

            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();

            if (! $movementType) {
                $movementType = MovementType::first();
            }

            if (! $movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido. Por favor, crea un tipo de movimiento primero.');
            }

            $documentType = DocumentType::findOrFail($request->document_type_id);

            $selectedPerson = null;
            if (! empty($validated['person_id'])) {
                $selectedPerson = Person::query()
                    ->where('id', $validated['person_id'])
                    ->where('branch_id', $branchId)
                    ->firstOrFail();
            }

            // Obtener concepto de pago para ventas (Pago de cliente - ID 5)
            $paymentConcept = PaymentConcept::find(3); // Pago de cliente

            // Si no existe el ID 5, buscar por descripción
            if (! $paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%pago de cliente%')
                    ->orWhere('description', 'like', '%Pago de cliente%')
                    ->first();
            }

            // Si aún no se encuentra, buscar cualquier concepto de ingreso relacionado con venta
            if (! $paymentConcept) {
                $paymentConcept = PaymentConcept::where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%cliente%')
                    ->where('type', 'I')
                    ->first();
            }

            // Si aún no se encuentra, buscar cualquier concepto de ingreso
            if (! $paymentConcept) {
                $paymentConcept = PaymentConcept::where('type', 'I')->first();
            }

            if (! $paymentConcept) {
                throw new \Exception('No se encontró un concepto de pago válido. Por favor, crea un concepto de pago primero.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $calculated = $this->calculateSubtotalAndTaxFromItems($request->items, $branchId);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            if ($validationMessage = $this->validateCustomerForDocumentType($documentType, $selectedPerson, (float) $total)) {
                throw new \Exception($validationMessage);
            }

            // Caja obtenida desde la sesión
            $cashRegister = CashRegister::findOrFail($cashRegisterId);

            $smPaymentType = $isCredit ? 'CREDIT' : 'CONTADO';
            $creditIgnoresCashLines = $isCredit
                && (float) $total > 0
                && $pmSum > 0
                && $pmSum >= (float) $total - 0.02;
            $paymentMethodsForCash = $creditIgnoresCashLines ? [] : $pmRaw;

            // Verificar si es un borrador a completar
            $movement = null;
            $number = null;

            if ($isDraft) {
                // Cargar el movimiento existente (borrador)
                $movement = Movement::where('id', $request->movement_id)
                    ->where('branch_id', $branchId)
                    ->first();

                if (! $movement) {
                    throw new \Exception('No se encontró el movimiento de venta');
                }

                $number = $movement->number;

                // Actualizar el movimiento - siempre se completa el pago completo
                $movement->update([
                    'comment' => $request->notes ?? 'Venta completada desde punto de venta',
                    'status' => 'A', // Siempre Activo (pago completo)
                    'document_type_id' => $documentType->id,
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '').' '.($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                ]);

                // Eliminar los detalles anteriores para recrearlos
                if ($movement->salesMovement) {
                    SalesMovementDetail::where('sales_movement_id', $movement->salesMovement->id)->delete();
                }
            } else {
                // Crear nuevo Movement
                $number = $this->generateSaleNumber(
                    (int) $documentType->id,
                    (int) $cashRegister->id,
                    true
                );

                $movement = Movement::create([
                    'number' => $number,
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '').' '.($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->id,
                    'responsible_name' => $user?->person ? trim(($user->person->first_name ?? '').' '.($user->person->last_name ?? '')) : ($user?->name ?? 'Sistema'),
                    'comment' => $request->notes ?? '',
                    'status' => 'A', // Siempre Activo (pago completo)
                    'movement_type_id' => $movementType->id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                    'parent_movement_id' => null,
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                    ],
                ]);
            }

            // Crear o actualizar SalesMovement
            if ($isDraft && $movement->salesMovement) {
                // Actualizar el SalesMovement existente
                $salesMovement = $movement->salesMovement;
                $salesMovement->update([
                    'payment_type' => $smPaymentType,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);
            } else {
                // Crear nuevo SalesMovement
                $salesMovement = SalesMovement::create([
                    'branch_snapshot' => [
                        'id' => $branch->id,
                        'legal_name' => $branch->legal_name,
                    ],
                    'series' => '001',
                    'year' => Carbon::now()->year,
                    'detail_type' => 'DETALLADO',
                    'consumption' => 'N',
                    'payment_type' => $smPaymentType,
                    'status' => 'N',
                    'sale_type' => 'MINORISTA',
                    'currency' => 'PEN',
                    'exchange_rate' => 3.5,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear SalesMovementDetails y actualizar stock (nota por producto en comment)
            foreach ($validated['items'] as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['pId']);

                $roundUpToQuarter = function (float $qty): float {
                    if ($qty <= 0) {
                        return 0.0;
                    }
                    return round(ceil($qty * 4) / 4, 6);
                };

                // Bloquear el registro para evitar condiciones de carrera
                $productBranch = ProductBranch::with('taxRate')
                    ->where('product_id', $item['pId'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (! $productBranch) {
                    throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                }

                // Validar stock disponible
                $qty = (float) ($item['qty'] ?? 0);
                $quantityToSell = $qty;

                // Si el producto tiene receta activa en esta sucursal, el stock se controla por ingredientes.
                $recipe = Recipe::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', (int) $product->id)
                    ->where('status', 'A')
                    ->with(['ingredients'])
                    ->first();

                if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
                    $yield = (float) $recipe->yield_quantity;

                    // Calcular consumo total por ingrediente para esta línea.
                    $requiredByIngredientId = [];
                    foreach ($recipe->ingredients as $ingredient) {
                        $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                        $ingredientQty = (float) ($ingredient->quantity ?? 0);
                        if ($ingredientProductId <= 0 || $ingredientQty <= 0) {
                            continue;
                        }

                        $rawConsumption = ($ingredientQty / $yield) * $quantityToSell;
                        $consumption = $roundUpToQuarter($rawConsumption);
                        if ($consumption <= 0) {
                            continue;
                        }

                        $requiredByIngredientId[$ingredientProductId] = ($requiredByIngredientId[$ingredientProductId] ?? 0) + $consumption;
                    }

                    if (! empty($requiredByIngredientId)) {
                        $ingredientIds = array_keys($requiredByIngredientId);
                        sort($ingredientIds);

                        // Lock de stocks de ingredientes (ordenado para evitar deadlocks).
                        $ingredientBranches = ProductBranch::query()
                            ->where('branch_id', $branchId)
                            ->whereIn('product_id', $ingredientIds)
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('product_id');

                        foreach ($ingredientIds as $ingredientProductId) {
                            $pb = $ingredientBranches->get($ingredientProductId);
                            $name = Product::query()->where('id', $ingredientProductId)->value('description') ?? ('Producto #' . $ingredientProductId);
                            if (! $pb) {
                                throw new \Exception("Ingrediente \"{$name}\" no disponible en esta sucursal");
                            }

                            $current = (float) ($pb->stock ?? 0);
                            $needed = (float) $requiredByIngredientId[$ingredientProductId];

                            // Para recetas, el stock se controla por ingredientes SIEMPRE, incluso si la sucursal
                            // permite vender con stock 0 para otros productos.
                            if ($current < $needed) {
                                throw new \Exception(
                                    "Stock insuficiente para el ingrediente \"{$name}\". " .
                                    "Stock disponible: {$current}, Requerido: {$needed}"
                                );
                            }
                        }

                        // Descontar stock de ingredientes
                        foreach ($ingredientIds as $ingredientProductId) {
                            $pb = $ingredientBranches->get($ingredientProductId);
                            $current = (float) ($pb->stock ?? 0);
                            $needed = (float) $requiredByIngredientId[$ingredientProductId];
                            $newStock = $current - $needed;
                            $pb->update([
                                'stock' => max(0, round($newStock, 6)),
                            ]);
                        }
                    }
                } else {
                    $currentStock = (float) ($productBranch->stock ?? 0);
                    // Validar stock disponible si no se permite vender sin stock
                    if (! $branch->allow_zero_stock_sales && $currentStock < $quantityToSell) {
                        throw new \Exception(
                            "Stock insuficiente para el producto \"{$product->description}\". " .
                            "Stock disponible: {$currentStock}, Cantidad solicitada: {$quantityToSell}"
                        );
                    }

                    // Restar el stock del producto en la sucursal
                    $newStock = $currentStock - $quantityToSell;
                    $productBranch->update([
                        'stock' => max(0, round($newStock, 6)),
                    ]);
                }

                $unit = $product->baseUnit;
                if (! $unit) {
                    throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                }

                $taxRate = $productBranch->taxRate;
                $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $this->getDefaultTaxRateValue();

                $courtesyQty = (float) ($item['courtesyQty'] ?? $item['courtesy_quantity'] ?? 0);
                $courtesyQty = max(0, min($courtesyQty, $qty));
                $paidQty = $qty - $courtesyQty;
                // Precio de venta incluye impuesto; el monto es solo por unidades pagadas (sin cortesía).
                $itemTotal = $paidQty * (float) ($item['price'] ?? 0);
                $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
                $itemTax = $itemTotal - $itemSubtotal;

                // Nota por producto (compatibilidad note/comment) y normalización
                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;
                SalesMovementDetail::create([
                    'detail_type' => 'DETAILED',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unit->id,
                    'tax_rate_id' => $taxRate?->id,
                    'tax_rate_snapshot' => $taxRate ? [
                        'id' => $taxRate->id,
                        'description' => $taxRate->description,
                        'tax_rate' => $taxRate->tax_rate,
                    ] : null,
                    'quantity' => $item['qty'],
                    'courtesy_quantity' => $courtesyQty,
                    'amount' => $itemTotal,
                    'discount_percentage' => 0.000000,
                    'original_amount' => $itemSubtotal,
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }

            // Crear/actualizar movimiento de caja separado del movimiento de venta
            $cashEntryMovement = $this->resolveCashEntryMovementBySaleMovement($movement->id);

            if (! $cashEntryMovement) {
                $cashEntryMovement = Movement::create([
                    'number' => $this->generateCashMovementNumber(
                        (int) $branchId,
                        (int) $cashRegister->id,
                        (int) $paymentConcept->id
                    ),
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '').' '.($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'responsible_id' => $user?->id,
                    'responsible_name' => $user?->person ? trim(($user->person->first_name ?? '').' '.($user->person->last_name ?? '')) : ($user?->name ?? 'Sistema'),
                    'comment' => 'Cobro de venta '.$movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                    'branch_id' => $branchId,
                    'parent_movement_id' => $movement->id,
                ]);
            } else {
                $cashEntryMovement->update([
                    'moved_at' => now(),
                    'person_id' => $selectedPerson?->id,
                    'person_name' => $selectedPerson
                        ? trim(($selectedPerson->first_name ?? '').' '.($selectedPerson->last_name ?? ''))
                        : 'Publico General',
                    'comment' => 'Cobro de venta '.$movement->number,
                    'status' => '1',
                    'movement_type_id' => 4,
                    'document_type_id' => 9,
                ]);
            }

            // Crear o actualizar CashMovement (entrada de dinero)
            $cashMovement = CashMovements::where('movement_id', $cashEntryMovement->id)->first();

            if ($cashMovement) {
                $cashMovement->update([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                    ],
                    'branch_id' => $branchId,
                ]);
                DB::table('cash_movement_details')
                    ->where('cash_movement_id', $cashMovement->id)
                    ->delete();
            } else {
                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegister->id,
                    'cash_register' => $cashRegister->number ?? 'Caja Principal',
                    'shift_id' => $shift->id,
                    'shift_snapshot' => [
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                    ],
                    'movement_id' => $cashEntryMovement->id,
                    'branch_id' => $branchId,
                ]);
            }

            // Crear CashMovementDetail para cada método de pago
            foreach ($paymentMethodsForCash as $paymentMethodData) {
                $paymentMethod = PaymentMethod::findOrFail($paymentMethodData['payment_method_id']);
                $paymentGateway = null;
                $card = null;
                $digitalWallet = null;

                if (! empty($paymentMethodData['payment_gateway_id'])) {
                    $paymentGateway = PaymentGateways::find($paymentMethodData['payment_gateway_id']);
                }
                if (! empty($paymentMethodData['card_id'])) {
                    $card = Card::find($paymentMethodData['card_id']);
                }
                if (! empty($paymentMethodData['digital_wallet_id'])) {
                    $digitalWallet = DigitalWallet::find($paymentMethodData['digital_wallet_id']);
                }
                $bank = ! empty($paymentMethodData['bank_id'])
                    ? Bank::find($paymentMethodData['bank_id'])
                    : null;

                DB::table('cash_movement_details')->insert([
                    'cash_movement_id' => $cashMovement->id,
                    'type' => 'PAGADO',
                    'paid_at' => now(),
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method' => $paymentMethod->description ?? '',
                    'number' => $cashEntryMovement->number,
                    'card_id' => $card?->id,
                    'card' => $card?->description ?? '',
                    'bank_id' => $bank?->id,
                    'bank' => $bank?->description ?? '',
                    'digital_wallet_id' => $digitalWallet?->id,
                    'digital_wallet' => $digitalWallet?->description ?? '',
                    'payment_gateway_id' => $paymentGateway?->id,
                    'payment_gateway' => $paymentGateway?->description ?? '',
                    'amount' => $paymentMethodData['amount'],
                    'comment' => $request->notes ?? 'Venta desde punto de venta - '.$documentType->name,
                    'status' => 'A',
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($isCredit) {
                $personIdForDebt = (int) ($selectedPerson?->id ?? 0);
                if ($personIdForDebt <= 0) {
                    throw new \InvalidArgumentException('Para venta a crédito debe seleccionar un cliente identificado.');
                }
                $totalPaidForArp = $creditIgnoresCashLines ? 0.0 : $pmSum;
                $dueAt = now()->addDays($creditDays);
                app(AccountReceivablePayableService::class)->syncDebtAccountForDirectSale(
                    (int) $branchId,
                    $personIdForDebt,
                    (int) $movement->id,
                    (float) $total,
                    $totalPaidForArp,
                    $dueAt,
                    (int) $cashMovement->id,
                    $cashEntryMovement,
                    $salesMovement->fresh(),
                    $creditDays,
                    $request->input('notes') ? (string) $request->input('notes') : null
                );
            }

            app(KardexSyncService::class)->syncMovement($movement);

            DB::commit();
            $movement->refresh();
            $electronicInvoice = $this->syncElectronicInvoiceForSale($movement, app(ApisunatService::class));

            $thermalPrinterAvailable = PrinterBranch::query()
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->whereNotNull('ip')
                ->where('ip', '!=', '')
                ->exists();

            return response()->json([
                'success' => true,
                'message' => 'Venta procesada correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'cash_movement_id' => $cashEntryMovement->id,
                    'number' => $movement->number,
                    'total' => $total,
                    'electronic_invoice' => $electronicInvoice,
                ],
                'client_on_local_network' => LocalNetworkClient::isOnLocalNetwork($request),
                'thermal_printer_available' => $thermalPrinterAvailable,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar la venta: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Error al procesar la venta';

            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    // Guardar venta como borrador/pendiente (sin pago)
    public function saveDraft(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.pId' => 'required|integer|exists:products,id',
                'items.*.qty' => 'required|numeric|min:0.000001',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.courtesyQty' => 'nullable|numeric|min:0',
                'items.*.note' => 'nullable|string|max:65535',
                // Compatibilidad: algunos flujos pueden enviar `comment` en lugar de `note`
                'items.*.comment' => 'nullable|string|max:65535',
                'document_type_id' => 'nullable|integer|exists:document_types,id',
                'notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $branchId = session('branch_id');
            $branch = Branch::findOrFail($branchId);

            // Obtener turno de la sucursal
            $shift = Shift::where('branch_id', $branchId)->first();
            if (! $shift) {
                $shift = Shift::first();
            }
            if (! $shift) {
                throw new \Exception('No hay turno disponible. Por favor, crea un turno primero.');
            }

            // Obtener tipos de movimiento y documento para ventas
            $movementType = MovementType::where('description', 'like', '%venta%')
                ->orWhere('description', 'like', '%sale%')
                ->orWhere('description', 'like', '%Venta%')
                ->first();

            if (! $movementType) {
                $movementType = MovementType::first();
            }

            if (! $movementType) {
                throw new \Exception('No se encontró un tipo de movimiento válido.');
            }

            // Obtener documento por defecto si no se especifica
            $documentType = null;
            if ($request->document_type_id) {
                $documentType = DocumentType::find($request->document_type_id);
            }

            if (! $documentType) {
                $documentType = DocumentType::where('movement_type_id', $movementType->id)->first();
            }

            if (! $documentType) {
                $documentType = DocumentType::first();
            }

            if (! $documentType) {
                throw new \Exception('No se encontró un tipo de documento válido.');
            }

            // Los precios del front ya incluyen IGV. Calcular subtotal e IGV por producto según su tasa.
            $calculated = $this->calculateSubtotalAndTaxFromItems($request->items, $branchId);
            $subtotal = $calculated['subtotal'];
            $tax = $calculated['tax'];
            $total = $calculated['total'];

            // Generar numero de movimiento con serie/correlativo por documento y caja activa
            $activeCashRegisterId = $this->resolveActiveCashRegisterId((int) $branchId);
            $number = $this->generateSaleNumber(
                (int) $documentType->id,
                $activeCashRegisterId,
                true
            );

            // Crear Movement con status 'P' (Pendiente) o 'I' (Inactivo)
            $movement = Movement::create([
                'number' => $number,
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => null,
                'person_name' => 'Público General',
                'responsible_id' => $user?->id,
                'responsible_name' => $user?->name ?? 'Sistema',
                'comment' => ($request->notes ?? '').' [BORRADOR]',
                'status' => 'P', // P = Pendiente
                'movement_type_id' => $movementType->id,
                'document_type_id' => $documentType->id,
                'branch_id' => $branchId,
                'parent_movement_id' => null,
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ],
            ]);

            $activeCashRegister = $activeCashRegisterId ? \App\Models\CashRegister::find($activeCashRegisterId) : null;
            $activeSeries = $activeCashRegister?->series ?? '001';

            // Crear SalesMovement con payment_type 'CREDIT' (pendiente de pago)
            $salesMovement = SalesMovement::create([
                'branch_snapshot' => [
                    'id' => $branch->id,
                    'legal_name' => $branch->legal_name,
                ],
                'series' => $activeSeries,
                'year' => Carbon::now()->year,
                'detail_type' => 'DETALLADO',
                'consumption' => 'N',
                'payment_type' => 'CONTADO',
                'status' => 'N',
                'sale_type' => 'MINORISTA',
                'currency' => 'PEN',
                'exchange_rate' => 1.000,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'movement_id' => $movement->id,
                'branch_id' => $branchId,
            ]);

            // Crear SalesMovementDetails (sin restar stock porque es borrador; nota por producto en comment)
            foreach ($validated['items'] as $item) {
                $product = Product::with('baseUnit')->findOrFail($item['pId']);
                $productBranch = ProductBranch::with('taxRate')
                    ->where('product_id', $item['pId'])
                    ->where('branch_id', $branchId)
                    ->first();

                if (! $productBranch) {
                    throw new \Exception("Producto {$product->description} no disponible en esta sucursal");
                }

                $unit = $product->baseUnit;
                if (! $unit) {
                    throw new \Exception("El producto {$product->description} no tiene una unidad base configurada");
                }

                $taxRate = $productBranch->taxRate;
                $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $this->getDefaultTaxRateValue();

                $qty = (float) ($item['qty'] ?? 0);
                $courtesyQty = (float) ($item['courtesyQty'] ?? $item['courtesy_quantity'] ?? 0);
                $courtesyQty = max(0, min($courtesyQty, $qty));
                $paidQty = $qty - $courtesyQty;
                // Precio de venta incluye impuesto; el monto es solo por unidades pagadas (sin cortesía).
                $itemTotal = $paidQty * (float) ($item['price'] ?? 0);
                $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
                $itemTax = $itemTotal - $itemSubtotal;

                // Nota por producto (compatibilidad note/comment) y normalización
                $detailNoteRaw = data_get($item, 'note', data_get($item, 'comment'));
                $detailNote = $detailNoteRaw === null ? null : trim((string) $detailNoteRaw);
                $detailNote = ($detailNote !== '') ? $detailNote : null;

                SalesMovementDetail::create([
                    'detail_type' => 'DETAILED',
                    'sales_movement_id' => $salesMovement->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                    ],
                    'unit_id' => $unit->id,
                    'tax_rate_id' => $taxRate?->id,
                    'tax_rate_snapshot' => $taxRate ? [
                        'id' => $taxRate->id,
                        'description' => $taxRate->description,
                        'tax_rate' => $taxRate->tax_rate,
                    ] : null,
                    'quantity' => $item['qty'],
                    'courtesy_quantity' => $courtesyQty,
                    'amount' => $itemTotal,
                    'discount_percentage' => 0.000000,
                    'original_amount' => $itemSubtotal,
                    'comment' => $detailNote,
                    'parent_detail_id' => null,
                    'complements' => [],
                    'status' => 'A',
                    'branch_id' => $branchId,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta guardada como borrador correctamente',
                'data' => [
                    'movement_id' => $movement->id,
                    'number' => $number,
                    'total' => $total,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar borrador de venta: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar borrador: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validateSale($request);

        $user = $request->user();
        $personName = null;
        if (! empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name.' '.$person->last_name) : null;
        }

        Movement::create([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->name ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        return redirect()
            ->route('sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta creada correctamente.');
    }

    public function edit(Movement $sale)
    {
        return view('sales.edit', [
            'sale' => $sale,
        ] + $this->getFormData($sale));
    }

    public function update(Request $request, Movement $sale)
    {
        $data = $this->validateSale($request);

        $personName = null;
        if (! empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            $personName = $person ? ($person->first_name.' '.$person->last_name) : null;
        }

        $sale->update([
            'number' => $data['number'],
            'moved_at' => $data['moved_at'],
            'person_id' => $data['person_id'] ?? null,
            'person_name' => $personName ?? '',
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'],
            'movement_type_id' => $data['movement_type_id'],
            'document_type_id' => $data['document_type_id'],
            'branch_id' => $data['branch_id'],
            'parent_movement_id' => $data['parent_movement_id'] ?? null,
        ]);

        if (isset($data['serie']) && $sale->salesMovement) {
            $sale->salesMovement->update(['series' => $data['serie']]);
        }

        return redirect()
            ->route('sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Venta actualizada correctamente.');
    }

    public function destroy(Movement $sale)
    {
        DB::transaction(function () use ($sale) {
            $linkedOrderMovement = $this->resolveLinkedOrderMovementForSale($sale);

            if ($linkedOrderMovement) {
                $linkedOrderMovement->status = 'PENDIENTE';
                $linkedOrderMovement->finished_at = null;
                $linkedOrderMovement->save();

                $orderBaseMovement = $linkedOrderMovement->movement;
                if ($orderBaseMovement) {
                    $orderBaseMovement->status = 'P';
                    $orderBaseMovement->save();
                }

                $tableId = $linkedOrderMovement->table_id;
                if ($tableId && ! $this->tableHasAnotherPendingOrder($tableId, $linkedOrderMovement->id)) {
                    Table::where('id', $tableId)->update([
                        'situation' => 'ocupada',
                        'opened_at' => now(),
                    ]);
                }
            }

            app(KardexSyncService::class)->deleteMovement((int) $sale->id);
            $sale->delete();
        });

        return redirect()
            ->route('sales.index', request()->filled('view_id') ? ['view_id' => request()->input('view_id')] : [])
            ->with('status', 'Venta eliminada correctamente.');
    }

    private function resolveLinkedOrderMovementForSale(Movement $sale): ?OrderMovement
    {
        $sale->loadMissing(['orderMovement.table', 'movement.orderMovement.table']);

        if ($sale->orderMovement) {
            return $sale->orderMovement;
        }

        if ($sale->movement?->orderMovement) {
            return $sale->movement->orderMovement;
        }

        return null;
    }

    private function tableHasAnotherPendingOrder(int $tableId, int $excludedOrderMovementId): bool
    {
        return OrderMovement::query()
            ->where('table_id', $tableId)
            ->where('id', '!=', $excludedOrderMovementId)
            ->whereIn('status', ['PENDIENTE', 'P'])
            ->exists();
    }

    private function validateSale(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:255'],
            'serie' => ['nullable', 'string', 'max:10'],
            'moved_at' => ['required', 'date'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'comment' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:1'],
            'movement_type_id' => ['required', 'integer', 'exists:movement_types,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_movement_id' => ['nullable', 'integer', 'exists:movements,id'],
        ]);
    }

    private function getFormData(?Movement $sale = null): array
    {
        $branches = Branch::query()->orderBy('legal_name')->get(['id', 'legal_name']);
        $people = Person::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $movementTypes = MovementType::query()->orderBy('description')->get(['id', 'description']);
        $documentTypes = DocumentType::query()->orderBy('name')->get(['id', 'name']);

        return [
            'branches' => $branches,
            'people' => $people,
            'movementTypes' => $movementTypes,
            'documentTypes' => $documentTypes,
        ];
    }

    /** Obtiene la tasa de impuesto por defecto del sistema (valor 0-1, ej: 0.18 para 18%). */
    private function getDefaultTaxRateValue(): float
    {
        $taxRate = TaxRate::where('status', true)->orderBy('order_num')->first();

        return $taxRate ? ((float) $taxRate->tax_rate) / 100 : 0.18;
    }

    private function resolveSalePaymentLabel(Movement $sale): string
    {
        if ($this->saleMovementIsCredit($sale)) {
            return 'Crédito';
        }

        $cashMovement = $sale->cashMovement ?: $this->resolveCashMovementBySaleMovement($sale->id);
        if (! $cashMovement) {
            return 'No especificado';
        }

        $methodName = DB::table('cash_movement_details as cmd')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'cmd.payment_method_id')
            ->where('cmd.cash_movement_id', $cashMovement->id)
            ->where('cmd.status', 'A')
            ->where('cmd.type', '!=', 'DEUDA')
            ->orderBy('cmd.id')
            ->value('pm.description');

        return $methodName ?: 'Mixto';
    }

    /** Pedido restaurante (CREDITO) o venta con payment_type CREDIT/CREDITO. */
    private function saleMovementIsCredit(Movement $sale): bool
    {
        if (($sale->orderMovement?->payment_type ?? null) === 'CREDITO') {
            return true;
        }

        return in_array($sale->salesMovement?->payment_type ?? null, ['CREDIT', 'CREDITO'], true);
    }

    public function printTicket(Request $request, Movement $sale)
    {
        $sale = $this->resolvePrintableForTicket($sale);
        $printData = $this->buildSalePrintData($sale, $request);
        if ($request->boolean('direct_print')) {
            $printData['autoPrint'] = true;

            return view('sales.print.ticket', $printData);
        }

        $printData['autoPrint'] = false;

        $html = view('sales.print.ticket', $printData)->render();
        $pageHeight = $this->estimateSaleTicketHeight($sale);
        $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, null, [
            '--page-width',
            '80mm',
            '--page-height',
            $pageHeight,
            '--margin-top',
            '0',
            '--margin-right',
            '0',
            '--margin-bottom',
            '0',
            '--margin-left',
            '0',
            '--print-media-type',
            '--disable-smart-shrinking',
            '--dpi',
            '203',
        ]);

        if ($pdfBinary === null) {
            $printData['autoPrint'] = true;

            return view('sales.print.ticket', $printData);
        }

        $docName = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)).$this->ticketSeriesForMovement($sale).'-'.$sale->number;

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docName.'-ticket.pdf"',
        ]);
    }

    private function estimateSaleTicketHeight(Movement $sale): string
    {
        $details = $this->ticketDetailsForMovement($sale);
        $lineWidth = 20;
        $detailLines = 0;

        foreach ($details as $detail) {
            $description = trim((string) ($detail->description ?? $detail->product?->description ?? 'Producto'));
            $detailLines += max(1, (int) ceil(mb_strlen($description) / $lineWidth));
        }

        $customerNameLines = max(1, (int) ceil(mb_strlen((string) ($sale->person_name ?? 'CLIENTES VARIOS')) / 24));
        $addressLines = max(1, (int) ceil(mb_strlen($this->resolveTicketAddressDisplay($sale)) / 24));
        $documentLines = max(1, (int) ceil(mb_strlen((string) ($sale->person?->document_number ?? '-')) / 24));
        $paymentLines = max(1, (int) ceil(mb_strlen((string) $this->resolveSalePaymentLabel($sale)) / 24));
        $creditNoteLines = $this->saleMovementIsCredit($sale) ? 1 : 0;
        $notesLines = 0;
        if (filled((string) $sale->comment)) {
            $notesLines = max(1, (int) ceil(mb_strlen((string) $sale->comment) / 26));
        }

        $baseHeight = 106;
        $itemsHeight = $detailLines * 8;
        $metaHeight = ($customerNameLines + $addressLines + $documentLines + $paymentLines + $creditNoteLines) * 4;
        $notesHeight = $notesLines * 5;
        $footerSafety = 18;

        $height = max(120, min(900, $baseHeight + $itemsHeight + $metaHeight + $notesHeight + $footerSafety));

        return $height.'mm';
    }

    /**
     * Envía ticket en texto plano a ticketera en red (puerto típico 9100).
     * Solo permite clientes cuya IP esté en LAN privada (WiFi del local).
     */
    public function printTicketThermalNetwork(Request $request)
    {
        if (! config('local_network.thermal_print_enabled', true)) {
            abort(404);
        }

        // Modo QZ: el cliente (QZ Tray) imprime directamente; el servidor solo devuelve el payload.
        // No requiere red local ni IP en la impresora (admite USB).
        $qzMode = $request->input('mode') === 'qz' || $request->boolean('qz_mode');

        if (! $qzMode && ! LocalNetworkClient::isOnLocalNetwork($request)) {
            return response()->json([
                'success' => false,
                'message' => 'La impresión por red desde el servidor solo está permitida dentro de la red del local. Si el sistema está en un VPS, usa QZ Tray en el equipo del establecimiento o conecta el VPS a la LAN/VPN del local.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'movement_id' => ['required', 'integer', 'exists:movements,id'],
                'printer_id' => ['nullable', 'integer', 'exists:printers_branch,id'],
                'ticket_text' => ['nullable', 'string'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos no válidos',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Sin sucursal en sesión.'], 422);
        }

        $movement = Movement::query()
            ->where('id', $validated['movement_id'])
            ->where('branch_id', $branchId)
            ->first();

        if (! $movement) {
            return response()->json(['success' => false, 'message' => 'Venta no encontrada en esta sucursal.'], 404);
        }

        $movement = $this->resolvePrintableForTicket($movement);

        $printer = null;

        if (! empty($validated['printer_id'])) {
            $printer = PrinterBranch::query()
                ->where('id', $validated['printer_id'])
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->first();
        }

        if (! $printer) {
            $printer = $this->resolveBranchThermalPrinter($branchId);
        }

        $ticketText = $validated['ticket_text'] ?? null;
        $plain = $ticketText !== null
            ? (string) $ticketText
            : $this->buildThermalTicketPlainTextApproved($movement, $request, $printer);
        $payload = $this->wrapEscPosPlainPayload($plain);

        // Modo QZ: mismo ticket maquetado que la vista/PDF manual (wkhtmltopdf); fallback RAW si no hay PDF.
        if ($qzMode) {
            $paperWidthMm = (int) ($printer?->width ?? 80);
            if (! in_array($paperWidthMm, [58, 80], true)) {
                $paperWidthMm = 80;
            }

            $printData = $this->buildSalePrintData($movement, $request);
            $printData['autoPrint'] = false;
            $printData['ticketPageWidthMm'] = $paperWidthMm;

            $html = view('sales.print.ticket', $printData)->render();
            $pageHeight = $this->estimateSaleTicketHeight($movement);
            $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, null, [
                '--page-width',
                $paperWidthMm.'mm',
                '--page-height',
                $pageHeight,
                '--margin-top',
                '0',
                '--margin-right',
                '0',
                '--margin-bottom',
                '0',
                '--margin-left',
                '0',
                '--print-media-type',
                '--disable-smart-shrinking',
                '--dpi',
                '203',
            ]);

            $response = [
                'success' => true,
                'payload_b64' => base64_encode($payload),
                'printer_name' => $printer?->name ?? null,
                'paper_width' => $paperWidthMm,
            ];

            if ($pdfBinary !== null && $pdfBinary !== '') {
                $response['ticket_pdf_b64'] = base64_encode($pdfBinary);
                $response['qz_print_format'] = 'pdf';
            }

            return response()->json($response);
        }

        if (! $printer) {
            return response()->json([
                'success' => false,
                'message' => 'No hay ticketera activa configurada para esta sucursal.',
            ], 422);
        }

        $printerService = app(ThermalNetworkPrintService::class);
        $hasNetworkIp = filled((string) $printer->ip);

        try {
            if ($hasNetworkIp) {
                $printerService->sendRaw(
                    (string) $printer->ip,
                    (int) config('local_network.thermal_port', 9100),
                    $payload,
                    (int) config('local_network.thermal_timeout_seconds', 4)
                );

                return response()->json(['success' => true, 'message' => 'Ticket enviado a la ticketera de red.']);
            }

            if (! config('local_network.thermal_windows_local_enabled', true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La ticketera seleccionada no tiene IP y la impresión USB local está deshabilitada.',
                ], 422);
            }

            $printerService->sendRawToWindowsPrinter(
                (string) $printer->name,
                $payload,
                (int) config('local_network.thermal_timeout_seconds', 4) + 4
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket enviado a la ticketera USB local.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impresión térmica: '.$e->getMessage());
            $safeMessage = $this->normalizeUtf8ForJson((string) $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? ($safeMessage !== '' ? $safeMessage : 'Error interno al imprimir ticket.')
                    : 'No se pudo enviar el ticket a la ticketera. Verifica IP o nombre de impresora local en Windows.',
            ], 500);
        }
    }

    private function resolveBranchThermalPrinter(int $branchId): ?PrinterBranch
    {
        $printerBaseQuery = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->where('status', 'E');

        $host = strtolower(trim(request()->getHost() ?: ''));
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
        $printerName = $isLocalhost ? 'barra' : 'barra2';

        $printer = (clone $printerBaseQuery)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$printerName])
            ->first();
        if ($printer) {
            return $printer;
        }

        $printer = (clone $printerBaseQuery)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $printerName . '%'])
            ->first();
        if ($printer) {
            return $printer;
        }

        $printer = (clone $printerBaseQuery)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->orderBy('id')
            ->first();
        if ($printer) {
            return $printer;
        }

        return (clone $printerBaseQuery)->orderBy('id')->first();
    }

    private function normalizeUtf8ForJson(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        $encoded = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        if (! is_string($encoded) || $encoded === '') {
            $encoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        }

        $normalized = is_string($encoded) && $encoded !== '' ? $encoded : $value;
        return preg_replace('/[^\P{C}\r\n\t]/u', '', $normalized) ?? $normalized;
    }

    private function resolveWkhtmltopdfBinary(): ?string
    {
        $candidates = array_filter([
            env('SNAPPY_PDF_BINARY'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function renderPdfWithWkhtmltopdf(string $html, ?string $pageSize = 'A4', array $extraArgs = []): ?string
    {
        $binary = $this->resolveWkhtmltopdfBinary();
        if (! $binary) {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $htmlFile = tempnam($tmpDir, 'sale_html_');
        $pdfFile = tempnam($tmpDir, 'sale_pdf_');

        if ($htmlFile === false || $pdfFile === false) {
            return null;
        }

        $htmlPath = $htmlFile.'.html';
        $pdfPath = $pdfFile.'.pdf';
        @rename($htmlFile, $htmlPath);
        @rename($pdfFile, $pdfPath);

        file_put_contents($htmlPath, $html);

        $args = array_merge([
            $binary,
            '--enable-local-file-access',
            '--disable-javascript',
            '--load-error-handling',
            'ignore',
            '--load-media-error-handling',
            'ignore',
            '--encoding',
            'utf-8',
            '--margin-top',
            '10',
            '--margin-right',
            '10',
            '--margin-bottom',
            '10',
            '--margin-left',
            '10',
        ], $extraArgs);

        if (! empty($pageSize)) {
            $args[] = '--page-size';
            $args[] = $pageSize;
        }

        $args = array_merge($args, [
            $htmlPath,
            $pdfPath,
        ]);

        $process = new Process($args);

        try {
            $process->setTimeout(120);
            $process->run();
            $pdfExists = file_exists($pdfPath) && filesize($pdfPath) > 0;
            if (! $pdfExists) {
                Log::warning('wkhtmltopdf fallo al generar PDF', [
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);

                return null;
            }

            $content = file_get_contents($pdfPath);

            return $content === false ? null : $content;
        } catch (\Throwable $e) {
            Log::warning('Error ejecutando wkhtmltopdf: '.$e->getMessage());

            return null;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    private function buildSalePrintData(Movement $sale, Request $request): array
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.unit',
            'salesMovement.details.product',
            'salesMovement.details.taxRate',
            'orderMovement.details.unit',
            'orderMovement.details.product',
            'orderMovement.area',
            'orderMovement.table.area',
        ]);

        $userBranchId = (int) (auth()->user()?->person?->branch_id ?? 0);
        $sessionBranchId = (int) session('branch_id');
        $userBranch = $userBranchId ? Branch::find($userBranchId) : null;
        $sessionBranch = $sessionBranchId ? Branch::find($sessionBranchId) : null;
        $branchForLogo = $userBranch ?: $sessionBranch ?: $sale->branch;

        $logoUrl = null;
        $logoFileUrl = null;
        if ($branchForLogo?->logo) {
            $rawLogo = trim((string) $branchForLogo->logo);

            if (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://')) {
                $logoUrl = $rawLogo;
            } else {
                $publicLogoPath = '/'.ltrim($rawLogo, '/');
                if (! str_starts_with($publicLogoPath, '/storage/')) {
                    $publicLogoPath = '/storage/'.ltrim($publicLogoPath, '/');
                }

                $logoUrl = asset(ltrim($publicLogoPath, '/'));

                $storageRelativePath = preg_replace('#^/storage/#', '', $publicLogoPath) ?: '';
                $localLogoPath = storage_path('app/public/'.ltrim($storageRelativePath, '/'));
                if (file_exists($localLogoPath)) {
                    $normalized = str_replace('\\', '/', $localLogoPath);
                    $logoFileUrl = 'file:///'.ltrim($normalized, '/');
                }
            }
        }

        $details = $this->ticketDetailsForMovement($sale);
        if ($details->isEmpty()) {
            abort(404, 'Comprobante sin detalle.');
        }

        return [
            'sale' => $sale,
            'details' => $details,
            'branchForLogo' => $branchForLogo,
            'logoUrl' => $logoUrl,
            'logoFileUrl' => $logoFileUrl,
            'printedAt' => now(),
            'paymentLabel' => $this->resolveSalePaymentLabel($sale),
            'ticketAddressDisplay' => $this->resolveTicketAddressDisplay($sale),
            'qrPayload' => $this->buildSaleQrPayload($sale, $branchForLogo),
            'qrImageUrl' => $this->buildSaleQrImageUrl($sale, $branchForLogo),
            'viewId' => $request->input('view_id'),
        ];
    }

    private function resolveTicketAddressDisplay(Movement $sale): string
    {
        $documentName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');
        $isTicketSale = str_contains($documentName, 'ticket');
        if (! $isTicketSale) {
            $clientAddress = trim((string) ($sale->person?->address ?? ''));
            return $clientAddress !== '' ? $clientAddress : '-';
        }

        $orderMovement = $sale->orderMovement;
        if (! $orderMovement) {
            return '';
        }

        $areaName = trim((string) (
            $orderMovement->area?->name
            ?? $orderMovement->table?->area?->name
            ?? ''
        ));
        $tableName = trim((string) ($orderMovement->table?->name ?? ''));

        if ($areaName !== '' && $tableName !== '') {
            return $areaName.' - mesa '.$tableName;
        }
        if ($tableName !== '') {
            return 'mesa '.$tableName;
        }

        return $areaName;
    }

    private function buildSaleQrPayload(Movement $sale, ?Branch $branchForLogo): ?string
    {
        $docName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');
        $documentCode = null;

        if (str_contains($docName, 'factura')) {
            $documentCode = '01';
        } elseif (str_contains($docName, 'boleta')) {
            $documentCode = '03';
        }

        if (! $documentCode) {
            return null;
        }

        $series = (string) ($sale->electronic_invoice_series ?: $this->ticketSeriesForMovement($sale));
        $correlative = (string) ($sale->number ?? '');
        $tax = number_format((float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0), 2, '.', '');
        $total = number_format((float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0), 2, '.', '');
        $issueDate = optional($sale->moved_at)->format('Y-m-d') ?? now()->format('Y-m-d');
        $customerDocument = trim((string) ($sale->person?->document_number ?? ''));
        $customerDocument = ($customerDocument === '' || $customerDocument === '-') ? '0' : $customerDocument;
        $customerDocumentType = strlen($customerDocument) === 11 ? '6' : (strlen($customerDocument) === 8 ? '1' : '0');
        $ruc = trim((string) ($branchForLogo?->ruc ?? ''));
        if ($ruc === '') {
            $ruc = '0';
        }

        return implode('|', [
            $ruc,
            $documentCode,
            $series,
            $correlative,
            $tax,
            $total,
            $issueDate,
            $customerDocumentType,
            $customerDocument,
        ]);
    }

    private function buildSaleQrImageUrl(Movement $sale, ?Branch $branchForLogo): ?string
    {
        $payload = $this->buildSaleQrPayload($sale, $branchForLogo);
        if (! $payload) {
            return null;
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size=170x170&margin=0&data='.rawurlencode($payload);
    }

    /**
     * Venta POS (salesMovement) o pedido cobrado (orderMovement).
     */
    private function resolvePrintableForTicket(Movement $sale): Movement
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.unit',
            'salesMovement.details.product',
            'salesMovement.details.taxRate',
            'orderMovement.details.unit',
            'orderMovement.details.product',
        ]);

        if (! $sale->salesMovement && ! $sale->orderMovement) {
            abort(404, 'Comprobante no encontrado.');
        }

        $branchId = (int) session('branch_id');
        if ($branchId && (int) $sale->branch_id !== $branchId) {
            abort(403);
        }

        return $sale;
    }

    private function ticketSeriesForMovement(Movement $sale): string
    {
        $electronicSeries = trim((string) ($sale->electronic_invoice_series ?? ''));
        if ($electronicSeries !== '') {
            if (preg_match('/^[A-Z]+(\d+)$/i', $electronicSeries, $matches) === 1) {
                return $matches[1];
            }

            return $electronicSeries;
        }

        return $sale->salesMovement?->series ?? '001';
    }

    private function syncElectronicInvoiceForSale(Movement $movement, ApisunatService $apisunatService): array
    {
        $movement->loadMissing(['documentType', 'branch', 'salesMovement', 'orderMovement']);

        if (! $apisunatService->isEligibleDocument($movement)) {
            return [
                'status' => 'SKIPPED',
                'message' => 'El documento no requiere envío electrónico.',
            ];
        }

        if (! $apisunatService->isConfiguredForBranch($movement->branch)) {
            return [
                'status' => 'SKIPPED',
                'message' => 'La sucursal no tiene Apisunat configurado.',
            ];
        }

        try {
            $result = $apisunatService->emitSale($movement);
            if (($result['status'] ?? null) === 'SENT') {
                $data = $result['data'] ?? [];
                $movement->forceFill([
                    'number' => $data['correlative'] ?? $movement->number,
                    'electronic_invoice_provider' => $data['provider'] ?? 'apisunat',
                    'electronic_invoice_status' => 'SENT',
                    'electronic_invoice_external_id' => $data['external_id'] ?? null,
                    'electronic_invoice_series' => $data['series'] ?? null,
                    'electronic_invoice_number' => $data['full_number'] ?? null,
                    'electronic_invoice_file_name' => $data['file_name'] ?? null,
                    'electronic_invoice_pdf_ticket_url' => $data['pdf_ticket_80mm'] ?? null,
                    'electronic_invoice_pdf_a4_url' => $data['pdf_a4'] ?? null,
                    'electronic_invoice_xml_url' => $data['xml_url'] ?? null,
                    'electronic_invoice_cdr_url' => $data['cdr_url'] ?? null,
                    'electronic_invoice_response' => $data['response'] ?? null,
                ])->save();
                $movement->refresh();
            }

            return $result;
        } catch (\Throwable $e) {
            $movement->forceFill([
                'electronic_invoice_provider' => 'apisunat',
                'electronic_invoice_status' => 'ERROR',
                'electronic_invoice_response' => [
                    'message' => $e->getMessage(),
                ],
            ])->save();

            return [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function resolveElectronicDocumentUrl(Movement $sale, string $type, ApisunatService $apisunatService): ?string
    {
        if ($type === 'xml' && $sale->electronic_invoice_xml_url) {
            return $sale->electronic_invoice_xml_url;
        }
        if ($type === 'cdr' && $sale->electronic_invoice_cdr_url) {
            return $sale->electronic_invoice_cdr_url;
        }

        if (! $sale->electronic_invoice_external_id) {
            return null;
        }

        try {
            $payload = $apisunatService->getDocumentById((string) $sale->electronic_invoice_external_id, $sale->branch);
            $urls = $apisunatService->extractDocumentUrls($payload);

            $sale->forceFill([
                'electronic_invoice_xml_url' => $sale->electronic_invoice_xml_url ?: ($urls['xml_url'] ?? null),
                'electronic_invoice_cdr_url' => $sale->electronic_invoice_cdr_url ?: ($urls['cdr_url'] ?? null),
                'electronic_invoice_response' => $payload,
            ])->save();
            $sale->refresh();

            return $type === 'xml'
                ? $sale->electronic_invoice_xml_url
                : $sale->electronic_invoice_cdr_url;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function ticketDetailsForMovement(Movement $sale)
    {
        if ($sale->salesMovement) {
            return $sale->salesMovement->details->sortBy('id')->values();
        }

        if ($sale->orderMovement) {
            return $sale->orderMovement->details()
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'C');
                })
                ->orderBy('id')
                ->get();
        }

        return collect();
    }

    public function printPdf(Request $request, Movement $sale)
    {
        $sale = $this->resolvePrintableForTicket($sale);
        $printData = $this->buildSalePrintData($sale, $request);
        $printData['autoPrint'] = false;

        $html = view('sales.print.pdf', $printData)->render();
        $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, 'A4');

        if ($pdfBinary === null) {
            return view('sales.print.pdf', $printData);
        }

        $docName = strtoupper(substr($sale->documentType?->name ?? 'C', 0, 1)).$this->ticketSeriesForMovement($sale).'-'.$sale->number;

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docName.'.pdf"',
        ]);
    }

    public function lookupFiscalDocument(Request $request, ApisunatService $apisunatService)
    {
        $validated = $request->validate([
            'doc' => ['required', 'string'],
        ]);

        $branchId = (int) session('branch_id');
        $branch = $branchId ? Branch::find($branchId) : null;

        try {
            $data = $apisunatService->consultDocument($branch, preg_replace('/\D+/', '', (string) $validated['doc']) ?: '');

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function redirectElectronicPdfA4(Request $request, Movement $sale)
    {
        $sale = $this->resolvePrintableForTicket($sale);
        if (! $sale->electronic_invoice_pdf_a4_url) {
            abort(404, 'El comprobante no tiene PDF A4 electrónico.');
        }

        return redirect()->away($sale->electronic_invoice_pdf_a4_url);
    }

    public function redirectElectronicXml(Request $request, Movement $sale, ApisunatService $apisunatService)
    {
        $sale = $this->resolvePrintableForTicket($sale);
        $url = $this->resolveElectronicDocumentUrl($sale, 'xml', $apisunatService);

        if (! $url) {
            abort(404, 'El comprobante no tiene XML disponible.');
        }

        return redirect()->away($url);
    }

    public function redirectElectronicCdr(Request $request, Movement $sale, ApisunatService $apisunatService)
    {
        $sale = $this->resolvePrintableForTicket($sale);
        $url = $this->resolveElectronicDocumentUrl($sale, 'cdr', $apisunatService);

        if (! $url) {
            abort(404, 'El comprobante no tiene CDR disponible.');
        }

        return redirect()->away($url);
    }

    public function convertTicketToElectronic(Request $request, Movement $sale, ApisunatService $apisunatService)
    {
        $sale = $this->resolvePrintableForTicket($sale);

        $validated = $request->validate([
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
        ]);

        $sale->loadMissing(['documentType', 'branch', 'salesMovement', 'person']);

        if (! $sale->salesMovement) {
            return back()->with('error', 'Solo se pueden convertir ventas reales.');
        }

        $currentDocumentName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');
        if (! str_contains($currentDocumentName, 'ticket')) {
            return back()->with('error', 'Solo los tickets de venta pueden convertirse.');
        }

        $targetDocumentType = DocumentType::query()
            ->where('movement_type_id', 2)
            ->findOrFail((int) $validated['document_type_id']);

        $targetDocumentName = mb_strtolower(trim((string) ($targetDocumentType->name ?? '')), 'UTF-8');
        if (! str_contains($targetDocumentName, 'boleta') && ! str_contains($targetDocumentName, 'factura')) {
            return back()->with('error', 'Solo se puede convertir a boleta o factura.');
        }

        $selectedPerson = null;
        if (! empty($validated['person_id'])) {
            $selectedPerson = Person::query()
                ->where('id', (int) $validated['person_id'])
                ->where('branch_id', $sale->branch_id)
                ->whereNull('deleted_at')
                ->first();

            if (! $selectedPerson) {
                return back()->with('error', 'El cliente seleccionado no pertenece a esta sucursal.');
            }
        }

        if (str_contains($targetDocumentName, 'factura')) {
            $document = preg_replace('/\D+/', '', (string) ($selectedPerson?->document_number ?? ''));
            if (! $this->isValidRuc($document)) {
                return back()->with('error', 'La factura requiere un cliente con RUC válido.');
            }
        }

        if (str_contains($targetDocumentName, 'boleta') && (float) ($sale->total ?? 0) > 700) {
            $document = preg_replace('/\D+/', '', (string) ($selectedPerson?->document_number ?? ''));
            if (! $this->isValidIdentityDocumentForBoleta($document)) {
                return back()->with('error', 'Para boletas mayores a S/ 700 debes seleccionar un cliente con documento válido.');
            }
        }

        try {
            DB::beginTransaction();

            $sale->forceFill([
                'document_type_id' => (int) $targetDocumentType->id,
                'person_id' => $selectedPerson?->id,
                'person_name' => $selectedPerson
                    ? trim(($selectedPerson->first_name ?? '') . ' ' . ($selectedPerson->last_name ?? ''))
                    : 'CLIENTES VARIOS',
                'electronic_invoice_provider' => null,
                'electronic_invoice_status' => null,
                'electronic_invoice_external_id' => null,
                'electronic_invoice_series' => null,
                'electronic_invoice_number' => null,
                'electronic_invoice_file_name' => null,
                'electronic_invoice_pdf_ticket_url' => null,
                'electronic_invoice_pdf_a4_url' => null,
                'electronic_invoice_xml_url' => null,
                'electronic_invoice_cdr_url' => null,
                'electronic_invoice_response' => null,
            ])->save();

            $sale->unsetRelation('documentType');
            $sale->unsetRelation('person');
            $sale->unsetRelation('branch');
            $sale->unsetRelation('salesMovement');
            $sale->refresh();

            $result = $this->syncElectronicInvoiceForSale($sale, $apisunatService);
            if (($result['status'] ?? null) !== 'SENT') {
                DB::rollBack();

                return redirect()
                    ->route('sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
                    ->with('error', $result['message'] ?? 'No se pudo emitir el comprobante electrónico.');
            }

            $sale->refresh();
            if ($sale->salesMovement && ! empty($sale->electronic_invoice_series)) {
                $seriesDigits = preg_replace('/^[A-Z]+/i', '', (string) $sale->electronic_invoice_series) ?: $sale->salesMovement->series;
                $sale->salesMovement->update([
                    'series' => $seriesDigits,
                ]);
            }

            DB::commit();

            $indexParams = ['thermal_reprint' => $sale->id];
            if ($request->filled('view_id')) {
                $indexParams['view_id'] = $request->input('view_id');
            }

            return redirect()
                ->route('sales.index', $indexParams)
                ->with('status', 'Ticket convertido y enviado correctamente a Apisunat.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('sales.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
                ->with('error', 'No se pudo convertir el ticket: '.$e->getMessage());
        }
    }

    /**
     * Calcula subtotal, IGV y total desde los ítems usando la tasa de impuesto de cada producto.
     * Usa la tasa configurada en ProductBranch->TaxRate; si no tiene, usa la tasa por defecto del sistema.
     */
    private function calculateSubtotalAndTaxFromItems(array $items, int $branchId): array
    {
        $defaultTaxPct = $this->getDefaultTaxRateValue();
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $productBranch = ProductBranch::with('taxRate')
                ->where('product_id', $item['pId'])
                ->where('branch_id', $branchId)
                ->first();

            $taxRate = $productBranch?->taxRate;
            $taxRateValue = $taxRate ? ($taxRate->tax_rate / 100) : $defaultTaxPct;

            $qty = (float) ($item['qty'] ?? 0);
            $courtesyQty = (float) ($item['courtesyQty'] ?? $item['courtesy_quantity'] ?? 0);
            $courtesyQty = max(0, min($courtesyQty, $qty));
            $paidQty = $qty - $courtesyQty;
            $itemTotal = $paidQty * (float) ($item['price'] ?? 0);
            $itemSubtotal = $taxRateValue > 0 ? ($itemTotal / (1 + $taxRateValue)) : $itemTotal;
            $itemTax = $itemTotal - $itemSubtotal;

            $subtotal += $itemSubtotal;
            $tax += $itemTax;
            $total += $itemTotal;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    public function exportPdf(Request $request)
    {
        $branchId = session('branch_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $cashRegisterId = effective_cash_register_id($branchId ? (int) $branchId : null);
        $personId = $request->input('person_id');
        $saleType = $request->input('sale_type');
        $cashShiftRelationId = $request->input('cash_shift_relation_id');

        $branch = $branchId ? Branch::with('company')->find($branchId) : null;
        $companyName = $branch?->company?->legal_name;
        $branchName = $branch?->legal_name;

        $query = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement'])
            ->where('movement_type_id', 2)
            ->where('branch_id', $branchId)
            ->whereHas('salesMovement');

        // Aplicación de filtros
        if ($documentTypeId && is_numeric($documentTypeId)) {
            $query->where('document_type_id', (int) $documentTypeId);
        }
        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('person_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }
        if ($dateFrom) {
            $query->where('moved_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo) {
            $query->where('moved_at', '<=', $dateTo.' 23:59:59');
        }
        if ($paymentMethodId) {
            $query->whereExists(function ($sub) use ($paymentMethodId) {
                $sub->select(DB::raw(1))
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                    ->whereColumn('m.parent_movement_id', 'movements.id')
                    ->where('cmd.payment_method_id', $paymentMethodId)
                    ->whereNull('cm.deleted_at')
                    ->whereNull('cmd.deleted_at');
            });
        }
        if ($cashRegisterId) {
            $query->whereExists(function ($sub) use ($cashRegisterId) {
                $sub->select(DB::raw(1))
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->whereColumn('m.parent_movement_id', 'movements.id')
                    ->where('cm.cash_register_id', $cashRegisterId)
                    ->whereNull('cm.deleted_at');
            });
        }
        if ($saleType) {
            $query->whereHas('salesMovement', function ($sub) use ($saleType) {
                $sub->where('detail_type', $saleType);
            });
        }

        // Filtro por turno (CashShiftRelation): ventana temporal por movimientos
        $effectiveCR = $cashRegisterId;
        if ($cashShiftRelationId !== null && $cashShiftRelationId !== '' && $branchId && $effectiveCR) {
            $csrApplied = CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', (int) $cashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCR) {
                    $q->where('cash_register_id', $effectiveCR);
                })
                ->first();

            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $query->where('movements.id', '>=', $startMid);
                if ($csrApplied->cashMovementEnd) {
                    $query->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $sales = $query->orderBy('moved_at', 'desc')->get();

        $filters = [];
        $filters['Desde'] = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : null;
        $filters['Hasta'] = $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : null;
        if ($search) {
            $filters['Búsqueda'] = $search;
        }
        if ($documentTypeId) {
            $dt = DocumentType::find($documentTypeId);
            $filters['Tipo de documento'] = $dt ? $dt->name : "ID {$documentTypeId}";
        }
        if ($paymentMethodId) {
            $pm = PaymentMethod::find($paymentMethodId);
            $filters['Método de pago'] = $pm ? ($pm->description ?? $pm->id) : "ID {$paymentMethodId}";
        }
        if ($saleType) {
            $filters['Tipo de venta'] = $saleType;
        }
        if ($cashShiftRelationId && isset($csrApplied) && $csrApplied) {
            $shiftLabel = ($csrApplied->cashMovementStart?->shift?->name ?? 'Turno') . ' (' . $csrApplied->id . ')';
            $filters['Turno'] = $shiftLabel;
        }
        if ($cashRegisterId) {
            $cr = CashRegister::find($cashRegisterId);
            $filters['Caja'] = $cr ? ($cr->number ?? $cr->id) : "ID {$cashRegisterId}";
        }

        try {
            $pdf = PDF::loadView('sales.pdfs.pdf_report', compact(
                'sales',
                'dateFrom',
                'dateTo',
                'filters',
                'companyName',
                'branchName'
            ));

            $pdf->setPaper('a4')
                ->setOption('margin-bottom', 10)
                ->setOption('encoding', 'utf-8')
                ->setOption('enable-local-file-access', true);

            // Obtenemos el contenido binario del PDF
            $output = $pdf->output();

            // Devolvemos la respuesta forzando los headers
            return response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Reporte_Ventas.pdf"',
                'Content-Length' => strlen($output),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al exportar PDF de ventas', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo generar el PDF de ventas.');
        }
    }

    public function exportExcel(Request $request)
    {
        $branchId = session('branch_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $cashRegisterId = effective_cash_register_id($branchId ? (int) $branchId : null);
        $saleType = $request->input('sale_type');
        $cashShiftRelationId = $request->input('cash_shift_relation_id');

        $query = Movement::query()
            ->with(['branch', 'person', 'movementType', 'documentType', 'salesMovement'])
            ->where('movement_type_id', 2)
            ->where('branch_id', $branchId)
            ->whereHas('salesMovement');

        if ($documentTypeId && is_numeric($documentTypeId)) {
            $query->where('document_type_id', (int) $documentTypeId);
        }
        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('number', 'like', "%{$search}%")
                    ->orWhere('person_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }
        if ($dateFrom) {
            $query->where('moved_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo) {
            $query->where('moved_at', '<=', $dateTo.' 23:59:59');
        }
        if ($paymentMethodId) {
            $query->whereExists(function ($sub) use ($paymentMethodId) {
                $sub->select(DB::raw(1))
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                    ->whereColumn('m.parent_movement_id', 'movements.id')
                    ->where('cmd.payment_method_id', $paymentMethodId)
                    ->whereNull('cm.deleted_at')
                    ->whereNull('cmd.deleted_at');
            });
        }
        if ($cashRegisterId) {
            $query->whereExists(function ($sub) use ($cashRegisterId) {
                $sub->select(DB::raw(1))
                    ->from('movements as m')
                    ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                    ->whereColumn('m.parent_movement_id', 'movements.id')
                    ->where('cm.cash_register_id', $cashRegisterId)
                    ->whereNull('cm.deleted_at');
            });
        }
        if ($saleType) {
            $query->whereHas('salesMovement', function ($sub) use ($saleType) {
                $sub->where('detail_type', $saleType);
            });
        }

        $effectiveCR = $cashRegisterId;
        if ($cashShiftRelationId !== null && $cashShiftRelationId !== '' && $branchId && $effectiveCR) {
            $csrApplied = CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', (int) $cashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCR) {
                    $q->where('cash_register_id', $effectiveCR);
                })
                ->first();

            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $query->where('movements.id', '>=', $startMid);
                if ($csrApplied->cashMovementEnd) {
                    $query->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $sales = $query->orderBy('moved_at', 'desc')->get();
        $timestamp = now()->format('Ymd_His');

        return Excel::download(
            new SalesExport($sales),
            "reporte_ventas_{$timestamp}.xlsx"
        );
    }

    /**
     * Genera numero de venta en formato correlativo simple: 00000127.
     * Mantiene compatibilidad leyendo tambien numeros historicos con formato antiguo.
     */
    private function generateSaleNumber(int $documentTypeId, int $cashRegisterId, bool $reserve = true): string
    {
        $documentType = DocumentType::find($documentTypeId);
        if (! $documentType) {
            throw new \Exception('Tipo de documento no encontrado.');
        }

        $cashRegister = CashRegister::find($cashRegisterId);
        if (! $cashRegister) {
            throw new \Exception('Caja no encontrada.');
        }

        $branchId = (int) session('branch_id');
        if (! $branchId) {
            throw new \Exception('No se encontro sucursal en sesion.');
        }

        $year = (int) now()->year;

        $query = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year);

        if ($reserve) {
            $query->lockForUpdate();
        }

        $lastCorrelative = 0;
        $numbers = $query->pluck('number');

        foreach ($numbers as $number) {
            $raw = trim((string) $number);
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $raw) === 1) {
                $value = (int) $raw;
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }

                continue;
            }

            if (preg_match('/(\d+)-\d{4}$/', $raw, $matches) === 1) {
                $value = (int) $matches[1];
                if ($value > $lastCorrelative) {
                    $lastCorrelative = $value;
                }
            }
        }

        $nextCorrelative = $lastCorrelative + 1;

        return str_pad((string) $nextCorrelative, 8, '0', STR_PAD_LEFT);
    }

    private function resolveDocumentAbbreviation(string $documentName): string
    {
        $name = strtolower(trim($documentName));

        if (str_contains($name, 'boleta')) {
            return 'B';
        }
        if (str_contains($name, 'factura')) {
            return 'F';
        }
        if (str_contains($name, 'ticket')) {
            return 'T';
        }
        if (str_contains($name, 'nota')) {
            return 'N';
        }

        $firstLetter = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $documentName) ?: 'X', 0, 1));

        return $firstLetter !== '' ? $firstLetter : 'X';
    }

    private function resolveActiveCashRegisterId(int $branchId): int
    {
        $cashRegisterId = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->orderBy('id')
            ->value('id');

        if (! $cashRegisterId) {
            $cashRegisterId = CashRegister::query()
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->value('id');
        }

        if (! $cashRegisterId) {
            throw new \Exception('No hay caja activa/disponible para generar el numero de venta.');
        }

        return (int) $cashRegisterId;
    }

    private function resolveCashMovementBySaleMovement(int $saleMovementId): ?CashMovements
    {
        $cashMovement = CashMovements::where('movement_id', $saleMovementId)->first();
        if ($cashMovement) {
            return $cashMovement;
        }

        $cashEntryMovementId = Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->value('id');

        return $cashEntryMovementId ? CashMovements::where('movement_id', $cashEntryMovementId)->first() : null;
    }

    private function resolveCashEntryMovementBySaleMovement(int $saleMovementId): ?Movement
    {
        return Movement::query()
            ->where('parent_movement_id', $saleMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveCashMovementTypeId(): int
    {
        $movementTypeId = MovementType::query()
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%caja%');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cash%', 'or');
            })
            ->orderBy('id')
            ->value('id');

        if (! $movementTypeId) {
            $movementTypeId = MovementType::find(4)?->id;
        }

        if (! $movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }

        if (! $movementTypeId) {
            throw new \Exception('No se encontro tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
    }

    private function buildThermalTicketPlainText(Movement $sale, Request $request, ?PrinterBranch $printer = null): string
    {
        $printData = $this->buildSalePrintData($sale, $request);
        $sale = $printData['sale'];
        $details = $printData['details'];
        $branch = $printData['branchForLogo'];
        $paymentLabel = $printData['paymentLabel'];
        $ticketAddressDisplay = (string) ($printData['ticketAddressDisplay'] ?? '');

        // Ancho dinámico según impresora configurada (80mm → 48 chars, 58mm → 32 chars)
        $printerWidthMm = (int) ($printer?->width ?? 58);
        $w = $printerWidthMm >= 80 ? 48 : 32;
        $sep = str_repeat('=', $w);
        $lines = [];

        $lines[] = $this->thermalPadCenter(strtoupper(Str::ascii($branch->legal_name ?? 'SUCURSAL')), $w);
        $lines[] = $this->thermalPadCenter('RUC: '.Str::ascii($branch->ruc ?? '-'), $w);
        $branchAddrPlain = trim((string) ($branch->address ?? ''));
        if ($branchAddrPlain !== '') {
            $addrAscii = Str::ascii($branchAddrPlain);
            foreach (preg_split('/\r\n|\r|\n/', wordwrap($addrAscii, max(10, $w - 2), "\n", true)) ?: [] as $addrLine) {
                $t = trim((string) $addrLine);
                if ($t !== '') {
                    $lines[] = $this->thermalPadCenter($t, $w);
                }
            }
        }
        $lines[] = $this->thermalPadCenter(strtoupper(Str::ascii($sale->documentType?->name ?? 'TICKET')), $w);
        $docCode = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1))
            .$this->ticketSeriesForMovement($sale).'-'.$sale->number;
        $lines[] = $this->thermalPadCenter(Str::ascii($docCode), $w);
        $lines[] = $sep;
        $lines[] = 'Fecha: '.optional($sale->moved_at)->format('d/m/Y H:i');
        $lines[] = 'Cliente: '.Str::ascii($sale->person_name ?? 'CLIENTES VARIOS');
        $lines[] = 'Dir. cliente: '.Str::ascii($ticketAddressDisplay);
        $lines[] = 'RUC/DNI: '.Str::ascii($sale->person?->document_number ?? '-');
        $lines[] = 'Forma pago: '.Str::ascii($paymentLabel);
        if ($this->saleMovementIsCredit($sale)) {
            $lines[] = Str::ascii('Venta a credito: saldo pendiente de cobro.');
        }
        $lines[] = $sep;
        $lines[] = 'Prod.         Cant P.Unit  Subt';

        foreach ($details as $detail) {
            $qty = (float) $detail->quantity;
            $lineTotal = (float) $detail->amount;
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0.0;
            $desc = Str::ascii(Str::limit($detail->description ?? $detail->product?->description ?? '-', 30));
            $lines[] = $desc;
            $lines[] = '  '
                .$this->thermalPadStart(number_format($qty, 2, '.', ''), 6)
                .' x '
                .$this->thermalPadStart(number_format($unitPrice, 2, '.', ''), 7)
                .' '
                .$this->thermalPadStart(number_format($lineTotal, 2, '.', ''), 8);
        }

        $docSubtotal = (float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0);
        $docTax = (float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0);
        $docTotal = (float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0);

        $lines[] = $sep;
        $lines[] = $this->thermalPadEnd('Op. gravada:', 20)
            .$this->thermalPadStart('S/'.number_format($docSubtotal, 2, '.', ''), 12);
        $lines[] = $this->thermalPadEnd('IGV:', 20)
            .$this->thermalPadStart('S/'.number_format($docTax, 2, '.', ''), 12);
        $lines[] = $this->thermalPadEnd('TOTAL:', 20)
            .$this->thermalPadStart('S/'.number_format($docTotal, 2, '.', ''), 12);

        if ($sale->comment) {
            $lines[] = $sep;
            $lines[] = 'Notas: '.Str::ascii(Str::limit((string) $sale->comment, 120));
        }

        $lines[] = $sep;
        $lines[] = $this->thermalPadCenter('Gracias por su preferencia', $w);
        $lines[] = 'Impreso: '.now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }

    private function buildThermalTicketPlainTextApproved(Movement $sale, Request $request, ?PrinterBranch $printer = null): string
    {
        $printData = $this->buildSalePrintData($sale, $request);
        $sale = $printData['sale'];
        $details = $printData['details'];
        $branch = $printData['branchForLogo'];
        $paymentLabel = $printData['paymentLabel'];
        $ticketAddressDisplay = (string) ($printData['ticketAddressDisplay'] ?? '');

        $printerWidthMm = (int) ($printer?->width ?? 58);
        $lineWidth = $printerWidthMm >= 80 ? 48 : 32;
        $colQty = $printerWidthMm >= 80 ? 5 : 4;
        $colPrice = $printerWidthMm >= 80 ? 9 : 7;
        $colAmount = $printerWidthMm >= 80 ? 9 : 7;
        $colGap = 2;
        $colName = max(8, $lineWidth - $colQty - $colGap - $colPrice - $colAmount);
        $sep = str_repeat('=', $lineWidth);

        $wrapText = function (string $text, int $length): array {
            $clean = trim(Str::ascii($text));
            if ($clean === '') {
                return ['-'];
            }

            $words = preg_split('/\s+/', $clean) ?: [];
            $wrapped = [];
            $current = '';

            foreach ($words as $word) {
                $candidate = $current !== '' ? $current.' '.$word : $word;
                if (strlen($candidate) <= $length) {
                    $current = $candidate;
                    continue;
                }
                if ($current !== '') {
                    $wrapped[] = $current;
                }
                $current = strlen($word) > $length ? substr($word, 0, $length) : $word;
            }

            if ($current !== '') {
                $wrapped[] = $current;
            }

            return $wrapped ?: ['-'];
        };

        $formatQty = function (float $qty): string {
            return floor($qty) === $qty ? (string) (int) $qty : number_format($qty, 2, '.', '');
        };

        $docSubtotal = (float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0);
        $docTax = (float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0);
        $docTotal = (float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0);
        $docCode = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)).$this->ticketSeriesForMovement($sale).'-'.$sale->number;

        $lines = [];
        $lines[] = $this->thermalPadCenter(strtoupper(Str::ascii($branch->legal_name ?? 'SUCURSAL')), $lineWidth);
        $lines[] = $this->thermalPadCenter('RUC: '.Str::ascii($branch->ruc ?? '-'), $lineWidth);
        $branchAddrPlainAppr = trim((string) ($branch->address ?? ''));
        if ($branchAddrPlainAppr !== '') {
            $addrAsciiAppr = Str::ascii($branchAddrPlainAppr);
            foreach (preg_split('/\r\n|\r|\n/', wordwrap($addrAsciiAppr, max(10, $lineWidth - 2), "\n", true)) ?: [] as $addrLineAppr) {
                $tAppr = trim((string) $addrLineAppr);
                if ($tAppr !== '') {
                    $lines[] = $this->thermalPadCenter($tAppr, $lineWidth);
                }
            }
        }
        $lines[] = $this->thermalPadCenter(strtoupper(Str::ascii($sale->documentType?->name ?? 'TICKET')), $lineWidth);
        $lines[] = $this->thermalPadCenter(Str::ascii($docCode), $lineWidth);
        $lines[] = $sep;
        $lines[] = 'Fecha: '.optional($sale->moved_at)->format('d/m/Y H:i');
        $lines[] = 'Cliente: '.Str::ascii($sale->person_name ?? 'CLIENTES VARIOS');
        $lines[] = 'Dir. cliente: '.Str::ascii($ticketAddressDisplay);
        $lines[] = 'RUC/DNI: '.Str::ascii($sale->person?->document_number ?? '-');
        $lines[] = 'Forma pago: '.Str::ascii($paymentLabel);
        if ($this->saleMovementIsCredit($sale)) {
            $lines[] = Str::ascii('Venta a credito: saldo pendiente de cobro.');
        }
        $lines[] = $sep;
        $lines[] = $this->thermalPadEnd('Cant.', $colQty)
            .str_repeat(' ', $colGap)
            .$this->thermalPadEnd('Descr.', $colName)
            .$this->thermalPadStart('P.Unit.', $colPrice)
            .$this->thermalPadStart('Subt.', $colAmount);
        $lines[] = $sep;

        $detailCount = $details->count();
        foreach ($details as $index => $detail) {
            $qty = (float) $detail->quantity;
            $lineTotal = (float) $detail->amount;
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0.0;
            $descLines = $wrapText((string) ($detail->description ?? $detail->product?->description ?? '-'), $colName);

            $lines[] = $this->thermalPadEnd($formatQty($qty), $colQty)
                .str_repeat(' ', $colGap)
                .$this->thermalPadEnd($descLines[0], $colName)
                .$this->thermalPadStart(number_format($unitPrice, 2, '.', ''), $colPrice)
                .$this->thermalPadStart(number_format($lineTotal, 2, '.', ''), $colAmount);

            for ($i = 1; $i < count($descLines); $i++) {
                $lines[] = $this->thermalPadEnd('', $colQty)
                    .str_repeat(' ', $colGap)
                    .$this->thermalPadEnd($descLines[$i], $colName)
                    .$this->thermalPadStart('', $colPrice)
                    .$this->thermalPadStart('', $colAmount);
            }

            if ($index < ($detailCount - 1)) {
                $lines[] = $sep;
            }
        }

        $lines[] = $sep;
        $lines[] = $this->thermalPadEnd('Subtotal', $lineWidth - 12)
            .$this->thermalPadStart(number_format($docSubtotal, 2, '.', ''), 12);
        $lines[] = $this->thermalPadEnd('IGV', $lineWidth - 12)
            .$this->thermalPadStart(number_format($docTax, 2, '.', ''), 12);
        $lines[] = $this->thermalPadEnd('TOTAL', $lineWidth - 12)
            .$this->thermalPadStart(number_format($docTotal, 2, '.', ''), 12);

        if ($sale->comment) {
            $lines[] = 'Notas: '.Str::ascii(Str::limit((string) $sale->comment, 120));
        }

        $lines[] = '';
        $lines[] = 'Impreso: '.now()->format('d/m/Y H:i:s');
        $lines[] = $this->thermalPadCenter('Gracias por su preferencia', $lineWidth);

        return implode("\n", $lines);
    }

    private function wrapEscPosPlainPayload(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return "\x1B\x40".$text."\n\n\n\x1D\x56\x00";
    }

    private function thermalPadCenter(string $s, int $len): string
    {
        $s = Str::ascii($s);
        $sl = strlen($s);
        if ($sl >= $len) {
            return substr($s, 0, $len);
        }
        $pad = $len - $sl;
        $l = intdiv($pad, 2);
        $r = $pad - $l;

        return str_repeat(' ', $l).$s.str_repeat(' ', $r);
    }

    private function thermalPadEnd(string $s, int $len): string
    {
        $s = Str::ascii($s);
        $sl = strlen($s);

        return $sl >= $len ? substr($s, 0, $len) : $s.str_repeat(' ', $len - $sl);
    }

    private function thermalPadStart(string $s, int $len): string
    {
        $s = Str::ascii($s);
        $sl = strlen($s);

        return $sl >= $len ? substr($s, -$len) : str_repeat(' ', $len - $sl).$s;
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $lastRecord = Movement::query()
            ->select('movements.number')
            ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
            ->where('movements.branch_id', $branchId)
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->when($paymentConceptId !== null, function ($query) use ($paymentConceptId) {
                $query->where('cash_movements.payment_concept_id', $paymentConceptId);
            })
            ->lockForUpdate()
            ->orderByDesc('movements.number')
            ->first();

        $lastNumber = $lastRecord?->number;
        $nextSequence = $lastNumber ? ((int) $lastNumber + 1) : 1;

        return str_pad((string) $nextSequence, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Clientes de la sucursal (misma lógica que OrderController::resolveClientPeople).
     */
    protected function branchClientsForBranch(?int $branchId): \Illuminate\Support\Collection
    {
        $q = Person::query()
            ->select('people.id', 'people.first_name', 'people.last_name', 'people.document_number')
            ->join('role_person', 'role_person.person_id', '=', 'people.id')
            ->join('roles', 'roles.id', '=', 'role_person.role_id')
            ->whereRaw("LOWER(TRIM(roles.name)) = 'cliente'")
            ->whereNull('role_person.deleted_at')
            ->orderBy('people.first_name')
            ->orderBy('people.last_name');

        if ($branchId) {
            $q->where('role_person.branch_id', $branchId);
        }

        return $q->distinct()->get();
    }

    private function isValidRuc(?string $document): bool
    {
        $doc = preg_replace('/\D+/', '', (string) $document);
        return (bool) preg_match('/^\d{11}$/', $doc) && $doc !== '00000000000';
    }

    private function isValidDni(?string $document): bool
    {
        $doc = preg_replace('/\D+/', '', (string) $document);
        return (bool) preg_match('/^\d{8}$/', $doc) && $doc !== '00000000';
    }

    private function isValidIdentityDocumentForBoleta(?string $document): bool
    {
        return $this->isValidDni($document) || $this->isValidRuc($document);
    }

    private function validateCustomerForDocumentType(DocumentType $documentType, ?Person $person, float $total): ?string
    {
        $docName = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');
        $document = preg_replace('/\D+/', '', (string) ($person?->document_number ?? ''));

        if (str_contains($docName, 'factura')) {
            if (! $this->isValidRuc($document)) {
                return 'La factura requiere un cliente con RUC válido.';
            }
        }

        if (str_contains($docName, 'boleta') && $total > 700) {
            if (! $this->isValidIdentityDocumentForBoleta($document)) {
                return 'Para boletas mayores a S/ 700 debes seleccionar un cliente con documento válido.';
            }
        }

        return null;
    }
}
