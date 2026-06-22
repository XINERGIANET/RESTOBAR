<?php

namespace App\Http\Controllers;

use App\Models\PurchaseMovement;
use App\Models\Movement;
use App\Models\PurchaseMovementDetail;
use App\Models\Person;
use App\Models\Branch;
use App\Models\ProductBranch;
use App\Models\Category;
use App\Models\DocumentType;
use App\Models\Unit;
use App\Models\Product;
use App\Models\WarehouseMovement;
use App\Models\WarehouseMovementDetail;
use App\Models\CashMovements;
use App\Models\CashMovementDetail;
use App\Models\CashShiftRelation;
use App\Models\PaymentConcept;
use App\Models\Shift;
use App\Models\CashRegister;
use App\Models\Card;
use App\Models\Bank;
use App\Models\DigitalWallet;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Operation;
use App\Models\Role;
use App\Services\KardexSyncService;
use App\Support\InsensitiveSearch;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $cashRegisterId = $request->input('cash_register_id');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

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

        $query = PurchaseMovement::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId, fn ($q) => $q->whereRaw('1 = 0'));

        if ($search) {
            $query->whereHas('movement', function ($q) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($q, 'number', $search);
                InsensitiveSearch::whereInsensitiveLike($q, 'person_name', $search, 'or');
            });
        }
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        if ($documentTypeId) {
            $query->whereHas('movement', function ($q) use ($documentTypeId) {
                $q->where('document_type_id', $documentTypeId);
            });
        }
        if ($cashRegisterId) {
            $query->whereExists(function ($sub) use ($cashRegisterId) {
                $sub->select(DB::raw(1))
                    ->from('cash_movements as cm')
                    ->whereColumn('cm.movement_id', 'purchase_movements.movement_id')
                    ->where('cm.cash_register_id', $cashRegisterId)
                    ->whereNull('cm.deleted_at');
            });
        }
        if ($paymentMethodId) {
            $query->whereExists(function ($sub) use ($paymentMethodId) {
                $sub->select(DB::raw(1))
                    ->from('cash_movements as cm')
                    ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                    ->whereColumn('cm.movement_id', 'purchase_movements.movement_id')
                    ->where('cmd.payment_method_id', $paymentMethodId)
                    ->whereNull('cm.deleted_at')
                    ->whereNull('cmd.deleted_at');
            });
        }

        $purchases = $query->with(['movement', 'details'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentMethods = PaymentMethod::query()
            ->whereNull('deleted_at')
            ->orderBy('description')
            ->get(['id', 'description']);

        $cashRegisters = CashRegister::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->orderBy('number')
            ->get(['id', 'number']);

        $startOfMonth = Carbon::now()->startOfMonth();
        $metricsQuery = PurchaseMovement::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId, fn ($q) => $q->whereRaw('1 = 0'));
        $metrics = [
            'total_month'    => (clone $metricsQuery)->where('created_at', '>=', $startOfMonth)->sum('total'),
            'igv_month'      => (clone $metricsQuery)->where('created_at', '>=', $startOfMonth)->sum('igv'),
            'count_invoices' => (clone $metricsQuery)->where('serie', 'LIKE', 'F%')->count(),
            'count_tickets'  => (clone $metricsQuery)->where('serie', 'LIKE', 'B%')->count(),
            'total_docs'     => (clone $metricsQuery)->count(),
        ];

        return view('purchases.index', compact(
            'purchases',
            'search',
            'dateFrom',
            'dateTo',
            'metrics',
            'operaciones',
            'perPage',
            'viewId',
            'documentTypeId',
            'paymentMethodId',
            'cashRegisterId',
            'documentTypes',
            'paymentMethods',
            'cashRegisters'
        ));
    }
    
    public function create(Request $request)
    {
        $branchId = $request->session()->get('branch_id');
        $branch = Branch::find($branchId);
        $companyId = $branch?->company_id;

        $branches = Branch::where('company_id', $companyId)->get();
        
        $people = Person::whereHas('roles', function ($query) use ($branchId) {
            $query->where('roles.id', 4) 
                  ->where('role_person.branch_id', $branchId); 
        })
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->get();

        $documentTypes = DocumentType::where('movement_type_id', 1)->get();
        $units = Unit::all();
        $defaultTaxRate = 18.00;
        $purchase = null; 

        $categories = Category::query()
            ->when($branchId, fn($q) => $q->forBranchMenu($branchId, 'COMPRAS'), function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->orderBy('description')
            ->get();
        
        $validCategoryIds = $categories->pluck('id');

        $products = ProductBranch::where('branch_id', $branchId)
            ->with(['product', 'product.baseUnit', 'product.category', 'product.productType'])
            ->get()
            ->filter(function ($pb) use ($validCategoryIds) {
                $product = $pb->product;
                if (!$product) return false;

                // Solo si la categoría está permitida para Compras
                if (!in_array($product->category_id, $validCategoryIds->all())) {
                    return false;
                }

                $pt = $product->productType;
                if (!$pt) return false;
                return in_array($pt->behavior, ['SUPPLY', 'BOTH'], true);
            })
            ->map(function ($pb) {
                $product = $pb->product;
                if (!$product) return null;
                $imgPath = $product->image ?? null;
                $baseUnit = $product->baseUnit;
                return (object) [
                    'id' => $pb->product_id,
                    'code' => $product->code ?? '',
                    'description' => $product->description ?? '',
                    'name' => $product->description ?? '',
                    'unit_sale' => $product->base_unit_id ?? 0,
                    'unit_id' => $product->base_unit_id ?? 0,
                    'unit_name' => $baseUnit ? ($baseUnit->description ?? $baseUnit->name ?? '') : '',
                    'price' => $pb->price ?? 0,
                    'cost' => $pb->purchase_price ?? 0,
                    'stock' => (float) ($pb->stock ?? 0),
                    'category_id' => $pb->product->category_id ?? null,
                    'category' => $pb->product->category ? $pb->product->category->description : 'General',
                    'image' => $imgPath,
                    'image_url' => $imgPath ? asset('storage/' . $imgPath) : null,
                ];
            })
            ->filter()
            ->values();

        // Cargamos los datos de pago para los selectores del formulario
        $cards = Card::all();
        $paymentGateways = PaymentGateways::all();
        $digitalWallets = DigitalWallet::all();
        $banks = Bank::all();
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num', 'asc')
            ->get(['id', 'description']);

        $viewId = $request->input('view_id');

        $departments = Location::query()->where('type', 'department')->orderBy('name')->get(['id', 'name']);
        $provinces   = Location::query()->where('type', 'province')->orderBy('name')->get(['id', 'name', 'parent_location_id']);
        $districts   = Location::query()->where('type', 'district')->orderBy('name')->get(['id', 'name', 'parent_location_id']);

        return view('purchases._form', compact(
            'people', 'documentTypes', 'units', 'products', 'defaultTaxRate',
            'purchase', 'branches', 'branchId', 'cards', 'paymentGateways', 'digitalWallets', 'banks',
            'categories', 'viewId', 'paymentMethods', 'departments', 'provinces', 'districts'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'moved_at' => ['required', 'date'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'series' => ['required', 'string', 'max:50'],
            'number' => ['nullable', 'string', 'max:50'],
            'tipo_detalle' => ['nullable', 'string', 'in:DETALLADO,GLOSA'],
            'includes_tax' => ['required', 'string', 'in:S,N'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_type' => ['required', 'string', 'in:CONTADO,CREDITO'],
            'currency' => ['required', 'string'],
            'exchange_rate' => ['required', 'numeric', 'min:0.001'],
            'affects_cash' => ['required', 'string', 'in:S,N'],
            'affects_kardex' => ['required', 'string', 'in:S,N'],
            'comment' => ['nullable', 'string'],
            
            // Validar items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.comment' => ['nullable', 'string'],

            // Validar pagos
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'integer'],
            'payments.*.payment_method' => ['nullable', 'string'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.number' => ['nullable', 'string'],
            'payments.*.card_id' => ['nullable', 'integer'],
            'payments.*.bank_id' => ['nullable', 'integer'],
            'payments.*.digital_wallet_id' => ['nullable', 'integer'],
            'payments.*.payment_gateway_id' => ['nullable', 'integer'],
            
            'payment_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
        ]);

        $branchId = (int) $data['branch_id'];

        // CONTADO: requiere al menos un método de pago y la suma debe coincidir con el total
        if ($data['payment_type'] === 'CONTADO') {
            $payments = $data['payments'] ?? [];
            if (empty($payments) || !is_array($payments)) {
                return back()->withErrors(['payments' => 'Para pago al contado debe registrar al menos un método de pago.'])->withInput();
            }
            $lineTotal = collect($data['items'])->reduce(fn ($c, $i) => $c + ($i['quantity'] * $i['amount']), 0);
            $taxRate = $data['tax_rate_percent'] / 100;
            $total = $data['includes_tax'] === 'S'
                ? $lineTotal
                : $lineTotal * (1 + $taxRate);
            $totalPaid = collect($payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if (abs($totalPaid - $total) > 0.02) {
                return back()->withErrors(['payments' => 'La suma de los pagos (S/ ' . number_format($totalPaid, 2) . ') no coincide con el total a pagar (S/ ' . number_format($total, 2) . ').'])->withInput();
            }
        }

        // Validar que los productos existan en la sucursal
        $productIds = collect($data['items'])->pluck('product_id')->unique();
        $validProductIds = ProductBranch::where('branch_id', $branchId)->pluck('product_id');
        $invalidIds = $productIds->diff($validProductIds);
        if ($invalidIds->isNotEmpty()) {
            return back()->withErrors(['items' => 'Uno o más productos no están registrados en la sucursal seleccionada.'])->withInput();
        }

        $cashRegisterId = session('cash_register_id');
        $activeShiftRelation = null;

        if ($data['payment_type'] === 'CONTADO' && $data['affects_cash'] === 'S') {
            if (!$cashRegisterId) {
                return back()->withErrors(['error' => 'Por favor, seleccione una caja en la barra superior antes de registrar una compra que afecta caja.'])->withInput();
            }

            // Buscamos el turno activo de la caja seleccionada
            $activeShiftRelation = CashShiftRelation::with('cashMovementStart')
                ->where('branch_id', session('branch_id'))
                ->where('status', '1')
                ->whereNull('ended_at')
                ->whereNull('cash_movement_end_id')
                ->whereHas('cashMovementStart', function ($q) use ($cashRegisterId) {
                    $q->where('cash_register_id', $cashRegisterId);
                })
                ->latest('id')
                ->first();

            if (!$activeShiftRelation) {
                return back()->withErrors(['error' => 'No hay un turno de caja activo para la caja seleccionada. Realice una Apertura de Caja primero.'])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($data, $request, $activeShiftRelation, $cashRegisterId) {
                $branchId = $data['branch_id']; 
                $person = Person::findOrFail($data['person_id']);
                
                // Recalcular totales
                $lineTotal = collect($data['items'])->reduce(function ($carry, $item) {
                    return $carry + ($item['quantity'] * $item['amount']);
                }, 0);

                $taxRate = $data['tax_rate_percent'] / 100;
                
                if ($data['includes_tax'] === 'S') {
                    $subtotal = $taxRate > 0 ? ($lineTotal / (1 + $taxRate)) : $lineTotal;
                    $tax = $lineTotal - $subtotal;
                    $total = $lineTotal;
                } else {
                    $subtotal = $lineTotal;
                    $tax = $subtotal * $taxRate;
                    $total = $subtotal + $tax;
                }

                // Crear Movement Genérico
                $movement = Movement::create([
                    'number' => $data['series'] . '-' . Carbon::parse($data['moved_at'])->format('Y'),
                    'moved_at' => Carbon::parse($data['moved_at']),
                    'user_id' => $request->user()?->id,
                    'user_name' => $request->user()?->name ?? '',
                    'responsible_id' => $request->user()?->id ?? session('user_id'),
                    'responsible_name' => $request->user()?->name ?? session('user_name') ?? '',
                    'person_id' => $person->id, 
                    'person_name' => trim($person->first_name . ' ' . $person->last_name),
                    'comment' => $data['comment'] ?? '',
                    'status' => 'E',
                    'movement_type_id' => 1, 
                    'document_type_id' => $data['document_type_id'],
                    'branch_id' => $branchId,
                ]);

                // Crear PurchaseMovement
                $purchase = PurchaseMovement::create([
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                    'json_persona' => $person->toJson(),
                    'serie' => $data['series'], 
                    'anio' => Carbon::parse($data['moved_at'])->format('Y'),
                    'tipo_detalle' => $data['tipo_detalle'] ?? 'DETALLADO',
                    'incluye_igv' => $data['includes_tax'],
                    'tipo_pago' => $data['payment_type'],
                    'afecta_caja' => $data['affects_cash'],
                    'moneda' => $data['currency'],
                    'tipocambio' => $data['exchange_rate'],
                    'subtotal' => round($subtotal, 2),
                    'igv' => round($tax, 2),
                    'total' => round($total, 2),
                    'afecta_kardex' => $data['affects_kardex'],
                ]);

                if ($request->hasFile('payment_image')) {
                    $path = $request->file('payment_image')->store('payment_images', 'public');
                    $purchase->update(['payment_image' => $path]);
                }

                // ==========================================
                // LOGICA PARA KARDEX: CABECERA DE ALMACEN
                // ==========================================
                $warehouseMovementObj = null;

                if ($data['affects_kardex'] === 'S') {
                    // Buscamos un tipo de documento que el Kardex identifique como Entrada (Ej: "Nota de Entrada")
                    $docEntrada = DocumentType::query()
                        ->tap(fn ($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%entrada%'))
                        ->first();
                    $docEntradaId = $docEntrada ? $docEntrada->id : $data['document_type_id'];

                    // Creamos un Movement gemelo para el Kardex, usando el prefijo 'E-' para que sea tomado como Ingreso
                    $whCoreMovement = Movement::create([
                        'number' => 'E-COMPRA-' . $data['series'] . '-' . Carbon::parse($data['moved_at'])->format('His'),
                        'moved_at' => Carbon::parse($data['moved_at']),
                        'user_id' => $request->user()?->id,
                        'user_name' => $request->user()?->name ?? '',
                        'responsible_id' => $request->user()?->id ?? session('user_id'),
                        'responsible_name' => $request->user()?->name ?? session('user_name') ?? '',
                        'person_id' => $person->id,
                        'person_name' => trim($person->first_name . ' ' . $person->last_name),
                        'comment' => 'Ingreso automático por compra: ' . ($data['comment'] ?? $data['series']),
                        'status' => 'E',
                        'movement_type_id' => 1, 
                        'document_type_id' => $docEntradaId,
                        'branch_id' => $branchId,
                    ]);

                    $warehouseMovementObj = WarehouseMovement::create([
                        'movement_id' => $whCoreMovement->id,
                        'branch_id' => $branchId,
                    ]);
                }

                // Crear los Detalles de la compra y Almacén
                foreach ($data['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $unit = Unit::find($item['unit_id']);

                    PurchaseMovementDetail::create([
                        'purchase_movement_id' => $purchase->id,
                        'branch_id' => $branchId,
                        'tipo_detalle' => $data['tipo_detalle'] ?? 'DETALLADO',
                        'producto_id' => $item['product_id'],
                        'codigo' => $product ? $product->code : null,
                        'descripcion' => $item['description'],
                        'json_producto' => $product ? $product->toJson() : null,
                        'unidad_id' => $item['unit_id'],
                        'json_unidad' => $unit ? $unit->toJson() : null,
                        'cantidad' => $item['quantity'],
                        'monto' => $item['amount'],
                        'comentario' => $item['comment'] ?? '',
                        'situacion' => 'E',
                    ]);

                    // ==========================================
                    // LOGICA PARA KARDEX: DETALLE Y STOCK
                    // ==========================================
                    if ($data['affects_kardex'] === 'S' && $warehouseMovementObj) {
                        
                        // 1. Guardar detalle de almacén
                        WarehouseMovementDetail::create([
                            'warehouse_movement_id' => $warehouseMovementObj->id,
                            'branch_id' => $branchId,
                            'product_id' => $item['product_id'],
                            'unit_id' => $item['unit_id'],
                            'quantity' => $item['quantity'],
                            'product_snapshot' => $product ? $product->toJson() : '{}',
                            'comment' => $item['comment'] ?? '',
                        ]);
                        $productBranch = ProductBranch::firstOrCreate(
                            ['product_id' => $item['product_id'], 'branch_id' => $branchId],
                            ['stock' => 0]
                        );
                        
                        $productBranch->increment('stock', $item['quantity']);
                    }
                }

                // REGISTRAR LOS PAGOS EN LA CAJA (SOLO SI AFECTA CAJA)
                if ($data['payment_type'] === 'CONTADO' && $data['affects_cash'] === 'S' && !empty($data['payments'])) {
                    
                    $paymentConcept = PaymentConcept::query()
                        ->tap(fn ($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'description', '%compra%'))
                        ->first();
                    $conceptId = $paymentConcept ? $paymentConcept->id : 1; 

                    $shiftId = null;
                    $shiftSnapshotJson = null;
                    $box = CashRegister::find($cashRegisterId);
                    $cashRegisterName = $box ? $box->number : 'Caja Desconocida';

                    if ($activeShiftRelation) {
                        $shift = Shift::find($activeShiftRelation->cashMovementStart->shift_id ?? null);
                        if ($shift) {
                            $shiftId = $shift->id;
                            $shiftSnapshotJson = json_encode([
                                'name'       => $shift->name,
                                'start_time' => $shift->start_time,
                                'end_time'   => $shift->end_time
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }

                    $totalAmountPaid = collect($data['payments'])->sum('amount');

                    $cashMovement = CashMovements::create([
                        'payment_concept_id' => $conceptId,
                        'currency'           => $data['currency'],
                        'exchange_rate'      => $data['exchange_rate'],
                        'total'              => $totalAmountPaid,
                        'cash_register_id'   => $cashRegisterId, 
                        'cash_register'      => $cashRegisterName,
                        'shift_id'           => $shiftId,
                        'shift_snapshot'     => $shiftSnapshotJson,
                        'movement_id'        => $movement->id,
                        'branch_id'          => $branchId,
                    ]);

                    foreach ($data['payments'] as $paymentData) {
                        $cardName = !empty($paymentData['card_id']) ? Card::find($paymentData['card_id'])?->description : null;
                        $bankName = !empty($paymentData['bank_id']) ? Bank::find($paymentData['bank_id'])?->description : null;
                        $walletName = !empty($paymentData['digital_wallet_id']) ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description : null;
                        $gatewayName = !empty($paymentData['payment_gateway_id']) ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description : null;

                        $individualComment = ($paymentData['payment_method_id'] != 1 && !empty($paymentData['number']))
                            ? 'Ref: ' . $paymentData['number']
                            : ($data['comment'] ?? 'Pago de compra');

                        CashMovementDetail::create([
                            'cash_movement_id'   => $cashMovement->id,
                            'branch_id'          => $branchId,
                            'type'               => 'EGRESO', 
                            'status'             => 'A',
                            'paid_at'            => now(),
                            'amount'             => $paymentData['amount'],
                            'payment_method_id'  => $paymentData['payment_method_id'],
                            'payment_method'     => $paymentData['payment_method'] ?? 'Desconocido',
                            'comment'            => $individualComment,
                            'number'             => $paymentData['number'] ?? null,
                            'card_id'            => $paymentData['card_id'] ?? null,
                            'card'               => $cardName,
                            'bank_id'            => $paymentData['bank_id'] ?? null,
                            'bank'               => $bankName,
                            'digital_wallet_id'  => $paymentData['digital_wallet_id'] ?? null,
                            'digital_wallet'     => $walletName,
                            'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                            'payment_gateway'    => $gatewayName,
                        ]);
                    }
                }

                app(KardexSyncService::class)->syncMovement($movement);
            });

            return redirect()->route('purchase.index')->with('status', 'Compra registrada correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al guardar la compra: ' . $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, PurchaseMovement $purchaseMovement)
    {
        $data = $request->validate([
            'moved_at' => ['required', 'date'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'series' => ['required', 'string', 'max:50'],
            'number' => ['nullable', 'string', 'max:50'],
            'tipo_detalle' => ['nullable', 'string', 'in:DETALLADO,GLOSA'],
            'includes_tax' => ['required', 'string', 'in:S,N'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_type' => ['required', 'string', 'in:CONTADO,CREDITO'],
            'currency' => ['required', 'string'],
            'exchange_rate' => ['required', 'numeric', 'min:0.001'],
            'affects_cash' => ['required', 'string', 'in:S,N'],
            'affects_kardex' => ['required', 'string', 'in:S,N'],
            'comment' => ['nullable', 'string'],
            
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.comment' => ['nullable', 'string'],

            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'integer'],
            'payments.*.payment_method' => ['nullable', 'string'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.number' => ['nullable', 'string'],
            'payments.*.card_id' => ['nullable', 'integer'],
            'payments.*.bank_id' => ['nullable', 'integer'],
            'payments.*.digital_wallet_id' => ['nullable', 'integer'],
            'payments.*.payment_gateway_id' => ['nullable', 'integer'],
        ]);

        $branchId = (int) $data['branch_id'];

        if ($data['payment_type'] === 'CONTADO') {
            $payments = $data['payments'] ?? [];
            if (empty($payments) || !is_array($payments)) {
                return back()->withErrors(['payments' => 'Para pago al contado debe registrar al menos un método de pago.'])->withInput();
            }
            $lineTotal = collect($data['items'])->reduce(fn ($c, $i) => $c + ($i['quantity'] * $i['amount']), 0);
            $taxRate = $data['tax_rate_percent'] / 100;
            $total = $data['includes_tax'] === 'S' ? $lineTotal : $lineTotal * (1 + $taxRate);
            $totalPaid = collect($payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if (abs($totalPaid - $total) > 0.02) {
                return back()->withErrors(['payments' => 'La suma de los pagos no coincide con el total a pagar.'])->withInput();
            }
        }

        $invalidIds = collect($data['items'])->pluck('product_id')->unique()->diff(
            ProductBranch::where('branch_id', $branchId)->pluck('product_id')
        );
        if ($invalidIds->isNotEmpty()) {
            return back()->withErrors(['items' => 'Uno o más productos no están registrados en la sucursal seleccionada.'])->withInput();
        }

        $cashRegisterId = session('cash_register_id');
        $activeShiftRelation = null;

        if ($data['payment_type'] === 'CONTADO' && $data['affects_cash'] === 'S') {
            if (!$cashRegisterId) {
                return back()->withErrors(['error' => 'Por favor, seleccione una caja en la barra superior.'])->withInput();
            }

            $activeShiftRelation = CashShiftRelation::with('cashMovementStart')
                ->where('branch_id', session('branch_id'))
                ->where('status', '1')
                ->whereNull('ended_at')
                ->whereNull('cash_movement_end_id')
                ->whereHas('cashMovementStart', function ($q) use ($cashRegisterId) {
                    $q->where('cash_register_id', $cashRegisterId);
                })
                ->latest('id')
                ->first();

            if (!$activeShiftRelation) {
                return back()->withErrors(['error' => 'No hay un turno de caja activo para la caja seleccionada.'])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($data, $purchaseMovement, $request, $activeShiftRelation, $cashRegisterId) {
                $branchId = $data['branch_id'];
                $person = Person::findOrFail($data['person_id']);
                
                $lineTotal = collect($data['items'])->reduce(function ($carry, $item) {
                    return $carry + ($item['quantity'] * $item['amount']);
                }, 0);

                $taxRate = $data['tax_rate_percent'] / 100;
                
                if ($data['includes_tax'] === 'S') {
                    $subtotal = $taxRate > 0 ? ($lineTotal / (1 + $taxRate)) : $lineTotal;
                    $tax = $lineTotal - $subtotal;
                    $total = $lineTotal;
                } else {
                    $subtotal = $lineTotal;
                    $tax = $subtotal * $taxRate;
                    $total = $subtotal + $tax;
                }

                if ($purchaseMovement->movement) {
                    $purchaseMovement->movement->update([
                        'number' => $data['series'] . '-' . Carbon::parse($data['moved_at'])->format('Y'),
                        'moved_at' => Carbon::parse($data['moved_at']),
                        'person_id' => $person->id,
                        'person_name' => trim($person->first_name . ' ' . $person->last_name),
                        'document_type_id' => $data['document_type_id'],
                        'comment' => $data['comment'] ?? '',
                        'branch_id' => $branchId,
                    ]);
                }

                $purchaseMovement->update([
                    'branch_id' => $branchId,
                    'json_persona' => $person->toJson(),
                    'serie' => $data['series'],
                    'anio' => Carbon::parse($data['moved_at'])->format('Y'),
                    'tipo_detalle' => $data['tipo_detalle'] ?? 'DETALLADO',
                    'incluye_igv' => $data['includes_tax'],
                    'tipo_pago' => $data['payment_type'],
                    'moneda' => $data['currency'],
                    'tipocambio' => $data['exchange_rate'],
                    'afecta_caja' => $data['affects_cash'],
                    'afecta_kardex' => $data['affects_kardex'],
                    'subtotal' => round($subtotal, 2),
                    'igv' => round($tax, 2),
                    'total' => round($total, 2),
                ]);

                if ($request->hasFile('payment_image')) {
                    $path = $request->file('payment_image')->store('payment_images', 'public');
                    $purchaseMovement->update(['payment_image' => $path]);
                }

                $purchaseMovement->details()->delete();

                foreach ($data['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $unit = Unit::find($item['unit_id']);

                    PurchaseMovementDetail::create([
                        'purchase_movement_id' => $purchaseMovement->id,
                        'branch_id' => $branchId,
                        'tipo_detalle' => $data['tipo_detalle'] ?? 'DETALLADO',
                        'producto_id' => $item['product_id'],
                        'codigo' => $product ? $product->code : null,
                        'descripcion' => $item['description'],
                        'json_producto' => $product ? $product->toJson() : null,
                        'unidad_id' => $item['unit_id'],
                        'json_unidad' => $unit ? $unit->toJson() : null,
                        'cantidad' => $item['quantity'],
                        'monto' => $item['amount'],
                        'comentario' => $item['comment'] ?? '',
                        'situacion' => 'E',
                    ]);
                }

                // Actualizar caja: Borramos el registro viejo
                if ($purchaseMovement->movement) {
                    $oldCashMovement = CashMovements::where('movement_id', $purchaseMovement->movement->id)->first();
                    if ($oldCashMovement) {
                        CashMovementDetail::where('cash_movement_id', $oldCashMovement->id)->delete();
                        $oldCashMovement->delete();
                    }
                }

                // Y creamos el nuevo si corresponde
                if ($data['payment_type'] === 'CONTADO' && $data['affects_cash'] === 'S' && !empty($data['payments'])) {
                    $paymentConcept = PaymentConcept::query()
                        ->tap(fn ($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'description', '%compra%'))
                        ->first();
                    $conceptId = $paymentConcept ? $paymentConcept->id : 1; 

                    $shiftId = null;
                    $shiftSnapshotJson = null;
                    $box = CashRegister::find($cashRegisterId);
                    $cashRegisterName = $box ? $box->number : 'Caja Desconocida';

                    if ($activeShiftRelation) {
                        $shift = Shift::find($activeShiftRelation->cashMovementStart->shift_id ?? null);
                        if ($shift) {
                            $shiftId = $shift->id;
                            $shiftSnapshotJson = json_encode([
                                'name'       => $shift->name,
                                'start_time' => $shift->start_time,
                                'end_time'   => $shift->end_time
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }

                    $cashMovement = CashMovements::create([
                        'payment_concept_id' => $conceptId,
                        'currency'           => $data['currency'],
                        'exchange_rate'      => $data['exchange_rate'],
                        'total'              => collect($data['payments'])->sum('amount'),
                        'cash_register_id'   => $cashRegisterId,
                        'cash_register'      => $cashRegisterName,
                        'shift_id'           => $shiftId,
                        'shift_snapshot'     => $shiftSnapshotJson,
                        'movement_id'        => $purchaseMovement->movement->id,
                        'branch_id'          => $branchId,
                    ]);

                    foreach ($data['payments'] as $paymentData) {
                        $cardName = !empty($paymentData['card_id']) ? Card::find($paymentData['card_id'])?->description : null;
                        $bankName = !empty($paymentData['bank_id']) ? Bank::find($paymentData['bank_id'])?->description : null;
                        $walletName = !empty($paymentData['digital_wallet_id']) ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description : null;
                        $gatewayName = !empty($paymentData['payment_gateway_id']) ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description : null;

                        CashMovementDetail::create([
                            'cash_movement_id'   => $cashMovement->id,
                            'branch_id'          => $branchId,
                            'type'               => 'EGRESO',
                            'status'             => 'A',
                            'paid_at'            => now(),
                            'amount'             => $paymentData['amount'],
                            'payment_method_id'  => $paymentData['payment_method_id'],
                            'payment_method'     => $paymentData['payment_method'] ?? 'Desconocido',
                            'comment'            => !empty($paymentData['number']) ? 'Ref: ' . $paymentData['number'] : 'Pago de compra',
                            'number'             => $paymentData['number'] ?? null,
                            'card_id'            => $paymentData['card_id'] ?? null,
                            'card'               => $cardName,
                            'bank_id'            => $paymentData['bank_id'] ?? null,
                            'bank'               => $bankName,
                            'digital_wallet_id'  => $paymentData['digital_wallet_id'] ?? null,
                            'digital_wallet'     => $walletName,
                            'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                            'payment_gateway'    => $gatewayName,
                        ]);
                    }
                }

                if ($purchaseMovement->movement) {
                    app(KardexSyncService::class)->syncMovement($purchaseMovement->movement);
                }
            });

            return redirect()->route('purchase.index')->with('status', 'Compra actualizada correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar la compra: ' . $e->getMessage()])->withInput();
        }
    }

    public function edit(Request $request, PurchaseMovement $purchaseMovement)
    {
        $branchId = $request->session()->get('branch_id');
        $branch = Branch::find($branchId);
        $companyId = $branch?->company_id;

        $branches = Branch::where('company_id', $companyId)->get();

        $people = Person::whereIn('person_type', ['SUPPLIER', 'PROVIDER'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $documentTypes = DocumentType::where('movement_type_id', 1)->get();
        $units = Unit::all();
        $defaultTaxRate = 18.00;
        
        $purchaseMovement->load('movement', 'details');
        
        $purchase = $purchaseMovement->movement; 
        
        $categories = Category::query()
            ->when($branchId, fn($q) => $q->forBranchMenu($branchId, 'COMPRAS'), function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->orderBy('description')
            ->get();
        
        $validCategoryIds = $categories->pluck('id');

        $products = ProductBranch::where('branch_id', $branchId)
            ->with(['product', 'product.baseUnit', 'product.category', 'product.productType'])
            ->get()
            ->filter(function ($pb) use ($validCategoryIds) {
                $product = $pb->product;
                if (!$product) return false;

                // Solo si la categoría está permitida para Compras
                if (!in_array($product->category_id, $validCategoryIds->all())) {
                    return false;
                }

                $pt = $product->productType;
                if (!$pt) return false;
                return in_array($pt->behavior, ['SUPPLY', 'BOTH'], true);
            })
            ->map(function ($pb) {
                $product = $pb->product;
                if (!$product) return null;
                $imgPath = $product->image ?? null;
                $baseUnit = $product->baseUnit;
                return (object) [
                    'id' => $pb->product_id,
                    'code' => $product->code ?? '',
                    'description' => $product->description ?? '',
                    'name' => $product->description ?? '',
                    'unit_sale' => $product->base_unit_id ?? 0,
                    'unit_id' => $product->base_unit_id ?? 0,
                    'unit_name' => $baseUnit ? ($baseUnit->description ?? $baseUnit->name ?? '') : '',
                    'price' => $pb->price ?? 0,
                    'cost' => $pb->purchase_price ?? 0,
                    'stock' => (float) ($pb->stock ?? 0),
                    'category_id' => $pb->product->category_id ?? null,
                    'category' => $pb->product->category ? $pb->product->category->description : 'General',
                    'image' => $imgPath,
                    'image_url' => $imgPath ? asset('storage/' . $imgPath) : null,
                ];
            })
            ->filter()
            ->values();
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num', 'asc')
            ->get(['id', 'description']);

        // Mandar los modelos de pago
        $cards = Card::all();
        $paymentGateways = PaymentGateways::all();
        $digitalWallets = DigitalWallet::all();
        $banks = Bank::all();
        $viewId = $request->input('view_id');

        $departments = Location::query()->where('type', 'department')->orderBy('name')->get(['id', 'name']);
        $provinces   = Location::query()->where('type', 'province')->orderBy('name')->get(['id', 'name', 'parent_location_id']);
        $districts   = Location::query()->where('type', 'district')->orderBy('name')->get(['id', 'name', 'parent_location_id']);

        return view('purchases._form', compact(
            'purchaseMovement', 'purchase', 'people', 'documentTypes', 'units',
            'products', 'defaultTaxRate', 'branches', 'branchId',
            'cards', 'paymentGateways', 'digitalWallets', 'banks',
            'categories', 'viewId', 'paymentMethods', 'departments', 'provinces', 'districts'
        ));
    }

    /**
     * Crear proveedor desde el formulario de compra (modal). Devuelve JSON para actualizar el combobox.
     */
    public function storeProveedor(Request $request)
    {
        $branchId = $request->session()->get('branch_id');
        if (!$branchId) {
            return response()->json(['message' => 'No hay sucursal seleccionada.'], 422);
        }

        $branch = Branch::find($branchId);
        if (!$branch) {
            return response()->json(['message' => 'Sucursal no válida.'], 422);
        }

        $data = $request->validate([
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['nullable', 'string', 'max:255'],
            'person_type'      => ['required', 'string', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number'  => ['required', 'string', 'max:50'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'address'          => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento'  => ['nullable', 'date'],
            'genero'           => ['nullable', 'string', 'max:20'],
            'location_id'      => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        // PostgreSQL no acepta NULL en columnas NOT NULL → forzar cadena vacía
        $data['last_name']  = $data['last_name']  ?? '';
        $data['phone']      = $data['phone']      ?? '';
        $data['email']      = $data['email']      ?? '';
        $data['address']    = $data['address']    ?? '';

        $data['branch_id']   = $branch->id;
        $data['location_id'] = $data['location_id'] ?? $branch->location_id ?? 1477;

        $person = Person::create($data);

        $proveedorRoleId = (int) env('PROVEEDOR_ROLE_ID', 4);
        if (!Role::where('id', $proveedorRoleId)->exists()) {
            $proveedorRoleId = (int) Role::query()->orderBy('id')->value('id');
        }
        if ($proveedorRoleId > 0) {
            $person->roles()->attach($proveedorRoleId, ['branch_id' => $branch->id]);
        }

        $description = trim($person->first_name . ' ' . $person->last_name) . ' - ' . $person->document_number;

        return response()->json([
            'id' => $person->id,
            'description' => $description,
        ]);
    }

    public function destroy(PurchaseMovement $purchaseMovement)
    {
        if ($purchaseMovement->movement_id) {
            app(KardexSyncService::class)->deleteMovement((int) $purchaseMovement->movement_id);
        }
        $purchaseMovement->delete();
        return redirect()->route('purchase.index')
            ->with('status', 'Compra eliminada correctamente.');
    }
}