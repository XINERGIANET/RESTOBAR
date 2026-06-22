<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Card;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\CashShiftRelation;
use App\Models\Category;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Operation;
use App\Models\OrderMovement;
use App\Models\OrderMovementDetail;
use App\Models\OrderPaymentSplit;
use App\Models\OrderPaymentSplitDetail;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\PrinterBranch;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\Location;
use App\Models\Role;
use App\Models\SalesMovement;
use App\Models\SalesMovementDetail;
use App\Models\Shift;
use App\Models\Table;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountReceivablePayableService;
use App\Services\ApisunatService;
use App\Services\OrderPaymentSplitService;
use App\Services\KardexSyncService;
use App\Services\PrintBridgeQueue;
use App\Services\ThermalNetworkPrintService;
use App\Support\InsensitiveSearch;
use App\Support\LocalNetworkClient;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private function waiterPinEnabled(?int $branchId): bool
    {
        if (! $branchId) {
            return false;
        }
        $value = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('bp.branch_id', $branchId)
            ->where('p.description', 'Requerir PIN a mozo')
            ->value('bp.value');

        $v = strtolower(trim((string) ($value ?? '0')));

        return in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'y', 'on'], true);
    }

    private function mozoProfileId(): ?int
    {
        return Profile::mozoProfileId();
    }

    /** Solo pedir PIN cuando la sucursal lo tiene activo Y el usuario tiene perfil Mozo. */
    private function shouldRequireWaiterPin(?int $branchId, $profileId): bool
    {
        if (! $this->waiterPinEnabled($branchId)) {
            return false;
        }
        $mozoId = $this->mozoProfileId();
        if ($mozoId === null) {
            return false;
        }
        $currentProfileId = $profileId !== null && $profileId !== '' ? (int) $profileId : null;

        return $currentProfileId === $mozoId;
    }

    /** El perfil Mozo puede guardar pedidos pero NO puede cobrar. */
    private function canCharge($profileId): bool
    {
        if ($this->mozoProfileId() === null) {
            return true;
        }
        $currentProfileId = $profileId !== null && $profileId !== '' ? (int) $profileId : null;

        return ! Profile::userHasMozoProfile($currentProfileId);
    }

    private function clienteRoleId(): ?int
    {
        $id = Role::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['cliente'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function resolveOrCreateClientPerson(?int $branchId, ?Branch $branch, ?int $clientPersonId, ?string $clientName): ?Person
    {
        if ($clientPersonId) {
            $person = Person::find($clientPersonId);
            if ($person) {
                return $person;
            }
        }

        $name = trim((string) $clientName);
        if (preg_match('/^\s*\d+\s*-\s*(.+)$/u', $name, $matches) === 1) {
            $name = trim((string) ($matches[1] ?? ''));
        }
        if ($name === '' || mb_strtolower($name) === 'público general' || mb_strtolower($name) === 'publico general') {
            return null;
        }

        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', $name), 'UTF-8');
        $existing = Person::query()
            ->where('branch_id', $branchId)
            ->where('document_number', '0')
            ->whereNull('deleted_at')
            ->get()
            ->first(function (Person $person) use ($normalizedName) {
                $personFullName = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
                $personNormalized = mb_strtolower(preg_replace('/\s+/', ' ', $personFullName), 'UTF-8');
                $firstNameNormalized = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $person->first_name)), 'UTF-8');

                return $personNormalized === $normalizedName || $firstNameNormalized === $normalizedName;
            });

        if ($existing) {
            $clienteRoleId = $this->clienteRoleId();
            if ($clienteRoleId) {
                $existing->roles()->syncWithoutDetaching([
                    $clienteRoleId => ['branch_id' => $branchId],
                ]);
            }

            return $existing;
        }

        $person = Person::create([
            'first_name' => $name,
            'last_name' => '',
            'person_type' => 'DNI',
            'document_number' => '0',
            'address' => '',
            'phone' => null,
            'email' => null,
            'location_id' => $branch?->location_id,
            'branch_id' => $branchId,
        ]);

        $clienteRoleId = $this->clienteRoleId();
        if ($clienteRoleId) {
            $person->roles()->syncWithoutDetaching([
                $clienteRoleId => ['branch_id' => $branchId],
            ]);
        }

        return $person;
    }

    /**
     * Total para tarjetas de mesa y API tablesData: productos (subtotal + impuestos) + delivery + descartables (llevar).
     */
    private function orderMovementDisplayTotal(?OrderMovement $orderMovement): float
    {
        if (! $orderMovement) {
            return 0.0;
        }
        $base = round((float) ($orderMovement->subtotal ?? 0) + (float) ($orderMovement->tax ?? 0), 2);
        $deliveryFee = strtoupper((string) ($orderMovement->service_type ?? '')) === 'DELIVERY'
            ? (float) ($orderMovement->delivery_amount ?? 0)
            : 0.0;
        $disposable = (float) ($orderMovement->takeaway_disposable_amount ?? 0);

        return round($base + $deliveryFee + $disposable, 2);
    }

    private function splitAccountViewData(?OrderMovement $pendingOrder): array
    {
        $enabled = (bool) config('orders.split_account_enabled', true);
        $branchId = (int) (session('branch_id') ?? 0);
        $splitBranchIds = config('orders.split_account_branch_ids', []);
        if (is_array($splitBranchIds) && count($splitBranchIds) > 0 && ! in_array($branchId, $splitBranchIds, true)) {
            $enabled = false;
        }
        if (! $pendingOrder || ! $enabled) {
            return [
                'split_account_enabled' => $enabled,
                'pending_split_remaining_total' => null,
                'pending_split_mode' => null,
                'split_locked_to_amount' => false,
                'pending_split_lines' => [],
            ];
        }

        $pendingOrder->loadMissing('details');
        $svc = app(OrderPaymentSplitService::class);
        $remaining = $svc->remainingOrderTotal($pendingOrder);
        $billed = $svc->billedQuantityByDetailId($pendingOrder);
        $lines = $pendingOrder->details
            ->filter(fn($d) => ($d->status ?? 'A') !== 'C')
            ->map(function ($d) use ($billed) {
                $qty = (float) $d->quantity;
                $courtesy = max(0, min((float) ($d->courtesy_quantity ?? 0), $qty));
                $billable = max(0, $qty - $courtesy);
                $already = (float) ($billed[$d->id] ?? 0);
                $remQty = max(0, $billable - $already);
                $unitGross = $billable > 0 ? ((float) ($d->amount ?? 0)) / $billable : 0;

                return [
                    'detail_id' => $d->id,
                    'description' => $d->description ?? '',
                    'remaining_qty' => round($remQty, 6),
                    'unit_amount' => round($unitGross, 6),
                    'line_remaining_total' => round($unitGross * $remQty, 2),
                ];
            })
            ->values()
            ->filter(fn($r) => (float) ($r['remaining_qty'] ?? 0) > 0.000001)
            ->values()
            ->all();

        return [
            'split_account_enabled' => true,
            'pending_split_remaining_total' => round($remaining, 2),
            'pending_split_mode' => $pendingOrder->split_mode,
            'split_locked_to_amount' => (bool) $pendingOrder->split_locked_to_amount,
            'pending_split_lines' => $lines,
        ];
    }

    /**
     * Ítems para el POS (resumen / cobro): cantidades e importes pendientes tras cobros parciales por división de cuenta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderDetailsToPendingPosItems(OrderMovement $orderMovement): array
    {
        $orderMovement->loadMissing('details');
        /** @var OrderPaymentSplitService $splitSvc */
        $splitSvc = app(OrderPaymentSplitService::class);
        $billed = $splitSvc->billedQuantityByDetailId($orderMovement);

        return ($orderMovement->details ?? collect())
            ->filter(fn($d) => ($d->status ?? 'A') !== 'C')
            ->map(function ($d) use ($billed) {
                $qty = (float) ($d->quantity ?? 0);
                $courtesyQty = max(0, min((float) ($d->courtesy_quantity ?? 0), $qty));
                $billableQty = max(0, $qty - $courtesyQty);
                $alreadyBilled = (float) ($billed[$d->id] ?? 0);
                $remBillable = max(0, round($billableQty - $alreadyBilled, 6));
                if ($remBillable <= 0.000001) {
                    return null;
                }

                $amount = (float) ($d->amount ?? 0);
                $paidQty = max(0, $qty - $courtesyQty);
                $price = ($paidQty > 0)
                    ? ($amount / $paidQty)
                    : 0;

                $rawComment = trim((string) ($d->comment ?? ''));
                $note = $rawComment;
                if ($note !== '' && preg_match('/^\d{2}:\d{2}\s*-\s*/', $note) === 1) {
                    $note = preg_replace('/^\d{2}:\d{2}(?:\s*[ap]\.?m\.?)?\s*-\s*/i', '', $note);
                    $note = trim($note);
                }
                $commandTime = $d->commanded_at
                    ? $d->commanded_at->format('H:i')
                    : ($d->created_at ? $d->created_at->format('H:i') : null);
                $status = $d->status ?? 'A';
                $takeawayQtyFull = max(0, min((float) ($d->takeaway_quantity ?? 0), $qty));

                if ($alreadyBilled <= 0.000001) {
                    return [
                        'pId' => (int) ($d->product_id ?? 0),
                        'name' => $d->description ?? '',
                        'qty' => $qty,
                        'price' => round($price, 6),
                        'tax_rate' => 10,
                        'priceManual' => true,
                        'note' => $note,
                        'commandTime' => $commandTime,
                        'delivered' => $status === 'E',
                        'courtesyQty' => $courtesyQty,
                        'takeawayQty' => $takeawayQtyFull,
                        'complements' => collect($d->complements ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
                    ];
                }

                $unitBillable = $billableQty > 0 ? ($amount / $billableQty) : 0;
                $scaledAmount = round($unitBillable * $remBillable, 2);
                $qtyOut = $remBillable;
                $priceOut = $remBillable > 0 ? ($scaledAmount / $remBillable) : 0;
                $takeawayScaled = $billableQty > 0
                    ? max(0, min(round($takeawayQtyFull * ($remBillable / $billableQty), 6), $remBillable))
                    : 0;

                return [
                    'pId' => (int) ($d->product_id ?? 0),
                    'name' => $d->description ?? '',
                    'qty' => $qtyOut,
                    'price' => round($priceOut, 6),
                    'tax_rate' => 10,
                    'priceManual' => true,
                    'note' => $note,
                    'commandTime' => $commandTime,
                    'delivered' => $status === 'E',
                    'courtesyQty' => 0,
                    'takeawayQty' => $takeawayScaled,
                    'complements' => collect($d->complements ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function validateWaiterPin(Request $request)
    {
        $branchId = (int) session('branch_id');
        $pin = trim((string) $request->input('pin'));

        if ($pin === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ingrese el PIN.',
            ], 422);
        }

        $person = Person::query()
            ->where('branch_id', $branchId)
            ->where('pin', $pin)
            ->first();

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'PIN inválido.',
            ], 422);
        }

        $name = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
        if ($name === '') {
            $name = 'Mozo';
        }

        $request->session()->put('waiter_person_id', (int) $person->id);
        $request->session()->put('waiter_name', $name);

        return response()->json([
            'success' => true,
            'waiter' => [
                'person_id' => (int) $person->id,
                'name' => $name,
            ],
        ]);
    }

    public function list(Request $request)
    {
        $search = $request->input('search');
        $viewId = $request->input('view_id');
        $status = $request->input('status');
        $perPage = (int) $request->input('per_page', 10);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $allowedPerPage = [10, 20, 50, 100];
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $branchId = $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $cashRegisterId = effective_cash_register_id($branchId ? (int) $branchId : null);
        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->tap(fn($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%venta%'))
            ->get(['id', 'name']);
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num')
            ->get(['id', 'description']);
        $effectiveCashRegisterId = $cashRegisterId;
        $cashShiftRelationId = $request->input('cash_shift_relation_id');

        $cashRegisters = CashRegister::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('number')
            ->get(['id', 'number']);
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

        // Sesiones (turnos) por caja para filtrar el listado y "limpiar" al abrir una nueva
        $cashShiftSessions = collect();
        if ($branchId && $effectiveCashRegisterId) {
            $cashShiftSessions = \App\Models\CashShiftRelation::query()
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCashRegisterId) {
                    $q->where('cash_register_id', $effectiveCashRegisterId);
                })
                ->with(['cashMovementStart.shift', 'cashMovementEnd'])
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        // Por defecto: filtrar por el turno actual (o último) de la caja seleccionada/en sesión
        if (($cashShiftRelationId === null || $cashShiftRelationId === '') && $branchId && $effectiveCashRegisterId) {
            $lastShift = \App\Models\CashShiftRelation::query()
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

        $ordersQuery = OrderMovement::query()
            ->with([
                'movement.branch',
                'movement.person',
                'movement.movementType',
                'movement.documentType',
                'table',
                'area',
            ])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereHas('movement', function ($movementQuery) use ($dateFrom) {
                    $movementQuery->where('moved_at', '>=', $dateFrom . ' 00:00:00');
                });
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereHas('movement', function ($movementQuery) use ($dateTo) {
                    $movementQuery->where('moved_at', '<=', $dateTo . ' 23:59:59');
                });
            })

            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('movement', function ($movementQuery) use ($search) {
                        $movementQuery->where(function ($movementInner) use ($search) {
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'number', $search);
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'person_name', $search, 'or');
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'user_name', $search, 'or');
                        });
                    })
                        ->orWhere(function ($q) use ($search) {
                            InsensitiveSearch::whereInsensitiveLike($q, 'status', $search);
                        });
                });
            })
            ->when($documentTypeId, function ($query) use ($documentTypeId) {
                $query->whereHas('movement', function ($movementQuery) use ($documentTypeId) {
                    $movementQuery->where('document_type_id', $documentTypeId);
                });
            })
            ->when($cashRegisterId, function ($query) use ($cashRegisterId) {
                $query->whereExists(function ($sub) use ($cashRegisterId) {
                    $sub->select(DB::raw(1))
                        ->from('movements as m')
                        ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                        ->whereColumn('m.parent_movement_id', 'order_movements.movement_id')
                        ->where('cm.cash_register_id', $cashRegisterId)
                        ->whereNull('cm.deleted_at');
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($paymentMethodId, function ($query) use ($paymentMethodId) {
                $query->whereExists(function ($sub) use ($paymentMethodId) {
                    $sub->select(DB::raw(1))
                        ->from('movements as m')
                        ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                        ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                        ->whereColumn('m.parent_movement_id', 'order_movements.movement_id')
                        ->where('cmd.payment_method_id', $paymentMethodId)
                        ->whereNull('cm.deleted_at')
                        ->whereNull('cmd.deleted_at');
                });
            });

        // Filtro por turno (CashShiftRelation): ventana temporal por movimientos
        if ($cashShiftRelationId !== null && $cashShiftRelationId !== '' && $branchId && $effectiveCashRegisterId) {
            $csrApplied = \App\Models\CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', (int) $cashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCashRegisterId) {
                    $q->where('cash_register_id', $effectiveCashRegisterId);
                })
                ->first();

            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $ordersQuery->whereHas('movement', function ($q) use ($startMid, $csrApplied) {
                    $q->where('movements.id', '>=', $startMid);
                    if ($csrApplied->cashMovementEnd) {
                        $q->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                    }
                });
            } else {
                $ordersQuery->whereRaw('1 = 0');
            }
        }

        $orders = $ordersQuery->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('orders.list', [
            'orders' => $orders,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'documentTypes' => $documentTypes,
            'paymentMethods' => $paymentMethods,
            'documentTypeId' => $documentTypeId,
            'paymentMethodId' => $paymentMethodId,
            'cashRegisterId' => $effectiveCashRegisterId,
            'cashRegisters' => $cashRegisters,
            'cashShiftRelationId' => $cashShiftRelationId,
            'cashShiftSessions' => $cashShiftSessions,
            'status' => $status,
        ]);
    }

    public function index(Request $request)
    {
        $branchId = session('branch_id');
        $profileId = session('profile_id') ?? $request->user()?->profile_id;
        $waiterPinEnabled = $this->shouldRequireWaiterPin($branchId ? (int) $branchId : null, $profileId);

        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->get(['id', 'name']);

        // Cargar todas las mesas de la sucursal primero
        $tables = Table::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId, fn($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name', 'area_id', 'capacity', 'situation', 'opened_at']);

        // Área seleccionada explícitamente (por query)
        if ($request->has('area_id')) {
            $selectedAreaId = (int) $request->input('area_id');
        } else {
            // Preferir la primera área que tenga mesas; si ninguna, usar la primera área
            $areaIdsWithTables = $tables->pluck('area_id')->unique()->filter()->values();
            $firstAreaWithTables = $areas->firstWhere(fn($a) => $areaIdsWithTables->contains($a->id));
            $selectedAreaId = $firstAreaWithTables?->id ?? $areas->first()?->id ?? null;
        }

        // Redirigir para fijar area_id en la URL si no venía en el request
        if ($areas->isNotEmpty() && ! $request->has('area_id') && $selectedAreaId) {
            $params = array_merge(
                $request->except('area_id'),
                ['area_id' => $selectedAreaId]
            );

            return redirect()->route('orders.index', $params);
        }

        $activeOrderMovements = OrderMovement::with(['movement', 'details'])
            ->whereIn('table_id', $tables->pluck('id'))
            ->whereIn('status', ['PENDIENTE', 'P'])
            ->get()
            ->groupBy('table_id');

        $tablesPayload = $tables->map(function (Table $table) use ($activeOrderMovements, $branchId) {
            $elapsed = '--:--';
            if (! empty($table->opened_at)) {
                try {
                    $opened = \Carbon\Carbon::parse($table->opened_at);
                    if ($opened->gt(now())) {
                        $opened->subDay();
                    }
                    $minutes = (int) $opened->diffInMinutes(now());
                    if ($minutes < 60) {
                        $elapsed = $minutes . ' min';
                    } else {
                        $h = (int) floor($minutes / 60);
                        $m = $minutes % 60;
                        $elapsed = $h . 'h ' . $m . 'm';
                    }
                } catch (\Throwable $e) {
                    $elapsed = '--:--';
                }
            }

            $rawSituation = $table->situation ?? 'libre';
            $situation = strtolower((string) $rawSituation);
            if ($situation !== 'libre' && $situation !== 'ocupada') {
                $situation = (in_array($rawSituation, ['PENDIENTE', 'OCUPADA', 'ocupada', 'Pendiente'], true))
                    ? 'ocupada'
                    : 'libre';
            }

            $orderMovement = $activeOrderMovements->get($table->id)?->sortByDesc('id')->first();

            $totalWithTax = $this->orderMovementDisplayTotal($orderMovement);

            // Si no hay pedido pendiente o el total es 0, la mesa debe considerarse libre
            if (! $orderMovement || $totalWithTax <= 0) {
                $situation = 'libre';
                $totalWithTax = 0;
                $elapsed = '--:--';
            }

            $productsText = '';
            if ($orderMovement && $orderMovement->details && $orderMovement->details->isNotEmpty()) {
                $productsText = $orderMovement->details
                    ->map(function ($d) {
                        if (! empty($d->description)) {
                            return $d->description;
                        }
                        if (is_array($d->product_snapshot)) {
                            return $d->product_snapshot['description'] ?? $d->product_snapshot['name'] ?? '';
                        }

                        return '';
                    })
                    ->filter()
                    ->unique()
                    ->implode(' ');
            }

            $openedAtForJs = null;
            if (! empty($table->opened_at)) {
                try {
                    $openedAtForJs = \Carbon\Carbon::parse($table->opened_at)->format('H:i:s');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $ordersCount = 0;
            if ($orderMovement && $orderMovement->movement && $orderMovement->movement->person_id) {
                $personId = $orderMovement->movement->person_id;
                $ordersCount = OrderMovement::whereHas('movement', function ($query) use ($personId, $branchId) {
                    $query->where('person_id', $personId);
                    if ($branchId) {
                        $query->where('branch_id', $branchId);
                    }
                })->count();
            }

            return [
                'id' => $table->id,
                'name' => $table->name,
                'area_id' => (int) $table->area_id,
                'situation' => $situation,
                'diners' => (int) ($table->capacity ?? 0),
                'people_count' => (int) ($orderMovement?->people_count ?? 0),
                'waiter' => $orderMovement?->movement?->responsible_name ?? $orderMovement?->movement?->user_name ?? '-',
                'client' => $orderMovement?->movement?->person_name ?? '-',
                'total' => $totalWithTax,
                'order_movement_id' => $orderMovement?->id ?? null,
                'movement_id' => $orderMovement?->movement_id ?? null,
                'elapsed' => $elapsed,
                'opened_at' => $openedAtForJs,
                'products_text' => strtolower($productsText),
                'orders_count' => $ordersCount,
            ];
        })->values();

        $areasArray = $areas->map(function ($area) {
            return [
                'id' => (int) $area->id,
                'name' => $area->name,
            ];
        })->values();

        $response = response()->view('orders.index', [
            'areas' => $areasArray,
            'tables' => $tablesPayload,
            'user' => $request->user(),
            'waiterPinEnabled' => $waiterPinEnabled,
            'canCharge' => $this->canCharge($profileId),
            'isMozo' => Profile::userHasMozoProfile(
                $profileId !== null && $profileId !== '' ? (int) $profileId : null
            ),
            'selectedAreaId' => $selectedAreaId,
            'turboCacheControl' => 'no-cache',
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    public function pdfReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $documentTypeId = $request->input('document_type_id');
        $paymentMethodId = $request->input('payment_method_id');
        $branchId = $request->session()->get('branch_id');
        $cashRegisterId = effective_cash_register_id($branchId ? (int) $branchId : null);
        $status = $request->input('status');
        $cashShiftRelationId = $request->input('cash_shift_relation_id');

        $branch = $branchId ? Branch::with('company')->find($branchId) : null;
        $companyName = $branch?->company?->legal_name;
        $branchName = $branch?->legal_name;

        $orders = OrderMovement::query()
            ->with(['movement.documentType', 'movement.movementType', 'table', 'area'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($dateFrom, fn($q) => $q->whereHas('movement', fn($m) => $m->where('moved_at', '>=', $dateFrom . ' 00:00:00')))
            ->when($dateTo, fn($q) => $q->whereHas('movement', fn($m) => $m->where('moved_at', '<=', $dateTo . ' 23:59:59')))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('movement', function ($movementQuery) use ($search) {
                        $movementQuery->where(function ($movementInner) use ($search) {
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'number', $search);
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'person_name', $search, 'or');
                            InsensitiveSearch::whereInsensitiveLike($movementInner, 'user_name', $search, 'or');
                        });
                    })
                        ->orWhere(function ($q) use ($search) {
                            InsensitiveSearch::whereInsensitiveLike($q, 'status', $search);
                        });
                });
            })
            ->when($documentTypeId, function ($query) use ($documentTypeId) {
                $query->whereHas('movement', fn($m) => $m->where('document_type_id', $documentTypeId));
            })
            ->when($cashRegisterId, function ($query) use ($cashRegisterId) {
                $query->whereExists(function ($sub) use ($cashRegisterId) {
                    $sub->select(DB::raw(1))
                        ->from('movements as m')
                        ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                        ->whereColumn('m.parent_movement_id', 'order_movements.movement_id')
                        ->where('cm.cash_register_id', $cashRegisterId)
                        ->whereNull('cm.deleted_at');
                });
            })
            ->when($paymentMethodId, function ($query) use ($paymentMethodId) {
                $query->whereExists(function ($sub) use ($paymentMethodId) {
                    $sub->select(DB::raw(1))
                        ->from('movements as m')
                        ->join('cash_movements as cm', 'cm.movement_id', '=', 'm.id')
                        ->join('cash_movement_details as cmd', 'cmd.cash_movement_id', '=', 'cm.id')
                        ->whereColumn('m.parent_movement_id', 'order_movements.movement_id')
                        ->where('cmd.payment_method_id', $paymentMethodId)
                        ->whereNull('cm.deleted_at')
                        ->whereNull('cmd.deleted_at');
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            });

        // Filtro por turno (CashShiftRelation): ventana temporal por movimientos
        $effectiveCR = $cashRegisterId;
        if ($cashShiftRelationId !== null && $cashShiftRelationId !== '' && $branchId && $effectiveCR) {
            $csrApplied = \App\Models\CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', (int) $cashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($effectiveCR) {
                    $q->where('cash_register_id', $effectiveCR);
                })
                ->first();

            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $orders->whereHas('movement', function ($q) use ($startMid, $csrApplied) {
                    $q->where('movements.id', '>=', $startMid);
                    if ($csrApplied->cashMovementEnd) {
                        $q->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                    }
                });
            } else {
                $orders->whereRaw('1 = 0');
            }
        }

        $orders = $orders->orderByDesc('id')->get();

        $filters = [];
        $filters['Desde'] = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : null;
        $filters['Hasta'] = $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : null;
        if ($search !== null && $search !== '') {
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
        if ($cashRegisterId) {
            $cr = CashRegister::find($cashRegisterId);
            $filters['Caja'] = $cr ? ($cr->number ?? $cr->id) : "ID {$cashRegisterId}";
        }
        if ($cashShiftRelationId && isset($csrApplied) && $csrApplied) {
            $shiftLabel = ($csrApplied->cashMovementStart?->shift?->name ?? 'Turno') . ' (' . $csrApplied->id . ')';
            $filters['Turno'] = $shiftLabel;
        }
        if ($status) {
            $filters['Estado'] = $status;
        }

        $pdf = SnappyPdf::loadView('orders.pdfs.pdf_report', [
            'orders' => $orders,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filters' => $filters,
            'companyName' => $companyName,
            'branchName' => $branchName,
        ]);

        return $pdf->download('reporte_pedidos.pdf');
    }

    public function tablesData(Request $request)
    {
        $branchId = session('branch_id');
        $areas = Area::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->get(['id', 'name']);
        $tables = Table::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId, fn($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name', 'area_id', 'capacity', 'situation', 'opened_at']);
        $tablesPayload = $tables->map(function (Table $table) use ($branchId) {
            $elapsed = '--:--';
            if (! empty($table->opened_at)) {
                try {
                    $opened = \Carbon\Carbon::parse($table->opened_at);
                    if ($opened->gt(now())) {
                        $opened->subDay();
                    }
                    $minutes = (int) $opened->diffInMinutes(now());
                    if ($minutes < 60) {
                        $elapsed = $minutes . ' min';
                    } else {
                        $h = (int) floor($minutes / 60);
                        $m = $minutes % 60;
                        $elapsed = $h . 'h ' . $m . 'm';
                    }
                } catch (\Throwable $e) {
                    $elapsed = '--:--';
                }
            }
            $rawSituation = $table->situation ?? 'libre';
            $situation = strtolower((string) $rawSituation);
            if ($situation !== 'libre' && $situation !== 'ocupada') {
                $situation = (in_array($rawSituation, ['PENDIENTE', 'OCUPADA', 'ocupada', 'Pendiente'], true))
                    ? 'ocupada'
                    : 'libre';
            }
            $orderMovement = OrderMovement::with('movement', 'details')
                ->where('table_id', $table->id)
                ->whereIn('status', ['PENDIENTE', 'P'])
                ->orderByDesc('id')
                ->first();
            $totalWithTax = $this->orderMovementDisplayTotal($orderMovement);

            // Si no hay pedido pendiente o el total es 0, la mesa debe considerarse libre
            if (! $orderMovement || $totalWithTax <= 0) {
                $situation = 'libre';
                $totalWithTax = 0;
                $elapsed = '--:--';
            }
            $productsText = '';
            if ($orderMovement && $orderMovement->relationLoaded('details') && $orderMovement->details->isNotEmpty()) {
                $productsText = $orderMovement->details
                    ->map(fn($d) => $d->description ?? ($d->product_snapshot['description'] ?? $d->product_snapshot['name'] ?? ''))
                    ->filter()
                    ->unique()
                    ->implode(' ');
            }
            $openedAtForJs = null;
            if (! empty($table->opened_at)) {
                try {
                    $openedAtForJs = \Carbon\Carbon::parse($table->opened_at)->format('H:i:s');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $ordersCount = 0;
            if ($orderMovement && $orderMovement->movement && $orderMovement->movement->person_id) {
                $personId = $orderMovement->movement->person_id;
                $ordersCount = OrderMovement::whereHas('movement', function ($query) use ($personId, $branchId) {
                    $query->where('person_id', $personId);
                    if ($branchId) {
                        $query->where('branch_id', $branchId);
                    }
                })->count();
            }

            return [
                'id' => $table->id,
                'name' => $table->name,
                'area_id' => (int) $table->area_id,
                'situation' => $situation,
                'diners' => (int) ($table->capacity ?? 0),
                'people_count' => (int) ($orderMovement?->people_count ?? 0),
                'waiter' => $orderMovement?->movement?->responsible_name ?? $orderMovement?->movement?->user_name ?? '-',
                'client' => $orderMovement?->movement?->person_name ?? '-',
                'total' => $totalWithTax,
                'order_movement_id' => $orderMovement?->id ?? null,
                'movement_id' => $orderMovement?->movement_id ?? null,
                'elapsed' => $elapsed,
                'opened_at' => $openedAtForJs,
                'products_text' => $productsText,
                'orders_count' => $ordersCount,
            ];
        })->values();
        $areasArray = $areas->map(fn($area) => ['id' => (int) $area->id, 'name' => $area->name])->values();

        return response()
            ->json(['tables' => $tablesPayload, 'areas' => $areasArray])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function create(Request $request)
    {
        $tableId = $request->query('table_id');
        $profileId = session('profile_id');
        $personId = session('person_id');
        $userId = session('user_id');

        $user = User::find($userId);
        $person = Person::find($personId);
        $profile = Profile::find($profileId);
        $table = Table::with('area')->find($tableId);

        if (! $table) {
            abort(404, 'Mesa no encontrada');
        }

        $area = $table->area;
        if (! $area && $request->has('area_id')) {
            $area = Area::find($request->query('area_id'));
        }
        // Sucursal: mesa > área > sesión (garantiza productos de la sucursal correcta).
        $branchId = (int) ($table->branch_id ?? $area?->branch_id ?? session('branch_id')) ?: null;
        $branch = $branchId ? Branch::find($branchId) : null;

        // Clientes del selector: rol «Cliente» → sin usuario → todos de la sucursal (fallbacks).
        $people = $this->resolveClientPeople($branchId);
        $waiters = $this->resolveWaiters($branchId);

        // Mismo criterio que Ventas: solo productos vendibles (sin tipo o tipo con behavior SELLABLE/BOTH), con product_branch y categoría en la sucursal.
        $products = Product::where('products.type', 'PRODUCT')
            ->where(function ($q) {
                $q->whereNull('products.product_type_id')
                    ->orWhereIn('products.product_type_id', function ($sub) {
                        $sub->select('id')->from('product_types')
                            ->whereIn('behavior', [
                                ProductType::BEHAVIOR_SELLABLE,
                                ProductType::BEHAVIOR_BOTH,
                            ])
                            ->whereNull('deleted_at');
                    });
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereIn('products.id', function ($sub) use ($branchId) {
                    $sub->select('product_id')->from('product_branch')
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at');
                })
                ->whereIn('products.category_id', function ($sub) use ($branchId) {
                    $sub->select('category_id')->from('category_branch')
                        ->where('branch_id', $branchId)
                        ->whereIn('menu_type', ['VENTAS_PEDIDOS', 'COMPRAS', 'GENERAL'])
                        ->whereNull('deleted_at');
                });
            }, function ($query) {
                $query->whereRaw('1 = 0'); // sin sucursal = no productos
            })
            ->with('category')
            ->orderBy('products.description')
            ->get()
            ->map(function ($product) use ($tableId, $branchId) {
                $imageUrl = ($product->image && ! empty($product->image))
                    ? asset('storage/' . $product->image)
                    : null;

                return [
                    'id' => $product->id,
                    'name' => $product->description,
                    'code' => filled(trim((string) ($product->code ?? ''))) ? trim((string) $product->code) : null,
                    'img' => $imageUrl,
                    'category' => $product->category ? $product->category->description : 'Sin categoría',
                    'category_id' => $product->category_id,
                    'detail_options' => collect($product->detail_options ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
                    'table_id' => $tableId,
                    'branch_id' => $branchId,
                ];
            });

        // Solo product_branches de esta sucursal cuyo producto es vendible (mismo criterio que Ventas).
        $productBranches = $branchId
            ? ProductBranch::with(['product.productType', 'taxRate', 'printers'])
            ->where('branch_id', $branchId)
            ->whereIn('product_id', function ($q) {
                $q->select('id')->from('products')
                  ->whereNull('deleted_at')
                  ->where(function ($sub) {
                      $sub->whereNull('product_type_id')
                          ->orWhereIn('product_type_id', function ($tSub) {
                              $tSub->select('id')->from('product_types')
                                  ->whereIn('behavior', [ProductType::BEHAVIOR_SELLABLE, ProductType::BEHAVIOR_BOTH])
                                  ->whereNull('deleted_at');
                          });
                  });
            })
            ->get()
            ->map(function ($productBranch) {
                $taxRatePct = $productBranch->taxRate ? (float) $productBranch->taxRate->tax_rate : null;
                $printerNames = $productBranch->printers
                    ->pluck('name')
                    ->map(fn($n) => trim((string) $n))
                    ->filter(fn($n) => $n !== '')
                    ->values()
                    ->all();
                $printers = $productBranch->printers
                    ->map(function ($p) {
                        $name = trim((string) ($p->name ?? ''));
                        $widthRaw = trim((string) ($p->width ?? ''));
                        if ($name === '') {
                            return null;
                        }

                        return [
                            'name' => $name,
                            'width' => $widthRaw !== '' ? $widthRaw : null,
                            'ip' => filled(trim((string) ($p->ip ?? ''))) ? trim((string) $p->ip) : null,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $productBranch->id,
                    'product_id' => $productBranch->product_id,
                    'price' => (float) $productBranch->price,
                    'stock' => (float) ($productBranch->stock ?? 0),
                    'tax_rate' => $taxRatePct,
                    'favorite' => ($productBranch->favorite ?? 'N'),
                    // compat (1 impresora)
                    'qz_printer_name' => request()->ip() === '127.0.0.1' || request()->ip() === '::1' ? ($printerNames[0] ?? null) : 'BARRA2',
                    // recomendado (varias impresoras por pivote)
                    'qz_printer_names' => $printerNames,
                    // recomendado: info completa para formateo por ticketera
                    'qz_printers' => $printers,
                ];
            })
            ->values()
            : collect();

        // Categorías asignadas a esta sucursal (category_branch).
        $categories = Category::query()
            ->when($branchId, fn($q) => $q->forBranchMenu($branchId, 'VENTAS_PEDIDOS'), function ($query) {
                $query->whereRaw('1 = 0'); // sin sucursal = no categorías
            })
            ->orderBy('description')
            ->get();

        $units = Unit::query()->orderBy('description')->get();

        // Pedido pendiente activo para esta mesa (si existe, lo cargamos para no duplicar)
        $pendingOrder = OrderMovement::with(['movement', 'details'])
            ->where('table_id', $table->id)
            ->whereIn('status', ['PENDIENTE', 'P'])
            ->orderByDesc('id')
            ->first();
        $startFresh = ! $pendingOrder;

        // Cliente actual del pedido pendiente (si existe)
        $pendingClientId = $pendingOrder?->movement?->person_id;
        $pendingClientName = $pendingOrder?->movement?->person_name;

        // Solo detalles activos con cantidad pendiente de cobro (resta divisiones de cuenta ya cobradas).
        $pendingItemsRaw = $pendingOrder
            ? $this->mapOrderDetailsToPendingPosItems($pendingOrder)
            : [];

        // Agrupar mismo producto en un solo ítem (ej. PB x5 + PB x1 → PB x6)
        $pendingItems = collect($pendingItemsRaw)->groupBy(function ($item) {
            $complements = collect($item['complements'] ?? [])->map(fn($value) => trim((string) $value))->filter()->values()->all();
            sort($complements);
            return implode('|', [
                (int) ($item['pId'] ?? 0),
                md5(json_encode($complements)),
                trim((string) ($item['note'] ?? '')),
            ]);
        })->map(function ($group) {
            $first = $group->first();

            $sumQty = $group->sum('qty');
            $sumTakeaway = $group->sum('takeawayQty');

            $sumPaid = 0.0;
            $sumWeighted = 0.0;
            foreach ($group as $row) {
                $q = (float) ($row['qty'] ?? 0);
                $cq = (float) ($row['courtesyQty'] ?? 0);
                $cq = max(0, min($cq, $q));
                $paid = max(0, $q - $cq);
                $p = (float) ($row['price'] ?? 0);
                $sumPaid += $paid;
                $sumWeighted += $p * $paid;
            }
            $mergedPrice = $sumPaid > 0 ? round($sumWeighted / $sumPaid, 6) : (float) ($first['price'] ?? 0);

            return [
                'pId' => $first['pId'],
                'name' => $first['name'],
                'qty' => $sumQty,
                'price' => $mergedPrice,
                'tax_rate' => $first['tax_rate'] ?? 10,
                'priceManual' => true,
                'note' => $first['note'],
                'commandTime' => $first['commandTime'],
                'delivered' => $group->contains('delivered', true),
                'courtesyQty' => $group->sum('courtesyQty'),
                'takeawayQty' => min($sumTakeaway, $sumQty),
                'complements' => $first['complements'] ?? [],
            ];
        })->values()->all();

        // Detalles cancelados: agrupar mismo producto en uno solo (ej. PB x5 + PB x1 → PB x6)
        $pendingCancelledDetails = $pendingOrder
            ? collect($pendingOrder->details->where('status', 'C')->map(fn($d) => [
                'product_id' => (int) ($d->product_id ?? 0),
                'description' => $d->description ?? 'Producto',
                'quantity' => (float) $d->quantity,
                'comment' => $d->comment ?? '',
                'complements' => collect($d->complements ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
            ]))
            ->groupBy(function ($item) {
                $complements = collect($item['complements'] ?? [])->map(fn($value) => trim((string) $value))->filter()->values()->all();
                sort($complements);
                return implode('|', [(int) ($item['product_id'] ?? 0), md5(json_encode($complements))]);
            })
            ->map(fn($group) => [
                'description' => $group->first()['description'],
                'quantity' => $group->sum('quantity'),
                'comment' => $group->first()['comment'],
                'complements' => $group->first()['complements'] ?? [],
            ])
            ->values()
            ->all()
            : [];

        $saleOrOrderTypeIds = MovementType::query()
            ->where(function ($q) {
                $q->where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%sale%')
                    ->orWhere('description', 'like', '%pedido%')
                    ->orWhere('description', 'like', '%orden%');
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->whereIn('movement_type_id', ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2])
            ->get(['id', 'name']);
        $defaultDocumentTypeId = effective_default_sale_document_type_id($branchId, ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2]);
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId)
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
            ->where('status', '1')
            ->orderBy('number')
            ->get(['id', 'number']);

        $deliveryAreaId = Area::query()
            ->tap(fn($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%delivery%'))
            ->value('id');
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

        $viewIdForPos = $request->query('view_id');
        $recipeStockData = $this->buildRecipeStockData($branchId, $branch?->company_id);
        $areaPrinterNames = $branchId
            ? Area::query()
            ->where('branch_id', $branchId)
            ->with(['printers' => fn($q) => $q->select('printers_branch.id', 'name')])
            ->get()
            ->mapWithKeys(function ($a) {
                $names = $a->printers
                    ->pluck('name')
                    ->map(fn($n) => trim((string) $n))
                    ->filter(fn($n) => $n !== '')
                    ->values()
                    ->all();
                return [(int) $a->id => $names];
            })
            ->all()
            : [];
        $response = response()->view('orders.create', array_merge([
            'user' => $user,
            'person' => $person,
            'profile' => $profile,
            'branch' => $branch,
            'area' => $area,
            'table' => $table,
            'products' => $products,
            'productBranches' => $productBranches,
            'categories' => $categories,
            'people' => $people,
            'waiters' => $waiters,
            'units' => $units,
            'startFresh' => $startFresh,
            'pendingOrderMovementId' => $pendingOrder?->id,
            'pendingMovementId' => $pendingOrder?->movement_id,
            'pendingClientId' => $pendingClientId,
            'pendingClientName' => $pendingClientName,
            'pendingWaiterId' => $pendingOrder?->movement?->person_id ?? session('waiter_person_id'),
            'pendingWaiterName' => $pendingOrder?->movement?->responsible_name ?? session('waiter_name'),
            'pendingPeopleCount' => (int) ($pendingOrder?->people_count ?: ($table->capacity ?? 1)),
            'pendingCancelledDetails' => $pendingCancelledDetails,
            'pendingItems' => $pendingItems,
            'pendingServiceType' => $pendingOrder?->service_type ?? ((strpos(strtolower($area->name ?? ''), 'delivery') !== false) ? 'DELIVERY' : 'IN_SITU'),
            'pendingDeliveryAddress' => $pendingOrder?->delivery_address,
            'pendingContactPhone' => $pendingOrder?->contact_phone,
            'pendingDeliveryAmount' => $pendingOrder?->delivery_amount ?? 0,
            'pendingTakeawayDisposableAmount' => (float) ($pendingOrder?->takeaway_disposable_amount ?? 0),
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'banks' => $banks,
            'cashRegisters' => $cashRegisters,
            'waiterPinEnabled' => $this->shouldRequireWaiterPin((int) $branchId, $profileId),
            'deliveryAreaId' => $deliveryAreaId,
            'canCharge' => $this->canCharge($profileId),
            'isMozo' => Profile::userHasMozoProfile(
                $profileId !== null && $profileId !== '' ? (int) $profileId : null
            ),
            'departments' => $departments,
            'provinces' => $provinces,
            'districts' => $districts,
            'turboCacheControl' => 'no-cache',
            'allowZeroStockSales' => (bool) ($branch?->allow_zero_stock_sales ?? true),
            'recipeStockData' => $recipeStockData,
            'areaPrinterNames' => $areaPrinterNames,
            'afterPaymentIndexUrl' => route('orders.index', $viewIdForPos ? ['view_id' => $viewIdForPos] : []),
        ], $this->splitAccountViewData($pendingOrder)));
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * POS Ventas: misma vista y lógica que Pedidos/create, sin mesa (pedido sin table_id por usuario).
     */
    public function createCounterSalePos(Request $request)
    {
        $profileId = session('profile_id');
        $personId = session('person_id');
        $userId = session('user_id');

        $user = User::find($userId);
        $person = Person::find($personId);
        $profile = Profile::find($profileId);

        $branchId = (int) (session('branch_id') ?? 0) ?: null;
        if (! $branchId) {
            abort(404, 'Sin sucursal en sesión');
        }
        $branch = Branch::findOrFail($branchId);
        $area = Area::where('branch_id', $branchId)->orderBy('id')->first();
        if (! $area) {
            $area = new Area(['name' => 'Local', 'branch_id' => $branchId]);
        }

        $table = new \stdClass();
        $table->id = 0;
        $table->name = 'Mostrador';
        $table->area_id = $area->id ?? null;
        $table->branch_id = $branchId;
        $table->capacity = 1;
        $table->situation = 'ocupada';
        $table->area = $area;

        $people = $this->resolveClientPeople($branchId);
        $waiters = $this->resolveWaiters($branchId);

        $products = Product::where('products.type', 'PRODUCT')
            ->where(function ($q) {
                $q->whereNull('products.product_type_id')
                    ->orWhereIn('products.product_type_id', function ($sub) {
                        $sub->select('id')->from('product_types')
                            ->whereIn('behavior', [
                                ProductType::BEHAVIOR_SELLABLE,
                                ProductType::BEHAVIOR_BOTH,
                            ])
                            ->whereNull('deleted_at');
                    });
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereIn('products.id', function ($sub) use ($branchId) {
                    $sub->select('product_id')->from('product_branch')
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at');
                })
                ->whereIn('products.category_id', function ($sub) use ($branchId) {
                    $sub->select('category_id')->from('category_branch')
                        ->where('branch_id', $branchId)
                        ->whereIn('menu_type', ['VENTAS_PEDIDOS', 'COMPRAS', 'GENERAL'])
                        ->whereNull('deleted_at');
                });
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->with('category')
            ->orderBy('products.description')
            ->get()
            ->map(function ($product) use ($branchId) {
                $imageUrl = ($product->image && ! empty($product->image))
                    ? asset('storage/' . $product->image)
                    : null;

                return [
                    'id' => $product->id,
                    'name' => $product->description,
                    'code' => filled(trim((string) ($product->code ?? ''))) ? trim((string) $product->code) : null,
                    'img' => $imageUrl,
                    'category' => $product->category ? $product->category->description : 'Sin categoría',
                    'category_id' => $product->category_id,
                    'detail_options' => collect($product->detail_options ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
                    'table_id' => null,
                    'branch_id' => $branchId,
                ];
            });

        $productBranches = $branchId
            ? ProductBranch::with(['product.productType', 'taxRate', 'printers'])
            ->where('branch_id', $branchId)
            ->whereIn('product_id', function ($q) {
                $q->select('id')->from('products')
                  ->whereNull('deleted_at')
                  ->where(function ($sub) {
                      $sub->whereNull('product_type_id')
                          ->orWhereIn('product_type_id', function ($tSub) {
                              $tSub->select('id')->from('product_types')
                                  ->whereIn('behavior', [ProductType::BEHAVIOR_SELLABLE, ProductType::BEHAVIOR_BOTH])
                                  ->whereNull('deleted_at');
                          });
                  });
            })
            ->get()
            ->map(function ($productBranch) {
                $taxRatePct = $productBranch->taxRate ? (float) $productBranch->taxRate->tax_rate : null;
                $printerNames = $productBranch->printers
                    ->pluck('name')
                    ->map(fn($n) => trim((string) $n))
                    ->filter(fn($n) => $n !== '')
                    ->values()
                    ->all();
                $printers = $productBranch->printers
                    ->map(function ($p) {
                        $name = trim((string) ($p->name ?? ''));
                        $widthRaw = trim((string) ($p->width ?? ''));
                        if ($name === '') {
                            return null;
                        }

                        return [
                            'name' => $name,
                            'width' => $widthRaw !== '' ? $widthRaw : null,
                            'ip' => filled(trim((string) ($p->ip ?? ''))) ? trim((string) $p->ip) : null,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $productBranch->id,
                    'product_id' => $productBranch->product_id,
                    'price' => (float) $productBranch->price,
                    'stock' => (float) ($productBranch->stock ?? 0),
                    'tax_rate' => $taxRatePct,
                    'favorite' => ($productBranch->favorite ?? 'N'),
                    'qz_printer_name' => request()->ip() === '127.0.0.1' || request()->ip() === '::1' ? ($printerNames[0] ?? null) : 'BARRA2',
                    'qz_printer_names' => $printerNames,
                    'qz_printers' => $printers,
                ];
            })
            ->values()
            : collect();

        $categories = Category::query()
            ->when($branchId, fn($q) => $q->forBranchMenu($branchId, 'VENTAS_PEDIDOS'), function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->orderBy('description')
            ->get();

        $units = Unit::query()->orderBy('description')->get();

        $pendingOrder = OrderMovement::with(['movement', 'details'])
            ->where('branch_id', $branchId)
            ->whereNull('table_id')
            ->whereHas('movement', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereIn('status', ['PENDIENTE', 'P'])
            ->orderByDesc('id')
            ->first();
        $startFresh = ! $pendingOrder;

        $pendingClientId = $pendingOrder?->movement?->person_id;
        $pendingClientName = $pendingOrder?->movement?->person_name;

        $pendingItemsRaw = $pendingOrder
            ? $this->mapOrderDetailsToPendingPosItems($pendingOrder)
            : [];

        $pendingItems = collect($pendingItemsRaw)->groupBy(function ($item) {
            $complements = collect($item['complements'] ?? [])->map(fn($value) => trim((string) $value))->filter()->values()->all();
            sort($complements);

            return implode('|', [
                (int) ($item['pId'] ?? 0),
                md5(json_encode($complements)),
                trim((string) ($item['note'] ?? '')),
            ]);
        })->map(function ($group) {
            $first = $group->first();

            $sumQty = $group->sum('qty');
            $sumTakeaway = $group->sum('takeawayQty');

            $sumPaid = 0.0;
            $sumWeighted = 0.0;
            foreach ($group as $row) {
                $q = (float) ($row['qty'] ?? 0);
                $cq = (float) ($row['courtesyQty'] ?? 0);
                $cq = max(0, min($cq, $q));
                $paid = max(0, $q - $cq);
                $p = (float) ($row['price'] ?? 0);
                $sumPaid += $paid;
                $sumWeighted += $p * $paid;
            }
            $mergedPrice = $sumPaid > 0 ? round($sumWeighted / $sumPaid, 6) : (float) ($first['price'] ?? 0);

            return [
                'pId' => $first['pId'],
                'name' => $first['name'],
                'qty' => $sumQty,
                'price' => $mergedPrice,
                'tax_rate' => $first['tax_rate'] ?? 10,
                'priceManual' => true,
                'note' => $first['note'],
                'commandTime' => $first['commandTime'],
                'delivered' => $group->contains('delivered', true),
                'courtesyQty' => $group->sum('courtesyQty'),
                'takeawayQty' => min($sumTakeaway, $sumQty),
                'complements' => $first['complements'] ?? [],
            ];
        })->values()->all();

        $pendingCancelledDetails = $pendingOrder
            ? collect($pendingOrder->details->where('status', 'C')->map(fn($d) => [
                'product_id' => (int) ($d->product_id ?? 0),
                'description' => $d->description ?? 'Producto',
                'quantity' => (float) $d->quantity,
                'comment' => $d->comment ?? '',
                'complements' => collect($d->complements ?? [])->map(fn($item) => trim((string) $item))->filter()->values()->all(),
            ]))
            ->groupBy(function ($item) {
                $complements = collect($item['complements'] ?? [])->map(fn($value) => trim((string) $value))->filter()->values()->all();
                sort($complements);

                return implode('|', [(int) ($item['product_id'] ?? 0), md5(json_encode($complements))]);
            })
            ->map(fn($group) => [
                'description' => $group->first()['description'],
                'quantity' => $group->sum('quantity'),
                'comment' => $group->first()['comment'],
                'complements' => $group->first()['complements'] ?? [],
            ])
            ->values()
            ->all()
            : [];

        $saleOrOrderTypeIds = MovementType::query()
            ->where(function ($q) {
                $q->where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%sale%')
                    ->orWhere('description', 'like', '%pedido%')
                    ->orWhere('description', 'like', '%orden%');
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->whereIn('movement_type_id', ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2])
            ->get(['id', 'name']);
        $defaultDocumentTypeId = effective_default_sale_document_type_id($branchId, ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2]);
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId)
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
            ->where('status', '1')
            ->orderBy('number')
            ->get(['id', 'number']);

        $deliveryAreaId = null;
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

        $viewId = $request->query('view_id');
        $salesIndexUrl = route('sales.index', $viewId ? ['view_id' => $viewId] : []);
        $posStorageKey = 'counter-b' . $branchId . '-u' . (int) $userId;
        $recipeStockData = $this->buildRecipeStockData($branchId, $branch?->company_id);
        $areaPrinterNames = $branchId
            ? Area::query()
            ->where('branch_id', $branchId)
            ->with(['printers' => fn($q) => $q->select('printers_branch.id', 'name')])
            ->get()
            ->mapWithKeys(function ($a) {
                $names = $a->printers
                    ->pluck('name')
                    ->map(fn($n) => trim((string) $n))
                    ->filter(fn($n) => $n !== '')
                    ->values()
                    ->all();
                return [(int) $a->id => $names];
            })
            ->all()
            : [];

        // Mostrador: venta directa (no OrderMovement / pedido). Carrito en cliente; "Guardar" abre /ventas/cobrar.
        $pendingOrder = null;
        $pendingItems = [];
        $pendingCancelledDetails = [];
        $pendingOrderMovementId = null;
        $pendingMovementId = null;
        $pendingClientId = null;
        $pendingClientName = null;
        $startFresh = true;

        $response = response()->view('orders.create', array_merge([
            'user' => $user,
            'person' => $person,
            'profile' => $profile,
            'branch' => $branch,
            'area' => $area,
            'table' => $table,
            'products' => $products,
            'productBranches' => $productBranches,
            'categories' => $categories,
            'people' => $people,
            'waiters' => $waiters,
            'units' => $units,
            'startFresh' => $startFresh,
            'pendingOrderMovementId' => $pendingOrder?->id,
            'pendingMovementId' => $pendingOrder?->movement_id,
            'pendingClientId' => $pendingClientId,
            'pendingClientName' => $pendingClientName,
            'pendingWaiterId' => $pendingOrder?->movement?->person_id ?? session('waiter_person_id'),
            'pendingWaiterName' => $pendingOrder?->movement?->responsible_name ?? session('waiter_name'),
            'pendingPeopleCount' => (int) ($pendingOrder?->people_count ?: 1),
            'pendingCancelledDetails' => $pendingCancelledDetails,
            'pendingItems' => $pendingItems,
            'pendingServiceType' => $pendingOrder?->service_type ?? 'IN_SITU',
            'pendingDeliveryAddress' => $pendingOrder?->delivery_address,
            'pendingContactPhone' => $pendingOrder?->contact_phone,
            'pendingDeliveryAmount' => $pendingOrder?->delivery_amount ?? 0,
            'pendingTakeawayDisposableAmount' => (float) ($pendingOrder?->takeaway_disposable_amount ?? 0),
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'banks' => $banks,
            'cashRegisters' => $cashRegisters,
            'waiterPinEnabled' => $this->shouldRequireWaiterPin((int) $branchId, $profileId),
            'deliveryAreaId' => $deliveryAreaId,
            'canCharge' => $this->canCharge($profileId),
            'isMozo' => Profile::userHasMozoProfile(
                $profileId !== null && $profileId !== '' ? (int) $profileId : null
            ),
            'departments' => $departments,
            'provinces' => $provinces,
            'districts' => $districts,
            'turboCacheControl' => 'no-cache',
            'allowZeroStockSales' => (bool) ($branch?->allow_zero_stock_sales ?? true),
            'recipeStockData' => $recipeStockData,
            'areaPrinterNames' => $areaPrinterNames,
            'isCounterSale' => true,
            'posStorageKey' => $posStorageKey,
            'afterPaymentIndexUrl' => $salesIndexUrl,
            'viewId' => $viewId,
        ], $this->splitAccountViewData($pendingOrder)));
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    public function charge(Request $request)
    {
        $profileId = session('profile_id') ?? $request->user()?->profile_id;
        if (Profile::userHasMozoProfile(
            $profileId !== null && $profileId !== '' ? (int) $profileId : null
        )) {
            return redirect()
                ->route('orders.index')
                ->with('error', 'Tu perfil (Mozo) no tiene permiso para cobrar. Solo puedes guardar pedidos.');
        }

        $saleOrOrderTypeIds = MovementType::query()
            ->where(function ($q) {
                $q->where('description', 'like', '%venta%')
                    ->orWhere('description', 'like', '%sale%')
                    ->orWhere('description', 'like', '%pedido%')
                    ->orWhere('description', 'like', '%orden%');
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
        $documentTypes = DocumentType::query()
            ->orderBy('name')
            ->whereIn('movement_type_id', ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2])
            ->get(['id', 'name']);
        $branchIdForPm = (int) session('branch_id');
        $defaultDocumentTypeId = effective_default_sale_document_type_id($branchIdForPm ?: null, ! empty($saleOrOrderTypeIds) ? $saleOrOrderTypeIds : [2]);

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchIdForPm ?: null)
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

        // Si se pasa un movement_id, cargar la orden pendiente (pedido o venta)
        $draftOrder = null;
        $pendingAmount = 0;
        $movement = null;
        $table = null;

        if ($request->has('movement_id')) {
            $movement = Movement::with(['salesMovement.details.product', 'cashMovement', 'orderMovement.details', 'orderMovement.table'])
                ->where('id', $request->movement_id)
                ->whereIn('status', ['P', 'A'])
                ->first();

            if ($movement && $movement->orderMovement) {
                $table = $movement->orderMovement->table;
            }
            if (! $table && $request->filled('table_id')) {
                $table = Table::find($request->table_id);
            }

            // Pedido: OrderMovement + detalles (subtotal/tax/total del pedido para que la vista de cobro no recalcule y coincida con la tarjeta de la mesa)
            if ($movement && $movement->orderMovement) {
                $om = $movement->orderMovement;
                $details = $om->details ?? collect();
                $omSubtotal = (float) $om->subtotal;
                $omTax = (float) ($om->tax ?? 0);
                $omTotal = (float) ($om->total ?? ($omSubtotal + $omTax));
                $draftOrder = [
                    'id' => $movement->id,
                    'number' => $movement->number,
                    'items' => $details->map(function ($detail) {
                        $qty = (float) $detail->quantity;
                        $courtesyQty = (float) ($detail->courtesy_quantity ?? 0);
                        $courtesyQty = max(0, min($courtesyQty, $qty));
                        $paidQty = max(0, $qty - $courtesyQty);
                        $amount = (float) $detail->amount;
                        $unit = $paidQty > 0 ? ($amount / $paidQty) : 0;

                        return [
                            'pId' => $detail->product_id,
                            'name' => $detail->description ?? 'Producto #' . $detail->product_id,
                            'qty' => $qty,
                            'price' => round($unit, 6),
                            'note' => $detail->comment ?? '',
                        ];
                    })->toArray(),
                    'clientName' => $movement->person_name ?? 'Público General',
                    'notes' => $movement->comment ?? '',
                    'pendingAmount' => $pendingAmount,
                    'subtotal' => round($omSubtotal, 2),
                    'tax' => round($omTax, 2),
                    'total' => round($omTotal, 2),
                ];
                $draftOrder = array_merge($draftOrder, $this->splitAccountViewData($om));
            }
            // Venta: SalesMovement + detalles
            if (! $draftOrder && $movement && $movement->salesMovement) {
                if ($movement->cashMovement) {
                    $debt = DB::table('cash_movement_details')
                        ->where('cash_movement_id', $movement->cashMovement->id)
                        ->where('type', 'DEUDA')
                        ->where('status', 'A')
                        ->sum('amount');
                    $pendingAmount = $debt ?? 0;
                }
                $draftOrder = [
                    'id' => $movement->id,
                    'number' => $movement->number,
                    'items' => $movement->salesMovement->details->map(function ($detail) {
                        return [
                            'pId' => $detail->product_id,
                            'name' => $detail->product->description ?? 'Producto #' . $detail->product_id,
                            'qty' => (float) $detail->quantity,
                            'price' => (float) $detail->original_amount / (float) $detail->quantity,
                            'note' => $detail->comment ?? '',
                        ];
                    })->toArray(),
                    'clientName' => $movement->person_name ?? 'Público General',
                    'notes' => $movement->comment ?? '',
                    'pendingAmount' => $pendingAmount,
                ];
            }
        }

        // Obtener todos los productos para poder mostrar sus nombres cuando se carga desde localStorage
        $products = Product::pluck('description', 'id')->toArray();

        $viewId = $request->input('view_id');
        $fromList = $request->input('from') === 'list';
        $backUrl = ($fromList || $viewId)
            ? route('orders.list', $viewId ? ['view_id' => $viewId] : [])
            : route('orders.index', $viewId ? ['view_id' => $viewId] : []);

        return view('orders.charge', [
            'documentTypes' => $documentTypes,
            'defaultDocumentTypeId' => $defaultDocumentTypeId,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'banks' => $banks,
            'draftOrder' => $draftOrder,
            'pendingAmount' => $pendingAmount,
            'products' => $products,
            'backUrl' => $backUrl,
            'movement' => $movement,
            'table' => $table,
            'turboCacheControl' => 'no-cache',
        ]);
    }

    public function moveTable(Request $request)
    {
        $sourceTableId = $request->input('table_id');       // mesa origen (ocupada)
        $destTableId = $request->input('new_table_id');   // mesa destino (libre)

        if (! $sourceTableId || ! $destTableId) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar una mesa destino.',
            ], 422);
        }

        if ((int) $sourceTableId === (int) $destTableId) {
            return response()->json([
                'success' => false,
                'message' => 'La mesa destino debe ser diferente a la mesa origen.',
            ], 422);
        }

        $sourceTable = Table::find($sourceTableId);
        $destTable = Table::find($destTableId);

        if (! $sourceTable) {
            return response()->json(['success' => false, 'message' => 'Mesa origen no encontrada.'], 404);
        }
        if (! $destTable) {
            return response()->json(['success' => false, 'message' => 'Mesa destino no encontrada.'], 404);
        }

        $destSituation = strtolower(trim((string) ($destTable->situation ?? '')));
        if ($destSituation === 'ocupada') {
            return response()->json([
                'success' => false,
                'message' => 'La mesa destino ya está ocupada.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Transferir el pedido pendiente de la mesa origen a la mesa destino
            OrderMovement::where('table_id', $sourceTableId)
                ->whereIn('status', ['PENDIENTE', 'P'])
                ->update([
                    'table_id' => $destTableId,
                    'area_id' => $destTable->area_id,
                ]);

            // Liberar mesa origen
            Table::where('id', $sourceTableId)->update([
                'situation' => 'libre',
                'opened_at' => null,
            ]);

            // Ocupar mesa destino (conservar opened_at si ya tenía)
            $destOpenedAt = $destTable->opened_at ?? now();
            Table::where('id', $destTableId)->update([
                'situation' => 'ocupada',
                'opened_at' => $destOpenedAt,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pedido movido a la mesa ' . ($destTable->name ?? $destTableId),
                'new_table_id' => $destTableId,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function processOrder(Request $request)
    {
        $items = $request->input('items', []);
        $cancellations = (array) $request->input('cancellations', []);
        $branchId = session('branch_id');
        $branch = Branch::findOrFail($branchId);

        $user = $request->user();
        $profileId = session('profile_id') ?? $user?->profile_id;
        $waiterPinEnabled = $this->shouldRequireWaiterPin($branchId ? (int) $branchId : null, $profileId);
        $isMozoProfile = Profile::userHasMozoProfile(
            $profileId !== null && $profileId !== '' ? (int) $profileId : null
        );
        $waiterIdFrontend = $request->input('waiter_id');
        $responsibleId = $user?->id; // default
        if ($isMozoProfile) {
            $waiterPersonId = (int) ($user?->person?->id ?? 0);
            $waiterName = trim(($user?->person?->first_name ?? '') . ' ' . ($user?->person?->last_name ?? ''));
            if ($waiterName === '') {
                $waiterName = $user?->name ?? 'Mozo';
            }
        } elseif ($waiterIdFrontend) {
            $waiterPersonId = (int) $waiterIdFrontend;
            $waiterPerson = Person::find($waiterPersonId);
            $waiterName = $waiterPerson ? trim(($waiterPerson->first_name ?? '') . ' ' . ($waiterPerson->last_name ?? '')) : 'Mozo';
            $responsibleUser = User::where('person_id', $waiterPersonId)->first();
            if ($responsibleUser) {
                $responsibleId = $responsibleUser->id;
            }
        } else {
            $waiterPersonId = $waiterPinEnabled ? (int) $request->session()->get('waiter_person_id') : (int) ($user?->person?->id ?? 0);
            $waiterName = $waiterPinEnabled ? (string) $request->session()->get('waiter_name') : trim(($user?->person?->first_name ?? '') . ' ' . ($user?->person?->last_name ?? ''));
            // responsible_name = empleado que insertó el PIN (Person), nunca el usuario (User)
            if ($waiterPersonId) {
                $waiterPerson = Person::find($waiterPersonId);
                $resolvedName = $waiterPerson ? trim(($waiterPerson->first_name ?? '') . ' ' . ($waiterPerson->last_name ?? '')) : '';
                $waiterName = $resolvedName !== '' ? $resolvedName : ($waiterPerson ? 'Mozo' : $waiterName);
                $responsibleUser = User::where('person_id', $waiterPersonId)->first();
                if ($responsibleUser) {
                    $responsibleId = $responsibleUser->id;
                }
            }
            if (trim((string) $waiterName) === '' && !$waiterPinEnabled) {
                $waiterName = $user?->name ?? 'Sistema';
            }
        }
        if ($waiterPinEnabled && ! $isMozoProfile && ! $waiterPersonId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe ingresar el PIN del mozo.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Debe ingresar el PIN del mozo.');
        }

        $hasCancellationQty = collect($cancellations)->contains(function ($c) {
            return ((float) ($c['qtyCanceled'] ?? $c['quantity'] ?? 0)) > 0;
        });
        if ($isMozoProfile && $hasCancellationQty) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El perfil Mozo no puede anular productos ya enviados a cocina.',
                ], 403);
            }

            return redirect()->back()->with('error', 'El perfil Mozo no puede anular productos ya enviados a cocina.');
        }

        // Subtotal: usar el enviado por el front o recalcular desde items
        $subtotal = $request->has('subtotal') ? (float) $request->subtotal : 0;
        if ($subtotal == 0 && ! empty($items)) {
            foreach ($items as $rawItem) {
                $qty = (float) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 1);
                $price = (float) ($rawItem['price'] ?? 0);
                $subtotal += $qty * $price;
            }
        }
        $subtotal = round($subtotal, 6);

        // Tax y total: usar los enviados por el front o calcular (10% impuesto)
        $tax = $request->has('tax') ? (float) $request->tax : round($subtotal * 0.10, 6);
        $total = $request->has('total') ? (float) $request->total : round($subtotal + $tax, 6);
        $tax = round($tax, 6);
        $total = round($total, 6);

        $tableId = $request->filled('table_id') ? $request->table_id : null;
        $areaId = $request->filled('area_id') ? $request->area_id : null;
        $peopleCount = max(0, (int) $request->input('people_count', 0));
        $deliveryAmount = round((float) ($request->input('delivery_amount', 0) ?: 0), 6);
        $serviceType = strtoupper((string) $request->input('service_type', 'IN_SITU'));
        if ($serviceType === 'DELIVERY' && $deliveryAmount > 0) {
            $total = round($total + $deliveryAmount, 6);
        }
        if ($serviceType !== 'DELIVERY') {
            $deliveryAmount = 0;
        }

        $chargeDisposable = filter_var($request->input('takeaway_disposable_charge'), FILTER_VALIDATE_BOOLEAN);
        $takeawayDisposableAmount = round((float) ($request->input('takeaway_disposable_amount', 0) ?: 0), 6);
        if ($takeawayDisposableAmount < 0) {
            $takeawayDisposableAmount = 0;
        }

        $hasTakeawayContext = $serviceType === 'TAKE_AWAY';
        if (! $hasTakeawayContext && ! empty($items)) {
            foreach ($items as $rawItem) {
                $qty = (float) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 1);
                $tw = (float) ($rawItem['takeawayQty'] ?? $rawItem['takeaway_quantity'] ?? 0);
                if ($tw > 0 && $tw <= $qty) {
                    $hasTakeawayContext = true;
                    break;
                }
            }
        }

        $takeawayDisposableStored = 0;
        if ($serviceType === 'DELIVERY' || ! $chargeDisposable || ! $hasTakeawayContext) {
            $takeawayDisposableAmount = 0;
        } else {
            $takeawayDisposableStored = $takeawayDisposableAmount;
            if ($takeawayDisposableAmount > 0) {
                $total = round($total + $takeawayDisposableAmount, 6);
            }
        }

        $clientPersonId = $request->filled('client_id') ? (int) $request->client_id : null;
        $clientNameFromRequest = $request->filled('client_name') ? trim((string) $request->client_name) : null;
        $clientPerson = $this->resolveOrCreateClientPerson($branchId ? (int) $branchId : null, $branch, $clientPersonId, $clientNameFromRequest);
        $clientName = $clientPerson
            ? trim(($clientPerson->first_name ?? '') . ' ' . ($clientPerson->last_name ?? ''))
            : ($clientNameFromRequest ?: 'Público General');

        DB::beginTransaction();

        try {
            // Prioridad 1: si el front envía order_movement_id, actualizar ese pedido (evita crear duplicado al auto-guardar)
            $existingOrderMovement = null;
            if ($request->filled('order_movement_id')) {
                $byId = OrderMovement::where('id', (int) $request->order_movement_id)
                    ->whereIn('status', ['PENDIENTE', 'P'])
                    ->first();
                if ($byId) {
                    // Si el pedido se movió de mesa, el front puede seguir enviando el table_id antiguo.
                    // Priorizamos el pedido por ID para evitar duplicados y "retornos" a la mesa anterior.
                    $existingOrderMovement = $byId;
                    $tableId = $byId->table_id;
                    $areaId = $byId->area_id;
                }
            }

            $existingOrderMovementForPeople = null;
            if ($request->filled('order_movement_id')) {
                $existingOrderMovementForPeople = OrderMovement::where('id', (int) $request->order_movement_id)
                    ->whereIn('status', ['PENDIENTE', 'P'])
                    ->first();
            } elseif ($tableId) {
                $existingOrderMovementForPeople = OrderMovement::where('table_id', $tableId)
                    ->whereIn('status', ['PENDIENTE', 'P'])
                    ->orderByDesc('id')
                    ->first();
            }

            // Si el front manda un valor (>0), úsalo tal cual; si no, conserva el que ya tenía el pedido;
            // y solo si tampoco hay, usa la capacidad de la mesa (pero sin limitar hacia arriba).
            $rawPeopleFromRequest = (int) $request->input('people_count', 0);
            if ($rawPeopleFromRequest > 0) {
                $peopleCount = $rawPeopleFromRequest;
            } elseif ($existingOrderMovementForPeople && $existingOrderMovementForPeople->people_count > 0) {
                $peopleCount = (int) $existingOrderMovementForPeople->people_count;
            } else {
                $capTable = $tableId ? Table::find($tableId) : null;
                $peopleCount = (int) ($capTable->capacity ?? 1);
            }

            // Prioridad 2: si no, buscar por mesa
            if (! $existingOrderMovement && $tableId) {
                $existingOrderMovement = OrderMovement::where('table_id', $tableId)
                    ->whereIn('status', ['PENDIENTE', 'P'])
                    ->orderByDesc('id')
                    ->first();
            }

            // Mostrador / venta directa (sin mesa): pedido pendiente del usuario en esta sucursal
            if (! $existingOrderMovement && ! $tableId && $branchId && $user) {
                $existingOrderMovement = OrderMovement::where('branch_id', $branchId)
                    ->whereNull('table_id')
                    ->whereHas('movement', fn($q) => $q->where('user_id', $user->id))
                    ->whereIn('status', ['PENDIENTE', 'P'])
                    ->orderByDesc('id')
                    ->first();
            }

            // Si ya hay pedido pendiente en la mesa pero items vacío: no crear duplicado (p. ej. al guardar sin productos al ir a cobrar)
            if ($existingOrderMovement && empty($items) && empty($cancellations)) {
                DB::commit();
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Pedido pendiente en la mesa.',
                        'movement_id' => $existingOrderMovement->movement_id,
                        'order_movement_id' => $existingOrderMovement->id,
                    ]);
                }

                return redirect()->route('orders.index')->with('status', 'Pedido pendiente en la mesa.');
            }

            // Sin mesa (mostrador): no crear pedido vacío sin borrador previo
            if (! $tableId && ! $request->filled('order_movement_id') && empty($items) && empty($cancellations)) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Agregue productos al pedido.',
                    ], 422);
                }

                return redirect()->back()->with('error', 'Agregue productos al pedido.');
            }

            // Mesa concreta pero sin items y sin pedido previo: no crear pedido vacío
            if ($tableId && empty($items) && empty($cancellations)) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Agregue productos al pedido.',
                    ], 422);
                }

                return redirect()->back()->with('error', 'Agregue productos al pedido.');
            }

            if ($existingOrderMovement) {
                // Antes de borrar y recrear detalles: snapshot para delta de stock/kardex (si no, previous queda en 0 y se vuelve a descontar todo).
                $previousCommittedQtyByProduct = $existingOrderMovement->details()
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'C');
                    })
                    ->get(['product_id', 'quantity'])
                    ->groupBy('product_id')
                    ->map(function ($rows) {
                        return (float) $rows->sum('quantity');
                    });

                // ACTUALIZAR pedido existente (aunque quede sin items, para registrar cancelaciones)
                $existingOrderMovement->update([
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'people_count' => $peopleCount,
                    'delivery_amount' => $deliveryAmount,
                    'takeaway_disposable_amount' => $takeawayDisposableStored,
                    'service_type' => $serviceType,

                    'contact_phone' => $request->filled('contact_phone') ? $request->contact_phone : null,
                    'delivery_address' => $request->filled('delivery_address') ? $request->delivery_address : null,
                    'delivery_time' => $request->filled('delivery_time') ? $request->delivery_time : null,
                ]);

                $existingOrderMovement->movement?->update([
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $clientPerson?->id,
                    'person_name' => $clientName,
                    'responsible_id' => $responsibleId,

                    'responsible_name' => $waiterName ?: (($user?->person?->first_name ?? '') . ' ' . ($user?->person?->last_name ?? '-')),
                ]);

                // Eliminar detalles antiguos ACTIVOS y crear los nuevos (mantener histórico de cancelaciones status='C')
                $existingOrderMovement->details()
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'C');
                    })
                    ->forceDelete();

                $orderMovement = $existingOrderMovement;
                $movement = $orderMovement->movement;
            } else {
                // CREAR nuevo pedido
                $documentType = $this->resolveOrderDocumentType();
                $movementType = $documentType->movementType ?? MovementType::query()->findOrFail($documentType->movement_type_id);

                $number = $this->generateOrderMovementNumber(
                    (int) $branchId,
                    (int) $movementType->id,
                    (int) $documentType->id
                );

                $movement = Movement::create([
                    'number' => $number,
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $clientPerson?->id,
                    'person_name' => $clientName,
                    'responsible_id' => $responsibleId,
                    'responsible_name' => $waiterName ?: (($user?->person?->first_name ?? '') . ' ' . ($user?->person?->last_name ?? '-')),
                    'comment' => 'Pedido ',
                    'status' => 'A',
                    'movement_type_id' => $movementType->id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                    'parent_movement_id' => null,
                ]);

                $orderMovement = OrderMovement::create([
                    'currency' => 'PEN',
                    'exchange_rate' => 1,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'people_count' => $peopleCount,
                    'finished_at' => null,
                    'table_id' => $tableId,
                    'area_id' => $areaId,
                    'delivery_amount' => $deliveryAmount,
                    'takeaway_disposable_amount' => $takeawayDisposableStored,
                    'contact_phone' => $request->filled('contact_phone') ? $request->contact_phone : null,
                    'delivery_address' => $request->filled('delivery_address') ? $request->delivery_address : null,
                    'delivery_time' => $request->filled('delivery_time') ? $request->delivery_time : null,
                    'service_type' => $request->input('service_type', 'IN_SITU'),
                    'status' => 'PENDIENTE',
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                ]);

                $previousCommittedQtyByProduct = collect();
            }

            $newCommittedQtyByProduct = collect($items)
                ->groupBy(function ($rawItem) {
                    return (int) ($rawItem['product_id'] ?? $rawItem['pId'] ?? 0);
                })
                ->map(function ($rows) {
                    return (float) collect($rows)->sum(function ($rawItem) {
                        return (float) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 0);
                    });
                })
                ->filter(fn($qty, $productId) => (int) $productId > 0);

            if (empty($items)) {
                $orderMovement->update(['status' => 'CANCELADO', 'finished_at' => now()]);
            }

            // Actualizar el estado de la mesa si está vinculada
            if ($tableId) {
                if (empty($items)) {
                    Table::where('id', $tableId)->update([
                        'situation' => 'libre',
                        'opened_at' => null,
                    ]);
                } else {
                    $existingOpenedAt = Table::where('id', $tableId)->value('opened_at');
                    Table::where('id', $tableId)->update([
                        'situation' => 'ocupada',
                        'opened_at' => $existingOpenedAt ?? now(),
                    ]);
                }
            }

            foreach ($items as $rawItem) {
                $productId = $rawItem['product_id'] ?? $rawItem['pId'] ?? null;
                $product = $productId ? Product::find($productId) : null;

                $qty = (float) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 1);
                $price = (float) ($rawItem['price'] ?? 0);
                $courtesyQty = (float) ($rawItem['courtesyQty'] ?? $rawItem['courtesy_quantity'] ?? 0);
                $courtesyQty = max(0, min($courtesyQty, $qty));
                $paidQty = $qty - $courtesyQty;
                $amount = $paidQty * $price;

                $takeawayQty = (float) ($rawItem['takeawayQty'] ?? $rawItem['takeaway_quantity'] ?? 0);
                $takeawayQty = max(0, min($takeawayQty, $qty));

                $unitId = $rawItem['unit_id'] ?? ($product?->unit_id ?? null);
                if (! $unitId) {
                    $unitId = Unit::query()->value('id'); // unidad por defecto
                }

                $code = $rawItem['code'] ?? ($product?->code ?? (string) $productId);
                $description = $rawItem['description'] ?? ($product?->description ?? ($rawItem['name'] ?? 'Producto'));

                // Validar stock disponible si no se permite vender sin stock
                $productBranch = ProductBranch::where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->first();
                $currentStock = (float) ($productBranch->stock ?? 0);
                $previousCommittedQty = (float) ($previousCommittedQtyByProduct->get((int) $productId, 0));
                $newCommittedQty = (float) ($newCommittedQtyByProduct->get((int) $productId, 0));
                $deltaQty = $newCommittedQty - $previousCommittedQty;
                $roundUpToQuarter = function (float $qty): float {
                    if ($qty <= 0) {
                        return 0.0;
                    }
                    return round(ceil($qty * 4) / 4, 6);
                };

                if ($deltaQty > 0) {
                    $recipe = Recipe::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', (int) ($productId ?? 0))
                        ->where('status', 'A')
                        ->with(['ingredients'])
                        ->first();

                    // Si tiene receta, validar por ingredientes; si no, validar por producto final.
                    if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
                        $yield = (float) $recipe->yield_quantity;
                        foreach ($recipe->ingredients as $ingredient) {
                            $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                            $ingredientQty = (float) ($ingredient->quantity ?? 0);
                            if ($ingredientProductId <= 0 || $ingredientQty <= 0) {
                                continue;
                            }

                            $rawConsumption = ($ingredientQty / $yield) * $deltaQty;
                            $needed = $roundUpToQuarter($rawConsumption);
                            if ($needed <= 0) {
                                continue;
                            }

                            $ingredientBranch = ProductBranch::query()
                                ->where('product_id', $ingredientProductId)
                                ->where('branch_id', $branchId)
                                ->first();
                            $ingredientStock = (float) ($ingredientBranch?->stock ?? 0);
                            if ($ingredientStock < $needed) {
                                $ingredientName = Product::query()->where('id', $ingredientProductId)->value('description') ?? ('Ingrediente #' . $ingredientProductId);
                                throw new \Exception(
                                    "Stock insuficiente para el ingrediente \"{$ingredientName}\". " .
                                        "Stock disponible: {$ingredientStock}, Requerido: {$needed}"
                                );
                            }
                        }
                    } else {
                        if (! $branch->allow_zero_stock_sales && $currentStock < $deltaQty) {
                            throw new \Exception(
                                "Stock insuficiente para el producto \"{$description}\". " .
                                    "Stock disponible: {$currentStock}, Cantidad solicitada: {$deltaQty}"
                            );
                        }
                    }
                }

                $rawNote = trim((string) ($rawItem['note'] ?? ''));
                $comment = $rawNote !== '' ? $rawNote : null;
                $complements = collect($rawItem['complements'] ?? [])
                    ->map(fn($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();
                $commandTime = $rawItem['commandTime'] ?? null;
                $commandedAt = null;
                if ($commandTime && preg_match('/^\\d{1,2}:\\d{2}(?::\\d{2})?/', $commandTime)) {
                    $commandedAt = \Carbon\Carbon::parse('today ' . $commandTime);
                }
                if (! $commandedAt) {
                    $commandedAt = now();
                }

                $delivered = ! empty($rawItem['delivered']);
                OrderMovementDetail::create([
                    'order_movement_id' => $orderMovement->id,
                    'product_id' => $productId,
                    'code' => $code,
                    'description' => $description,
                    'product_snapshot' => $product ? $product->toArray() : null,
                    'unit_id' => $unitId,
                    'tax_rate_id' => $rawItem['tax_rate_id'] ?? null,
                    'tax_rate_snapshot' => $rawItem['tax_rate_snapshot'] ?? null,
                    'quantity' => $qty,
                    'courtesy_quantity' => $courtesyQty,
                    'takeaway_quantity' => $takeawayQty,
                    'amount' => $amount,
                    'branch_id' => $branchId,
                    'comment' => $comment,
                    'complements' => $complements,
                    'commanded_at' => $commandedAt,
                    'status' => $delivered ? 'E' : 'A',
                ]);
            }

            // Registrar cancelaciones por plato como detalles con estado 'C'
            foreach ($cancellations as $rawCancel) {
                $productId = $rawCancel['product_id'] ?? $rawCancel['pId'] ?? null;
                $product = $productId ? Product::find($productId) : null;

                $qty = (float) ($rawCancel['qtyCanceled'] ?? $rawCancel['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $price = (float) ($rawCancel['price'] ?? 0);
                $amount = $qty * $price;

                $unitId = $rawCancel['unit_id'] ?? ($product?->unit_id ?? null);
                if (! $unitId) {
                    $unitId = Unit::query()->value('id'); // unidad por defecto
                }

                $code = $rawCancel['code'] ?? ($product?->code ?? (string) $productId);
                $description = $rawCancel['description'] ?? ($product?->description ?? ($rawCancel['name'] ?? 'Producto'));

                $productSnapshot = $rawCancel['product_snapshot'] ?? null;
                if (! is_array($productSnapshot) && $product) {
                    $productSnapshot = $product->toArray();
                }
                $cancelComplements = collect($rawCancel['complements'] ?? [])
                    ->map(fn($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();

                OrderMovementDetail::create([
                    'order_movement_id' => $orderMovement->id,
                    'product_id' => $productId,
                    'code' => $code,
                    'description' => $description,
                    'product_snapshot' => $productSnapshot,
                    'unit_id' => $unitId,
                    'tax_rate_id' => $rawCancel['tax_rate_id'] ?? null,
                    'tax_rate_snapshot' => $rawCancel['tax_rate_snapshot'] ?? null,
                    'quantity' => $qty,
                    'courtesy_quantity' => 0,
                    'amount' => $amount,
                    'branch_id' => $branchId,
                    'comment' => $rawCancel['cancel_reason'] ?? null,
                    'complements' => $cancelComplements,
                    'status' => 'C',
                ]);
            }

            $this->applyCommittedStockDelta(
                $branch,
                (int) $branchId,
                $previousCommittedQtyByProduct,
                $newCommittedQtyByProduct
            );

            app(KardexSyncService::class)->syncMovement($movement);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pedido guardado correctamente',
                    'movement_id' => $movement->id,
                    'order_movement_id' => $orderMovement->id,
                    'client_person_id' => $clientPerson?->id,
                    'client_name' => $clientName,
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al procesar pedido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()->route('orders.index')->with('error', 'Error al procesar el pedido');
        }
    }

    public function printKitchenTicketThermal(Request $request)
    {
        if (! config('local_network.thermal_print_enabled', true)) {
            abort(404);
        }

        if (! LocalNetworkClient::isOnLocalNetwork($request)) {
            return response()->json([
                'success' => false,
                'message' => 'La impresión por red desde el servidor solo está permitida dentro de la red del local.',
            ], 403);
        }

        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'printer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json([
                'success' => false,
                'message' => 'Sin sucursal en sesión.',
            ], 422);
        }

        $printerBaseQuery = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->where('status', 'E');

        $host = strtolower(trim($request->getHost() ?: ''));
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
        $defaultPrinterName = $isLocalhost ? 'barra' : 'barra2';

        $requestedName = trim((string) ($validated['printer_name'] ?? '')) ?: $defaultPrinterName;
        $printer = $this->findPrinterBranchForBarTicket($printerBaseQuery, $requestedName, $defaultPrinterName);
        if (! $printer) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una ticketera de barra configurada para la comanda.',
            ], 422);
        }

        $payload = $this->buildKitchenEscPosPayload((string) $validated['ticket_text']);
        if ($this->shouldSkipDuplicateKitchenThermal($branchId, (string) $printer->name, $payload, 'comanda')) {
            return response()->json([
                'success' => true,
                'message' => 'Comanda duplicada detectada: se omitió la reimpresión.',
                'duplicate_skipped' => true,
            ]);
        }
        $bridgeResponse = $this->maybeQueuePrintBridge($printer, $branchId, $payload, 'comanda');
        if ($bridgeResponse) {
            return $bridgeResponse;
        }
        $printerService = app(ThermalNetworkPrintService::class);
        $timeout = (int) config('local_network.thermal_timeout_seconds', 4);

        try {
            if (filled((string) $printer->ip)) {
                $printerService->sendRaw(
                    (string) $printer->ip,
                    (int) config('local_network.thermal_port', 9100),
                    $payload,
                    $timeout
                );
            } else {
                if (! config('local_network.thermal_windows_local_enabled', true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La ticketera no tiene IP y la impresión USB local está deshabilitada.',
                    ], 422);
                }

                $printerService->sendRawToWindowsPrinter((string) $printer->name, $payload, $timeout + 4);
            }
        } catch (\Throwable $e) {
            Log::warning('Impresión comanda térmica: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? (string) $e->getMessage() : 'No se pudo imprimir la comanda.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Comanda enviada a "' . ($printer->name ?? 'Ticketera') . '"',
        ]);
    }

    public function printPreAccountThermal(Request $request)
    {
        if (! config('local_network.thermal_print_enabled', true)) {
            abort(404);
        }

        if (! LocalNetworkClient::isOnLocalNetwork($request)) {
            return response()->json([
                'success' => false,
                'message' => 'La impresión por red desde el servidor solo está permitida dentro de la red del local.',
            ], 403);
        }

        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'printer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json([
                'success' => false,
                'message' => 'Sin sucursal en sesión.',
            ], 422);
        }

        $printerBaseQuery = PrinterBranch::query()
            ->where('branch_id', $branchId)
            ->where('status', 'E');

        $host = strtolower(trim($request->getHost() ?: ''));
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
        $defaultPrinterName = $isLocalhost ? 'barra' : 'barra2';

        $requestedName = trim((string) ($validated['printer_name'] ?? '')) ?: $defaultPrinterName;
        $printer = $this->findPrinterBranchForBarTicket($printerBaseQuery, $requestedName, $defaultPrinterName);

        if (! $printer) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una ticketera de barra configurada para la precuenta.',
            ], 422);
        }

        $payload = $this->buildKitchenEscPosPayload((string) $validated['ticket_text']);
        if ($this->shouldSkipDuplicateKitchenThermal($branchId, (string) $printer->name, $payload, 'precuenta')) {
            return response()->json([
                'success' => true,
                'message' => 'Precuenta duplicada detectada: se omitió la reimpresión.',
                'duplicate_skipped' => true,
            ]);
        }
        $bridgeResponse = $this->maybeQueuePrintBridge($printer, $branchId, $payload, 'precuenta');
        if ($bridgeResponse) {
            return $bridgeResponse;
        }
        $printerService = app(ThermalNetworkPrintService::class);
        $timeout = (int) config('local_network.thermal_timeout_seconds', 4);

        try {
            if (filled((string) $printer->ip)) {
                $printerService->sendRaw(
                    (string) $printer->ip,
                    (int) config('local_network.thermal_port', 9100),
                    $payload,
                    $timeout
                );
            } else {
                if (! config('local_network.thermal_windows_local_enabled', true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La ticketera no tiene IP y la impresión USB local está deshabilitada.',
                    ], 422);
                }

                $printerService->sendRawToWindowsPrinter((string) $printer->name, $payload, $timeout + 4);
            }
        } catch (\Throwable $e) {
            Log::warning('Impresión precuenta térmica: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? (string) $e->getMessage() : 'No se pudo imprimir la precuenta.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Precuenta enviada a "' . ($printer->name ?? 'Ticketera') . '"',
        ]);
    }

    public function printKitchenTicketPdf(Request $request)
    {
        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->renderTextTicketPdfResponse(
            (string) $validated['ticket_text'],
            (int) ($validated['paper_width'] ?? 58),
            (string) ($validated['title'] ?? 'Comanda'),
            'comanda'
        );
    }

    public function createKitchenTicketPdfLink(Request $request)
    {
        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'success' => true,
            'url' => $this->storeTextTicketPdfPayload(
                (string) $validated['ticket_text'],
                (int) ($validated['paper_width'] ?? 58),
                (string) ($validated['title'] ?? 'Comanda'),
                'comanda'
            ),
        ]);
    }

    public function printPreAccountPdf(Request $request)
    {
        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->renderTextTicketPdfResponse(
            (string) $validated['ticket_text'],
            (int) ($validated['paper_width'] ?? 58),
            (string) ($validated['title'] ?? 'Precuenta'),
            'precuenta'
        );
    }

    public function createPreAccountPdfLink(Request $request)
    {
        $validated = $request->validate([
            'ticket_text' => ['required', 'string'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'success' => true,
            'url' => $this->storeTextTicketPdfPayload(
                (string) $validated['ticket_text'],
                (int) ($validated['paper_width'] ?? 58),
                (string) ($validated['title'] ?? 'Precuenta'),
                'precuenta'
            ),
        ]);
    }

    public function showStoredTextTicketPdf(string $token)
    {
        $payload = Cache::get('text-ticket-pdf:' . $token);
        abort_unless(is_array($payload), 404);

        return $this->renderTextTicketPdfResponse(
            (string) ($payload['ticket_text'] ?? ''),
            (int) ($payload['paper_width'] ?? 58),
            (string) ($payload['title'] ?? 'Ticket'),
            (string) ($payload['file_prefix'] ?? 'ticket')
        );
    }

    private function renderTextTicketPdfResponse(string $ticketText, int $paperWidth, string $title, string $filePrefix)
    {
        $paperWidth = $paperWidth === 80 ? 80 : 58;
        $normalizedText = str_replace(["\r\n", "\r"], "\n", trim($ticketText));
        $lineCount = max(1, count(explode("\n", $normalizedText)));
        $heightMm = min(500, max(60, (int) ceil(($paperWidth === 80 ? 22 : 18) + ($lineCount * ($paperWidth === 80 ? 4.0 : 3.7)))));

        $pdf = SnappyPdf::loadView('orders.print.text_ticket_pdf', [
            'title' => $title,
            'ticketText' => $normalizedText,
            'paperWidth' => $paperWidth,
        ]);

        $pdf->setOption('page-width', $paperWidth . 'mm')
            ->setOption('page-height', $heightMm . 'mm')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0)
            ->setOption('encoding', 'utf-8')
            ->setOption('print-media-type', true)
            ->setOption('disable-smart-shrinking', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('dpi', 203);

        $output = $pdf->output();

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filePrefix . '_' . now()->format('Ymd_His') . '.pdf"',
            'Content-Length' => strlen($output),
        ]);
    }

    private function storeTextTicketPdfPayload(string $ticketText, int $paperWidth, string $title, string $filePrefix): string
    {
        $token = bin2hex(random_bytes(20));

        Cache::put('text-ticket-pdf:' . $token, [
            'ticket_text' => $ticketText,
            'paper_width' => $paperWidth === 80 ? 80 : 58,
            'title' => $title,
            'file_prefix' => $filePrefix,
        ], now()->addMinutes(10));

        return route('orders.print.ticket.pdf.show', ['token' => $token]);
    }

    /**
     * Comanda / precuenta: resuelve la fila de ticketera por nombre sin confundir BARRA con BARRA2
     * cuando un LIKE "barra%" o el orden de filas en BD entregaba la impresora incorrecta
     * (móvil en la LAN enviando comanda a BARRA2 por USB vía IP en otra PC).
     */
    private function findPrinterBranchForBarTicket($printerBaseQuery, string $requestedName, string $defaultPrinterName): ?PrinterBranch
    {
        $n = trim($requestedName) !== '' ? trim($requestedName) : trim($defaultPrinterName);
        if ($n === '') {
            $n = $defaultPrinterName;
        }
        $ln = mb_strtolower($n);
        $printer = (clone $printerBaseQuery)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($n)])
            ->first();
        if ($printer) {
            return $printer;
        }
        if ($ln !== '') {
            $printer = (clone $printerBaseQuery)
                ->whereRaw('LOWER(TRIM(name)) LIKE ?', [$ln . '%'])
                ->orderByRaw('LENGTH(TRIM(name)) ASC')
                ->orderBy('id')
                ->first();
            if ($printer) {
                return $printer;
            }
        }
        $def = trim($defaultPrinterName);
        if ($def !== '') {
            $printer = (clone $printerBaseQuery)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($def)])
                ->first();
            if ($printer) {
                return $printer;
            }
        }

        return (clone $printerBaseQuery)
            ->whereRaw('LOWER(TRIM(name)) LIKE ?', ['barra%'])
            ->orderByRaw('LENGTH(TRIM(name)) DESC')
            ->orderBy('id')
            ->first();
    }

    /**
     * Comanda / precuenta hacia BARRA2 (USB en otra PC): cola en caché; la PC con QZ activa el puente (worker o escucha global).
     */
    private function maybeQueuePrintBridge(PrinterBranch $printer, int $branchId, string $escposPayload, string $kind = 'comanda'): ?\Illuminate\Http\JsonResponse
    {
        $queue = app(PrintBridgeQueue::class);
        if (! $queue->shouldQueueToStation($printer)) {
            return null;
        }
        $queue->push($branchId, (string) $printer->name, $escposPayload);
        if ($kind === 'precuenta') {
            $msg = 'Precuenta en cola para la estación (QZ en la PC con BARRA2). En esa PC, inicie sesión y active el puente (página /print-bridge/worker o “escuchar en todas las pantallas”).';

            return response()->json([
                'success' => true,
                'message' => $msg,
                'print_bridge' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Comanda en cola para la estación (QZ en la PC con BARRA2). En esa PC, misma sucursal en sesión y puente activo (/print-bridge/worker o escuchar en todas las pantallas).',
            'print_bridge' => true,
        ]);
    }

    /**
     * Evita doble impresión por doble POST consecutivo del mismo ticket.
     */
    private function shouldSkipDuplicateKitchenThermal(int $branchId, string $printerName, string $escposPayload, string $kind): bool
    {
        $pn = mb_strtolower(trim($printerName));
        if ($branchId <= 0 || $pn === '' || $escposPayload === '') {
            return false;
        }
        $hash = md5($escposPayload);
        $key = 'kitchen_thermal_dupe:' . $kind . ':' . $branchId . ':' . md5($pn) . ':' . $hash;
        // add() retorna false si ya existe: significa duplicado en ventana corta.
        return ! Cache::add($key, 1, now()->addSeconds(6));
    }

    private function buildKitchenEscPosPayload(string $plainText): string
    {
        $normalized = $this->normalizeKitchenAscii($plainText);

        return
            "\x1B\x40" .     // init
            "\x1B\x74\x02" . // codepage PC850
            $normalized .
            "\n\n" .
            "\x1D\x56\x42\x10"; // cut
    }

    private function normalizeKitchenAscii(string $text): string
    {
        $value = str_replace(
            ['á', 'Á', 'é', 'É', 'í', 'Í', 'ó', 'Ó', 'ú', 'Ú', 'ü', 'Ü', 'ñ', 'Ñ', '¿', '¡'],
            ['a', 'A', 'e', 'E', 'i', 'I', 'o', 'O', 'u', 'U', 'u', 'U', 'n', 'N', '?', '!'],
            $text
        );

        return str_replace("\r\n", "\n", (string) $value);
    }

    public function processOrderPayment(Request $request, AccountReceivablePayableService $accountReceivablePayableService)
    {

        $profileId = session('profile_id') ?? $request->user()?->profile_id;
        if (Profile::userHasMozoProfile(
            $profileId !== null && $profileId !== '' ? (int) $profileId : null
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Tu perfil (Mozo) no tiene permiso para cobrar. Solo puedes guardar pedidos.',
            ], 403);
        }

        $movementId = $request->input('movement_id');
        $tableId = $request->input('table_id');
        $branchId = (int) session('branch_id');
        $user = $request->user();
        $clientPersonId = $request->input('client_id');
        $clientNameFromRequest = $request->filled('client_name') ? trim((string) $request->client_name) : null;
        $branch = $branchId ? Branch::find($branchId) : null;
        $clientPerson = $clientPersonId ? Person::find((int) $clientPersonId) : null;
        $clientName = $clientNameFromRequest
            ?: ($clientPerson
                ? trim(($clientPerson->first_name ?? '') . ' ' . ($clientPerson->last_name ?? ''))
                : null);
        $detailMode = strtoupper(trim((string) $request->input('detail_mode', 'DETALLADO')));
        if (! in_array($detailMode, ['DETALLADO', 'CONSUMO', 'GLOSA'], true)) {
            $detailMode = 'DETALLADO';
        }
        $detailGlosa = trim((string) $request->input('detail_glosa', ''));
        $paymentMethods = collect($request->input('payment_methods', []));
        $saleType = strtoupper(trim((string) $request->input(
            'payment_type',
            $request->input('sale_type', 'CONTADO')
        )));
        if (! in_array($saleType, ['CONTADO', 'CREDITO'], true)) {
            $saleType = 'CONTADO';
        }
        $creditDays = (int) $request->input('credit_days', 0);
        $dueDate = $request->input('debt_due_date', $request->input('due_date'));

        $restrictedPmIds = PaymentMethod::paymentMethodIdsForBranchOrNull($branchId ?: null);
        if ($restrictedPmIds !== null && $paymentMethods->isNotEmpty()) {
            foreach ($paymentMethods as $row) {
                $pid = (int) ($row['payment_method_id'] ?? 0);
                if ($pid > 0 && ! in_array($pid, $restrictedPmIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Método de pago no permitido para esta sucursal.',
                    ], 422);
                }
            }
        }

        // ── Validar caja ANTES de tocar la base de datos ──────────────────────
        $cashRegisterId = effective_cash_register_id($branchId);
        if (! $cashRegisterId) {
            return response()->json([
                'success' => false,
                'message' => 'Seleccione una caja de trabajo antes de cobrar el pedido.',
            ], 422);
        }

        try {
            $this->assertCashRegisterIsOpen($cashRegisterId, $branchId);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        // ──────────────────────────────────────────────────────────────────────

        $orderMovement = null;
        if ($movementId) {
            $orderMovement = OrderMovement::where('movement_id', $movementId)->first();
        }
        if (! $orderMovement && $tableId) {
            $orderMovement = OrderMovement::where('table_id', $tableId)
                ->whereIn('status', ['PENDIENTE', 'P'])
                ->first();
        }

        if (! $orderMovement) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un pedido pendiente para esta mesa. Guarda el pedido antes de cobrar.',
            ], 404);
        }

        $splitPayload = $request->input('split');
        if (is_array($splitPayload) && ! empty($splitPayload['mode'])) {
            $splitBranchIds = config('orders.split_account_branch_ids', []);
            if (is_array($splitBranchIds) && count($splitBranchIds) > 0 && ! in_array($branchId, $splitBranchIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La división de cuenta no está habilitada para esta sucursal.',
                ], 422);
            }
            if (! (bool) config('orders.split_account_enabled', true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La división de cuenta no está habilitada para esta instalación.',
                ], 422);
            }
            if (! in_array((string) ($orderMovement->status ?? ''), ['PENDIENTE', 'P'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no está pendiente de cobro.',
                ], 422);
            }
            DB::beginTransaction();
            try {
                $splitResult = $this->processOrderPaymentSplit(
                    $request,
                    $orderMovement,
                    $branchId,
                    $branch,
                    $user,
                    $clientPerson,
                    $clientName,
                    $clientNameFromRequest,
                    $clientPersonId,
                    $paymentMethods,
                    $cashRegisterId,
                    $detailMode,
                    $detailGlosa,
                    $saleType
                );
                DB::commit();
                $orderPaymentSplit = $splitResult['order_payment_split'];
                $saleMovement = $splitResult['sale_movement'];
                $electronicInvoice = $this->syncElectronicInvoiceForSale($saleMovement, app(ApisunatService::class));
                if ($orderPaymentSplit) {
                    $orderPaymentSplit->electronic_invoice_status = $electronicInvoice['status'] ?? null;
                    $orderPaymentSplit->save();
                }
                $thermalPrinterAvailable = PrinterBranch::query()
                    ->where('branch_id', $branchId)
                    ->where('status', 'E')
                    ->whereNotNull('ip')
                    ->where('ip', '!=', '')
                    ->exists();

                return response()->json([
                    'success' => true,
                    'message' => 'Cobro parcial (división) procesado correctamente',
                    'movement_id' => $orderMovement->movement_id,
                    'order_movement_id' => $orderMovement->id,
                    'split_sale_movement_id' => $saleMovement->id,
                    'cash_movement_id' => $splitResult['cash_entry_movement_id'] ?? null,
                    'electronic_invoice' => $electronicInvoice,
                    'split_remaining_total' => $splitResult['remaining_after_total'],
                    'order_closed' => $splitResult['order_closed'],
                    'client_on_local_network' => LocalNetworkClient::isOnLocalNetwork($request),
                    'thermal_printer_available' => $thermalPrinterAvailable,
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Error al procesar cobro dividido de pedido', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], $e instanceof \InvalidArgumentException ? 422 : 500);
            }
        }

        $cashEntryMovement = null;
        DB::beginTransaction();
        try {
            /** @var OrderPaymentSplitService $splitService */
            $splitService = app(OrderPaymentSplitService::class);
            $hasPreviousSplits = $splitService->totalPaidBySplits($orderMovement) > 0.02;
            $remainingSaleDraft = null;
            $saleSubtotal = (float) ($orderMovement->subtotal ?? 0);
            $saleTax = (float) ($orderMovement->tax ?? 0);
            $saleTotal = (float) ($orderMovement->total ?? 0);

            if ($hasPreviousSplits) {
                $orderMovement->loadMissing(['details' => function ($q) {
                    $q->with('taxRate');
                }]);
                $remainingTotal = $splitService->remainingOrderTotal($orderMovement);
                if ($remainingTotal <= 0.02) {
                    throw new \InvalidArgumentException('Este pedido ya no tiene saldo pendiente por cobrar.');
                }
                $remainingSaleDraft = $splitService->buildAmountSplit($orderMovement, $remainingTotal);
                $saleSubtotal = (float) $remainingSaleDraft['subtotal'];
                $saleTax = (float) $remainingSaleDraft['tax'];
                $saleTotal = (float) $remainingSaleDraft['total'];
            }

            $orderMovement->status = 'FINALIZADO';
            $orderMovement->finished_at = now();
            $orderMovement->payment_type = $saleType === 'CREDITO' ? 'CREDITO' : 'CONTADO';
            $orderMovement->save();

            $orderBaseMovement = Movement::find($orderMovement->movement_id);
            if ($orderBaseMovement) {
                $updateData = [
                    'status' => 'A',
                    'moved_at' => now(),
                ];
                if ($clientPerson) {
                    $updateData['person_id'] = $clientPerson->id;
                }
                if ($clientName) {
                    $updateData['person_name'] = $clientName;
                }
                $requestDocumentTypeId = $request->input('document_type_id');
                $defaultDocumentTypeId = effective_default_sale_document_type_id($branchId, [2]);
                $resolvedDocumentTypeId = ($requestDocumentTypeId && DocumentType::where('id', $requestDocumentTypeId)->exists())
                    ? (int) $requestDocumentTypeId
                    : $defaultDocumentTypeId;
                $resolvedDocumentType = $resolvedDocumentTypeId
                    ? DocumentType::find((int) $resolvedDocumentTypeId)
                    : null;
                if ($resolvedDocumentType) {
                    $validationMessage = $this->validateCustomerForDocumentType(
                        $resolvedDocumentType,
                        $clientPerson,
                        $saleTotal
                    );
                    if ($validationMessage) {
                        throw new \InvalidArgumentException($validationMessage);
                    }
                }
                if ($detailMode === 'GLOSA' && $detailGlosa === '') {
                    throw new \InvalidArgumentException('Debes ingresar la glosa que saldra en el comprobante.');
                }
                if (! $clientPerson && $clientNameFromRequest) {
                    $clientPerson = $this->resolveOrCreateClientPerson(
                        $branchId ?: null,
                        $branch,
                        $clientPersonId ? (int) $clientPersonId : null,
                        $clientNameFromRequest
                    );
                    $clientName = trim(($clientPerson->first_name ?? '') . ' ' . ($clientPerson->last_name ?? ''));
                }
                if ($clientPerson) {
                    $updateData['person_id'] = $clientPerson->id;
                    $updateData['person_name'] = trim(($clientPerson->first_name ?? '') . ' ' . ($clientPerson->last_name ?? ''));
                }
                if ($resolvedDocumentTypeId && DocumentType::where('id', $resolvedDocumentTypeId)->exists()) {
                    $updateData['document_type_id'] = (int) $resolvedDocumentTypeId;
                }
                $orderBaseMovement->update($updateData);
                $orderBaseMovement->refresh();

                // --- INTEGRACIÓN CON VENTAS: Cada pedido cobrado figura ahora en ventas ---
                if (!SalesMovement::where('movement_id', $orderBaseMovement->id)->exists()) {
                    $branch = Branch::find($branchId);

                    // Determinar el tipo de movimiento para Ventas (tipo 2 por defecto, pero resolvemos dinámicamente)
                    $salesMovementType = MovementType::where('description', 'like', '%venta%')
                        ->orWhere('description', 'like', '%sale%')
                        ->orWhere('description', 'like', '%Venta%')
                        ->first();

                    if ($salesMovementType) {
                        $orderBaseMovement->update(['movement_type_id' => $salesMovementType->id]);
                    }

                    $cashRegisterId = effective_cash_register_id($branchId);
                    $cashRegister = $cashRegisterId ? \App\Models\CashRegister::find($cashRegisterId) : null;
                    $activeSeries = $cashRegister?->series ?? '001';

                    // Crear el registro de SalesMovement
                    $salesMovement = SalesMovement::create([
                        'branch_snapshot' => [
                            'id' => $branch->id,
                            'legal_name' => $branch->legal_name,
                        ],
                        'series' => $activeSeries,
                        'year' => now()->year,
                        'detail_type' => $detailMode === 'DETALLADO' ? 'DETALLADO' : 'GLOSA',
                        'consumption' => $detailMode === 'CONSUMO' ? 'Y' : 'N',
                        'payment_type' => $saleType === 'CREDITO' ? 'CREDIT' : 'CONTADO',
                        'status' => 'N',
                        'sale_type' => 'MINORISTA',
                        'currency' => 'PEN',
                        'exchange_rate' => 1.0,
                        'subtotal' => $saleSubtotal,
                        'tax' => $saleTax,
                        'total' => $saleTotal,
                        'movement_id' => $orderBaseMovement->id,
                        'branch_id' => $branchId,
                    ]);

                    // Crear detalles de venta a partir de los detalles del pedido
                    $activeOrderDetails = $orderMovement->details
                        ->filter(fn($orderDetail) => ($orderDetail->status ?? 'A') !== 'C')
                        ->values();

                    if ($detailMode === 'DETALLADO') {
                        if ($remainingSaleDraft) {
                            foreach ($remainingSaleDraft['lines'] as $line) {
                                /** @var OrderMovementDetail $orderDetail */
                                $orderDetail = $line['detail'];

                                SalesMovementDetail::create([
                                    'detail_type' => 'DETAILED',
                                    'sales_movement_id' => $salesMovement->id,
                                    'code' => $orderDetail->code,
                                    'description' => $orderDetail->description,
                                    'product_id' => $orderDetail->product_id,
                                    'product_snapshot' => $orderDetail->product_snapshot,
                                    'unit_id' => $orderDetail->unit_id,
                                    'tax_rate_id' => $orderDetail->tax_rate_id,
                                    'tax_rate_snapshot' => $orderDetail->tax_rate_snapshot,
                                    'quantity' => $line['quantity'],
                                    'courtesy_quantity' => 0,
                                    'amount' => $line['amount'],
                                    'discount_percentage' => 0,
                                    'original_amount' => $line['line_subtotal'],
                                    'comment' => $orderDetail->comment,
                                    'complements' => $orderDetail->complements ?? [],
                                    'status' => 'A',
                                    'branch_id' => $branchId,
                                ]);
                            }
                        } else {
                            foreach ($activeOrderDetails as $orderDetail) {
                            if (($orderDetail->status ?? 'A') === 'C') {
                                continue;
                            }

                            // Calcular subtotal (original_amount) si es posible
                            $qty = (float) $orderDetail->quantity;
                            $totalDetail = (float) $orderDetail->amount;
                            $taxRateVal = 0;
                            if ($orderDetail->tax_rate_snapshot && isset($orderDetail->tax_rate_snapshot['tax_rate'])) {
                                $taxRateVal = (float) $orderDetail->tax_rate_snapshot['tax_rate'] / 100;
                            } else {
                                $taxRateVal = 0.10; // Fallback al 10% según processOrder
                            }

                            $subtotalDetail = $taxRateVal > 0 ? ($totalDetail / (1 + $taxRateVal)) : $totalDetail;

                            SalesMovementDetail::create([
                                'detail_type' => 'DETAILED',
                                'sales_movement_id' => $salesMovement->id,
                                'code' => $orderDetail->code,
                                'description' => $orderDetail->description,
                                'product_id' => $orderDetail->product_id,
                                'product_snapshot' => $orderDetail->product_snapshot,
                                'unit_id' => $orderDetail->unit_id,
                                'tax_rate_id' => $orderDetail->tax_rate_id,
                                'tax_rate_snapshot' => $orderDetail->tax_rate_snapshot,
                                'quantity' => $orderDetail->quantity,
                                'courtesy_quantity' => (int) $orderDetail->courtesy_quantity,
                                'amount' => $orderDetail->amount,
                                'discount_percentage' => 0,
                                'original_amount' => $subtotalDetail,
                                'comment' => $orderDetail->comment,
                                'complements' => $orderDetail->complements ?? [],
                                'status' => 'A',
                                'branch_id' => $branchId,
                            ]);
                            }
                        }
                    } else {
                        $referenceDetail = $remainingSaleDraft
                            ? ($remainingSaleDraft['lines'][0]['detail'] ?? null)
                            : $activeOrderDetails->first();
                        if (! $referenceDetail) {
                            throw new \InvalidArgumentException('No hay detalles validos para generar la venta.');
                        }

                        SalesMovementDetail::create([
                            'detail_type' => 'GLOSA',
                            'sales_movement_id' => $salesMovement->id,
                            'code' => 'GLOSA',
                            'description' => $detailMode === 'CONSUMO' ? 'POR CONSUMO' : $detailGlosa,
                            'product_id' => null,
                            'product_snapshot' => [
                                'type' => $detailMode,
                                'source' => 'order_payment',
                            ],
                            'unit_id' => $referenceDetail->unit_id,
                            'tax_rate_id' => $referenceDetail->tax_rate_id,
                            'tax_rate_snapshot' => $referenceDetail->tax_rate_snapshot,
                            'quantity' => 1,
                            'courtesy_quantity' => 0,
                            'amount' => $saleTotal,
                            'discount_percentage' => 0,
                            'original_amount' => $saleSubtotal,
                            'comment' => null,
                            'complements' => [],
                            'status' => 'A',
                            'branch_id' => $branchId,
                        ]);
                    }
                }
                // --------------------------------------------------------------------------
            }

            $paymentConcept = $this->resolveOrderPaymentConcept();
            $cashMovementTypeId = $this->resolveCashMovementTypeId();
            $cashDocumentTypeId = $this->resolveCashIncomeDocumentTypeId($cashMovementTypeId);
            $cashRegister = CashRegister::find($cashRegisterId);
            $shift = Shift::where('branch_id', $branchId)->first() ?? Shift::first();
            if (! $shift) {
                throw new \Exception('No hay turno disponible para registrar el cobro.');
            }

            // Movimiento de caja hijo del movimiento de pedido
            $cashEntryMovement = $this->resolveCashEntryMovementByParentMovement((int) $orderMovement->movement_id);
            if (! $cashEntryMovement) {
                $cashEntryMovement = Movement::create([
                    'number' => $this->generateCashMovementNumber($branchId, (int) $cashRegisterId, (int) $paymentConcept->id),
                    'moved_at' => now(),
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $orderBaseMovement?->person_id,
                    'person_name' => $orderBaseMovement?->person_name ?? 'Publico General',
                    'responsible_id' => $user?->id,
                    'responsible_name' => $user?->person ? trim(($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')) : ($user?->name ?? 'Sistema'),
                    'comment' => 'Cobro de pedido ' . ($orderBaseMovement?->number ?? ''),
                    'status' => '1',
                    'movement_type_id' => $cashMovementTypeId,
                    'document_type_id' => $cashDocumentTypeId,
                    'branch_id' => $branchId,
                    'parent_movement_id' => $orderMovement->movement_id,
                ]);
            } else {
                $cashEntryMovement->update([
                    'moved_at' => now(),
                    'comment' => 'Cobro de pedido ' . ($orderBaseMovement?->number ?? ''),
                    'status' => '1',
                ]);
            }

            $cashMovement = CashMovements::where('movement_id', $cashEntryMovement->id)->first();
            $total = $saleTotal;
            if ($cashMovement) {
                $cashMovement->update([
                    'payment_concept_id' => $paymentConcept->id,
                    'currency' => 'PEN',
                    'exchange_rate' => 1.000,
                    'total' => $total,
                    'cash_register_id' => $cashRegisterId,
                    'cash_register' => $cashRegister?->number ?? 'Caja Principal',
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
                    'cash_register_id' => $cashRegisterId,
                    'cash_register' => $cashRegister?->number ?? 'Caja Principal',
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

            $orderTotalForCash = $saleTotal;
            $paymentMethodsSum = (float) $paymentMethods->sum(fn ($r) => (float) ($r['amount'] ?? 0));
            // En crédito, la UI a veces envía los mismos importes de "contado" (total en métodos de pago).
            // Eso no es un abono real: no debe registrar PAGADO ni saldar la cuenta por cobrar.
            $creditIgnoresCashLines = $saleType === 'CREDITO'
                && $orderTotalForCash > 0
                && $paymentMethodsSum > 0
                && $paymentMethodsSum >= $orderTotalForCash - 0.02;
            $paymentMethodsForCashDetails = $creditIgnoresCashLines ? collect() : $paymentMethods;

            if ($paymentMethodsForCashDetails->isNotEmpty()) {
                foreach ($paymentMethodsForCashDetails as $paymentMethodData) {
                    $paymentMethod = PaymentMethod::findOrFail((int) ($paymentMethodData['payment_method_id'] ?? 0));
                    $paymentGateway = ! empty($paymentMethodData['payment_gateway_id'])
                        ? PaymentGateways::find((int) $paymentMethodData['payment_gateway_id'])
                        : null;
                    $card = ! empty($paymentMethodData['card_id'])
                        ? Card::find((int) $paymentMethodData['card_id'])
                        : null;
                    $digitalWallet = ! empty($paymentMethodData['digital_wallet_id'])
                        ? DigitalWallet::find((int) $paymentMethodData['digital_wallet_id'])
                        : null;
                    $bank = ! empty($paymentMethodData['bank_id'])
                        ? Bank::find((int) $paymentMethodData['bank_id'])
                        : null;

                    DB::table('cash_movement_details')->insert([
                        'cash_movement_id' => $cashMovement->id,
                        'type' => 'PAGADO',
                        'paid_at' => now(),
                        'payment_method_id' => $paymentMethod->id,
                        'payment_method' => $paymentMethod->description ?? '',
                        'number' => $cashEntryMovement->number,
                        'card_id' => $card?->id,
                        'card' => $card?->description,
                        'bank_id' => $bank?->id,
                        'bank' => $bank?->description ?? '',
                        'digital_wallet_id' => $digitalWallet?->id,
                        'digital_wallet' => $digitalWallet?->description,
                        'payment_gateway_id' => $paymentGateway?->id,
                        'payment_gateway' => $paymentGateway?->description,
                        'amount' => (float) ($paymentMethodData['amount'] ?? 0),
                        'comment' => $request->input('notes') ?: 'Cobro de pedido ' . ($orderBaseMovement?->number ?? ''),
                        'status' => 'A',
                        'branch_id' => $branchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($saleType === 'CREDITO') {
                if (! $orderBaseMovement) {
                    throw new \InvalidArgumentException('No se encontró el movimiento del pedido para registrar el crédito.');
                }
                $personIdForDebt = (int) ($orderBaseMovement->person_id ?? 0);
                if ($personIdForDebt <= 0) {
                    throw new \InvalidArgumentException('Para venta a crédito debe asignar un cliente al pedido (persona identificada).');
                }

                $totalPaid = $creditIgnoresCashLines ? 0.0 : $paymentMethodsSum;
                $dueAt = $dueDate ? \Carbon\Carbon::parse($dueDate) : now()->addDays(max(0, $creditDays));

                $accountReceivablePayableService->syncDebtAccount(
                    $branchId,
                    $personIdForDebt,
                    (int) $orderBaseMovement->id,
                    $orderTotalForCash,
                    $totalPaid,
                    $dueAt,
                    (int) $cashMovement->id,
                    $cashEntryMovement,
                    $orderMovement->fresh(),
                    $creditDays,
                    $request->input('notes') ? (string) $request->input('notes') : null
                );
            }

            // Al cobrar, liberar la mesa (update directo para asegurar que se persiste)
            $tableIdToFree = $tableId ?? $orderMovement->table_id;
            if ($tableIdToFree) {
                Table::where('id', $tableIdToFree)->update([
                    'situation' => 'libre',
                    'opened_at' => null,
                ]);
            }

            DB::commit();
            $orderBaseMovement?->refresh();
            $electronicInvoice = $orderBaseMovement
                ? $this->syncElectronicInvoiceForSale($orderBaseMovement, app(ApisunatService::class))
                : ['status' => 'SKIPPED', 'message' => 'No se encontró movimiento base para emitir.'];

            $thermalPrinterAvailable = PrinterBranch::query()
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->whereNotNull('ip')
                ->where('ip', '!=', '')
                ->exists();

            return response()->json([
                'success' => true,
                'message' => 'Cobro de pedido procesado correctamente',
                'movement_id' => $orderMovement?->movement_id,
                'order_movement_id' => $orderMovement?->id,
                'cash_movement_id' => $cashEntryMovement?->id,
                'electronic_invoice' => $electronicInvoice,
                'client_on_local_network' => LocalNetworkClient::isOnLocalNetwork($request),
                'thermal_printer_available' => $thermalPrinterAvailable,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al procesar cobro de pedido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @return array{
     *   order_payment_split: OrderPaymentSplit,
     *   sale_movement: Movement,
     *   cash_entry_movement_id: int|null,
     *   remaining_after_total: float,
     *   order_closed: bool
     * }
     */
    private function processOrderPaymentSplit(
        Request $request,
        OrderMovement $orderMovement,
        ?int $branchId,
        ?Branch $branch,
        $user,
        ?Person $clientPerson,
        ?string $clientName,
        ?string $clientNameFromRequest,
        $clientPersonId,
        $paymentMethods,
        int $cashRegisterId,
        string $detailMode,
        string $detailGlosa,
        string $saleType = 'CONTADO'
    ): array {
        $branchId = (int) $branchId;
        $orderMovement = OrderMovement::with(['movement', 'details' => function ($q) {
            $q->with('taxRate');
        }])
            ->where('id', $orderMovement->id)
            ->lockForUpdate()
            ->firstOrFail();

        $splitPayload = $request->input('split', []);
        $mode = strtolower(trim((string) ($splitPayload['mode'] ?? '')));
        if (! in_array($mode, ['products', 'amount'], true)) {
            throw new \InvalidArgumentException('Modo de división inválido (use products o amount).');
        }

        if ($orderMovement->split_locked_to_amount && $mode !== 'amount') {
            throw new \InvalidArgumentException('Este pedido ya inició división por monto; solo puede continuar en ese modo.');
        }
        if ((string) ($orderMovement->split_mode ?? '') === 'amount' && $mode !== 'amount') {
            throw new \InvalidArgumentException('Este pedido solo permite división por monto en las siguientes partes.');
        }

        /** @var OrderPaymentSplitService $splitService */
        $splitService = app(OrderPaymentSplitService::class);

        if ($mode === 'products') {
            $items = $splitPayload['items'] ?? $splitPayload['selection'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }
            $draft = $splitService->buildProductSplit($orderMovement, $items);
        } else {
            $amount = (float) ($splitPayload['amount'] ?? 0);
            $draft = $splitService->buildAmountSplit($orderMovement, $amount);
        }

        $remaining = $splitService->remainingOrderTotal($orderMovement);
        if ($draft['total'] > $remaining + 0.02) {
            throw new \InvalidArgumentException('El importe a cobrar excede el pendiente del pedido.');
        }

        if ($paymentMethods->isEmpty()) {
            throw new \InvalidArgumentException('Debe indicar al menos un método de pago.');
        }
        $pmTotal = round((float) $paymentMethods->sum(fn($r) => (float) ($r['amount'] ?? 0)), 2);
        if (abs($pmTotal - $draft['total']) > 0.02) {
            throw new \InvalidArgumentException('La suma de métodos de pago debe coincidir con el total de la parte a cobrar.');
        }

        $requestDocumentTypeId = $request->input('document_type_id');
        $defaultDocumentTypeId = effective_default_sale_document_type_id($branchId, [2]);
        $resolvedDocumentTypeId = ($requestDocumentTypeId && DocumentType::where('id', $requestDocumentTypeId)->exists())
            ? (int) $requestDocumentTypeId
            : $defaultDocumentTypeId;
        $resolvedDocumentType = $resolvedDocumentTypeId
            ? DocumentType::find((int) $resolvedDocumentTypeId)
            : null;
        if (! $resolvedDocumentTypeId || ! $resolvedDocumentType) {
            throw new \InvalidArgumentException('Seleccione un tipo de comprobante válido para emitir la parte cobrada.');
        }

        if (! $clientPerson && $clientNameFromRequest) {
            $clientPerson = $this->resolveOrCreateClientPerson(
                $branchId ?: null,
                $branch,
                $clientPersonId ? (int) $clientPersonId : null,
                $clientNameFromRequest
            );
            if ($clientPerson) {
                $clientName = trim(($clientPerson->first_name ?? '') . ' ' . ($clientPerson->last_name ?? ''));
            }
        }

        $validationMessage = $this->validateCustomerForDocumentType(
            $resolvedDocumentType,
            $clientPerson,
            (float) $draft['total']
        );
        if ($validationMessage) {
            throw new \InvalidArgumentException($validationMessage);
        }

        $salesMovementType = MovementType::where('description', 'like', '%venta%')
            ->orWhere('description', 'like', '%sale%')
            ->orWhere('description', 'like', '%Venta%')
            ->first();
        if (! $salesMovementType) {
            $salesMovementType = MovementType::query()->orderBy('id')->first();
        }
        if (! $salesMovementType) {
            throw new \Exception('No se encontró tipo de movimiento de venta.');
        }

        $number = $this->generateSaleNumberForSplit((int) $resolvedDocumentTypeId, $cashRegisterId);

        $branch = $branch ?: Branch::find($branchId);
        if (! $branch) {
            throw new \Exception('Sucursal no encontrada.');
        }

        $splitSaleMovement = Movement::create([
            'number' => $number,
            'moved_at' => now(),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'person_id' => $clientPerson?->id,
            'person_name' => $clientName ?: 'Publico General',
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->person ? trim(($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')) : ($user?->name ?? 'Sistema'),
            'comment' => 'Venta parcial (división de pedido) ' . ($orderMovement->movement?->number ?? ''),
            'status' => 'A',
            'movement_type_id' => $salesMovementType->id,
            'document_type_id' => (int) $resolvedDocumentTypeId,
            'branch_id' => $branchId,
            'parent_movement_id' => $orderMovement->movement_id,
        ]);

        $cashRegister = CashRegister::find($cashRegisterId);
        $activeSeries = $cashRegister?->series ?? '001';

        $salesMovement = SalesMovement::create([
            'branch_snapshot' => [
                'id' => $branch->id,
                'legal_name' => $branch->legal_name,
            ],
            'series' => $activeSeries,
            'year' => now()->year,
            'detail_type' => 'DETALLADO',
            'consumption' => 'N',
            'payment_type' => 'CONTADO',
            'status' => 'N',
            'sale_type' => 'MINORISTA',
            'currency' => 'PEN',
            'exchange_rate' => 1.0,
            'subtotal' => $draft['subtotal'],
            'tax' => $draft['tax'],
            'total' => $draft['total'],
            'movement_id' => $splitSaleMovement->id,
            'branch_id' => $branchId,
        ]);

        foreach ($draft['lines'] as $line) {
            /** @var OrderMovementDetail $d */
            $d = $line['detail'];
            SalesMovementDetail::create([
                'detail_type' => 'DETAILED',
                'sales_movement_id' => $salesMovement->id,
                'code' => $d->code,
                'description' => $d->description,
                'product_id' => $d->product_id,
                'product_snapshot' => $d->product_snapshot,
                'unit_id' => $d->unit_id,
                'tax_rate_id' => $d->tax_rate_id,
                'tax_rate_snapshot' => $d->tax_rate_snapshot,
                'quantity' => $line['quantity'],
                'courtesy_quantity' => 0,
                'amount' => $line['amount'],
                'discount_percentage' => 0,
                'original_amount' => $line['line_subtotal'],
                'comment' => $d->comment,
                'complements' => $d->complements ?? [],
                'status' => 'A',
                'branch_id' => $branchId,
            ]);
        }

        $paymentConcept = $this->resolveOrderPaymentConcept();
        $cashMovementTypeId = $this->resolveCashMovementTypeId();
        $cashDocumentTypeId = $this->resolveCashIncomeDocumentTypeId($cashMovementTypeId);
        $shift = Shift::where('branch_id', $branchId)->first() ?? Shift::first();
        if (! $shift) {
            throw new \Exception('No hay turno disponible para registrar el cobro.');
        }

        $cashEntryMovement = Movement::create([
            'number' => $this->generateCashMovementNumber($branchId, (int) $cashRegisterId, (int) $paymentConcept->id),
            'moved_at' => now(),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'person_id' => $splitSaleMovement->person_id,
            'person_name' => $splitSaleMovement->person_name ?? 'Publico General',
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->person ? trim(($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')) : ($user?->name ?? 'Sistema'),
            'comment' => 'Cobro parcial pedido ' . ($orderMovement->movement?->number ?? ''),
            'status' => '1',
            'movement_type_id' => $cashMovementTypeId,
            'document_type_id' => $cashDocumentTypeId,
            'branch_id' => $branchId,
            'parent_movement_id' => $splitSaleMovement->id,
        ]);

        $cashMovement = CashMovements::create([
            'payment_concept_id' => $paymentConcept->id,
            'currency' => 'PEN',
            'exchange_rate' => 1.000,
            'total' => $draft['total'],
            'cash_register_id' => $cashRegisterId,
            'cash_register' => $cashRegister?->number ?? 'Caja Principal',
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
            'movement_id' => $cashEntryMovement->id,
            'branch_id' => $branchId,
        ]);

        foreach ($paymentMethods as $paymentMethodData) {
            $paymentMethod = PaymentMethod::findOrFail((int) ($paymentMethodData['payment_method_id'] ?? 0));
            $paymentGateway = ! empty($paymentMethodData['payment_gateway_id'])
                ? PaymentGateways::find((int) $paymentMethodData['payment_gateway_id'])
                : null;
            $card = ! empty($paymentMethodData['card_id'])
                ? Card::find((int) $paymentMethodData['card_id'])
                : null;
            $digitalWallet = ! empty($paymentMethodData['digital_wallet_id'])
                ? DigitalWallet::find((int) $paymentMethodData['digital_wallet_id'])
                : null;
            $bank = ! empty($paymentMethodData['bank_id'])
                ? Bank::find((int) $paymentMethodData['bank_id'])
                : null;

            DB::table('cash_movement_details')->insert([
                'cash_movement_id' => $cashMovement->id,
                'type' => 'PAGADO',
                'paid_at' => now(),
                'payment_method_id' => $paymentMethod->id,
                'payment_method' => $paymentMethod->description ?? '',
                'number' => $cashEntryMovement->number,
                'card_id' => $card?->id,
                'card' => $card?->description,
                'bank_id' => $bank?->id,
                'bank' => $bank?->description ?? '',
                'digital_wallet_id' => $digitalWallet?->id,
                'digital_wallet' => $digitalWallet?->description,
                'payment_gateway_id' => $paymentGateway?->id,
                'payment_gateway' => $paymentGateway?->description,
                'amount' => (float) ($paymentMethodData['amount'] ?? 0),
                'comment' => $request->input('notes') ?: 'Cobro parcial (división) ' . ($orderMovement->movement?->number ?? ''),
                'status' => 'A',
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $nextSeq = (int) (OrderPaymentSplit::where('order_movement_id', $orderMovement->id)->max('sequence') ?? 0) + 1;

        $orderPaymentSplit = OrderPaymentSplit::create([
            'order_movement_id' => $orderMovement->id,
            'sequence' => $nextSeq,
            'mode' => $mode,
            'subtotal' => $draft['subtotal'],
            'tax' => $draft['tax'],
            'total' => $draft['total'],
            'status' => 'COMPLETED',
            'movement_id' => $splitSaleMovement->id,
            'electronic_invoice_status' => null,
        ]);

        foreach ($draft['lines'] as $line) {
            OrderPaymentSplitDetail::create([
                'order_payment_split_id' => $orderPaymentSplit->id,
                'order_movement_detail_id' => $line['order_movement_detail_id'],
                'quantity' => $line['quantity'],
                'amount' => $line['amount'],
                'tax_rate_snapshot' => $line['tax_rate_snapshot'],
                'product_snapshot' => $line['product_snapshot'],
            ]);
        }

        if ($mode === 'amount') {
            $orderMovement->split_mode = 'amount';
            $orderMovement->split_locked_to_amount = true;
        } elseif (! $orderMovement->split_mode) {
            $orderMovement->split_mode = 'products';
        }
        $orderMovement->save();

        $remainingAfter = $splitService->remainingOrderTotal($orderMovement->fresh());
        $orderClosed = false;
        if ($remainingAfter <= 0.02) {
            $orderMovement->status = 'FINALIZADO';
            $orderMovement->finished_at = now();
            $st = strtoupper(trim($saleType));
            $orderMovement->payment_type = $st === 'CREDITO' ? 'CREDITO' : 'CONTADO';
            $orderMovement->save();
            $tableIdToFree = $request->input('table_id') ?? $orderMovement->table_id;
            if ($tableIdToFree) {
                Table::where('id', $tableIdToFree)->update([
                    'situation' => 'libre',
                    'opened_at' => null,
                ]);
            }
            $orderClosed = true;
        }

        return [
            'order_payment_split' => $orderPaymentSplit,
            'sale_movement' => $splitSaleMovement->fresh(),
            'cash_entry_movement_id' => $cashEntryMovement->id,
            'remaining_after_total' => round($remainingAfter, 2),
            'order_closed' => $orderClosed,
        ];
    }

    /**
     * Correlativo de venta por tipo de documento y caja (misma lógica que ventas).
     */
    private function generateSaleNumberForSplit(int $documentTypeId, int $cashRegisterId): string
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

        $query->lockForUpdate();

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

    /**
     * Genera número de pedido por sucursal (branch + movement_type + document_type).
     * Secuencia independiente por sucursal; cada nuevo movimiento recibe el siguiente correlativo.
     * Usa el último movimiento por id (más reciente) para no depender de ORDER BY por número en BD.
     */
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

    /**
     * Documento de pedido (tabla document_types, ej. nombre "Pedido") y su movement_type.
     * Evita el bug de tomar el primer movement_type por LIKE/OR y acabar en "Factura de compra".
     */
    private function resolveOrderDocumentType(): DocumentType
    {
        $exact = DocumentType::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['pedido'])
            ->first();

        if ($exact) {
            return $exact;
        }

        $byName = DocumentType::query()
            ->tap(fn($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%pedido%'))
            ->orderBy('id')
            ->first();

        if ($byName) {
            return $byName;
        }

        $movementType = MovementType::query()
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%pedido%');
            })
            ->orderBy('id')
            ->first();

        if ($movementType) {
            $documentType = DocumentType::query()
                ->where('movement_type_id', $movementType->id)
                ->tap(fn($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%pedido%'))
                ->orderBy('id')
                ->first();

            if ($documentType) {
                return $documentType;
            }

            $documentType = DocumentType::query()
                ->where('movement_type_id', $movementType->id)
                ->orderBy('id')
                ->first();

            if ($documentType) {
                return $documentType;
            }
        }

        $fallback = DocumentType::query()->orderBy('id')->first();
        if ($fallback) {
            return $fallback;
        }

        throw new \Exception('No hay tipo de movimiento o tipo de documento configurado para pedidos.');
    }

    private function generateOrderMovementNumber(int $branchId, int $movementTypeId, int $documentTypeId): string
    {
        $driver = DB::connection()->getDriverName();

        // Advisory lock por driver para serializar la generación del número.
        if ($driver === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [7000000 + $branchId]);
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SELECT GET_LOCK(?, 10)', ["order_num_{$branchId}"]);
        }

        try {
            // Buscar el máximo número usado en order_movements para esta sucursal,
            // sin depender de movement_type_id / document_type_id (que pueden variar
            // por fallback y reiniciarían la secuencia incorrectamente).
            // El CAST varía por driver: UNSIGNED en MySQL, BIGINT en PostgreSQL/SQLite.
            $castExpr = match ($driver) {
                'pgsql' => 'CAST(movements.number AS BIGINT)',
                'sqlite' => 'CAST(movements.number AS INTEGER)',
                default => 'CAST(movements.number AS UNSIGNED)',
            };

            $regexNumericExpr = match ($driver) {
                'pgsql' => "movements.number ~ '^[0-9]+$'",
                'sqlite' => "movements.number GLOB '[0-9]*' AND movements.number NOT GLOB '*[^0-9]*'",
                default => "movements.number REGEXP '^[0-9]+$'",
            };

            // lockForUpdate() no es compatible con MAX() en PostgreSQL.
            // La serialización ya la garantiza el advisory lock de arriba.
            $maxNumber = DB::table('order_movements')
                ->join('movements', 'movements.id', '=', 'order_movements.movement_id')
                ->where('movements.branch_id', $branchId)
                ->whereRaw($regexNumericExpr)
                ->max(DB::raw($castExpr));

            $next = (int) ($maxNumber ?? 0) + 1;

            return str_pad((string) $next, 8, '0', STR_PAD_LEFT);
        } finally {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SELECT RELEASE_LOCK(?)', ["order_num_{$branchId}"]);
            }
        }
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

    private function resolveCashIncomeDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->tap(fn($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%ingreso%'))
            ->orderBy('id')
            ->value('id');

        if (! $documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (! $documentTypeId) {
            throw new \Exception('No se encontro tipo de documento para movimiento de caja.');
        }

        return (int) $documentTypeId;
    }

    private function resolveActiveCashRegisterId(int $branchId): int
    {
        // Preferir caja con turno activo en esta sucursal
        $cashRegisterId = CashShiftRelation::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereNull('ended_at')
            ->whereNull('cash_movement_end_id')
            ->latest('id')
            ->value(DB::raw('(SELECT cash_register_id FROM cash_movements WHERE cash_movements.id = cash_shift_relations.cash_movement_start_id LIMIT 1)'));

        if (! $cashRegisterId) {
            // Fallback: primera caja habilitada (status booleano true=1)
            $cashRegisterId = CashRegister::query()
                ->where('status', true)
                ->orderBy('id')
                ->value('id');
        }

        if (! $cashRegisterId) {
            $cashRegisterId = CashRegister::query()
                ->orderBy('id')
                ->value('id');
        }

        if (! $cashRegisterId) {
            throw new \Exception('No hay caja activa/disponible para registrar cobro.');
        }

        return (int) $cashRegisterId;
    }

    private function assertCashRegisterIsOpen(int $cashRegisterId, int $branchId): void
    {
        // 1. Verificar que la caja exista y esté habilitada (status es booleano: true=1, false=0)
        $cashRegister = CashRegister::query()
            ->where('id', $cashRegisterId)
            ->where('status', true)
            ->first();

        if (! $cashRegister) {
            throw new \Exception('La caja seleccionada no está habilitada.');
        }

        // 2. Verificar turno activo para esta caja específica en esta sucursal
        // (status='1', sin ended_at ni cash_movement_end_id = turno no cerrado)
        $activeShift = CashShiftRelation::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereNull('ended_at')
            ->whereNull('cash_movement_end_id')
            ->whereHas('cashMovementStart', function ($query) use ($cashRegisterId) {
                $query->where('cash_register_id', $cashRegisterId);
            })
            ->latest('id')
            ->first();

        if (! $activeShift) {
            throw new \Exception('La caja "' . $cashRegister->number . '" no tiene un turno abierto. Realice una Apertura de Caja primero.');
        }
    }

    private function resolveOrderPaymentConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'I')
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%pago de cliente%');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%venta%', 'or');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%pedido%', 'or');
            })
            ->orderBy('id')
            ->first();

        if (! $paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'I')
                ->orderBy('id')
                ->first();
        }

        if (! $paymentConcept) {
            throw new \Exception('No se encontro concepto de pago de ingreso para el cobro.');
        }

        return $paymentConcept;
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $lockName = "cash_num_{$branchId}_{$cashRegisterId}";
            DB::statement('SELECT GET_LOCK(?, 10)', [$lockName]);
        }

        try {
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
        } finally {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $lockName = "cash_num_{$branchId}_{$cashRegisterId}";
                DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    private function resolveCashEntryMovementByParentMovement(int $parentMovementId): ?Movement
    {
        return Movement::query()
            ->where('parent_movement_id', $parentMovementId)
            ->whereHas('cashMovement')
            ->orderByDesc('id')
            ->first();
    }

    private function applyCommittedStockDelta(Branch $branch, int $branchId, \Illuminate\Support\Collection $previousCommittedQtyByProduct, \Illuminate\Support\Collection $newCommittedQtyByProduct): void
    {
        $affectedProductIds = $previousCommittedQtyByProduct
            ->keys()
            ->merge($newCommittedQtyByProduct->keys())
            ->map(fn($productId) => (int) $productId)
            ->filter(fn($productId) => $productId > 0)
            ->unique()
            ->values();

        foreach ($affectedProductIds as $productId) {
            $previousCommittedQty = (float) ($previousCommittedQtyByProduct->get($productId, 0));
            $newCommittedQty = (float) ($newCommittedQtyByProduct->get($productId, 0));
            $deltaQty = $newCommittedQty - $previousCommittedQty;
            if (abs($deltaQty) < 0.000001) {
                continue;
            }

            $roundUpToQuarter = function (float $qty): float {
                if ($qty <= 0) {
                    return 0.0;
                }
                return round(ceil($qty * 4) / 4, 6);
            };
            $roundDownToQuarter = function (float $qty): float {
                if ($qty <= 0) {
                    return 0.0;
                }
                return round(floor($qty * 4) / 4, 6);
            };

            $recipe = Recipe::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('status', 'A')
                ->with(['ingredients'])
                ->first();

            if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
                $yield = (float) $recipe->yield_quantity;

                $requiredByIngredientId = [];
                foreach ($recipe->ingredients as $ingredient) {
                    $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                    $ingredientQty = (float) ($ingredient->quantity ?? 0);
                    if ($ingredientProductId <= 0 || $ingredientQty <= 0) {
                        continue;
                    }

                    $raw = ($ingredientQty / $yield) * abs($deltaQty);
                    $portion = $deltaQty > 0 ? $roundUpToQuarter($raw) : $roundDownToQuarter($raw);
                    if ($portion <= 0) {
                        continue;
                    }

                    // Delta positivo consume (resta), delta negativo devuelve (suma)
                    $signed = $deltaQty > 0 ? $portion : -$portion;
                    $requiredByIngredientId[$ingredientProductId] = ($requiredByIngredientId[$ingredientProductId] ?? 0) + $signed;
                }

                if (! empty($requiredByIngredientId)) {
                    $ingredientIds = array_keys($requiredByIngredientId);
                    sort($ingredientIds);

                    $ingredientBranches = ProductBranch::query()
                        ->where('branch_id', $branchId)
                        ->whereIn('product_id', $ingredientIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('product_id');

                    foreach ($ingredientIds as $ingredientProductId) {
                        $signedDelta = (float) $requiredByIngredientId[$ingredientProductId];
                        if ($signedDelta <= 0) {
                            continue;
                        }
                        $pb = $ingredientBranches->get($ingredientProductId);
                        $name = Product::query()->where('id', $ingredientProductId)->value('description') ?? ('Ingrediente #' . $ingredientProductId);
                        if (! $pb) {
                            throw new \Exception("Ingrediente \"{$name}\" no disponible en esta sucursal.");
                        }
                        $current = (float) ($pb->stock ?? 0);
                        if ($current < $signedDelta) {
                            throw new \Exception("Stock insuficiente para \"{$name}\".");
                        }
                    }

                    foreach ($ingredientIds as $ingredientProductId) {
                        $pb = $ingredientBranches->get($ingredientProductId);
                        if (! $pb) {
                            continue;
                        }
                        $current = (float) ($pb->stock ?? 0);
                        $signedDelta = (float) $requiredByIngredientId[$ingredientProductId];
                        $newStock = $current - $signedDelta;
                        $pb->update([
                            'stock' => max(0, round($newStock, 6)),
                        ]);
                    }
                }
            } else {
                $productBranch = ProductBranch::query()
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (! $productBranch) {
                    $product = Product::find($productId);
                    $productName = (string) ($product?->description ?? ('Producto #' . $productId));
                    throw new \Exception("Producto {$productName} no disponible en esta sucursal.");
                }

                $currentStock = (float) ($productBranch->stock ?? 0);
                $newStock = $currentStock - $deltaQty;

                if (! $branch->allow_zero_stock_sales && $newStock < 0) {
                    $product = Product::find($productId);
                    $productName = (string) ($product?->description ?? ('Producto #' . $productId));
                    throw new \Exception("Stock insuficiente para \"{$productName}\".");
                }

                $productBranch->update([
                    'stock' => max(0, round($newStock, 6)),
                ]);
            }
        }
    }

    public function cancelOrder(Request $request)
    {
        $profileId = session('profile_id') ?? $request->user()?->profile_id;
        $resolvedPid = $profileId !== null && $profileId !== '' ? (int) $profileId : null;
        if (Profile::userHasMozoProfile($resolvedPid)) {
            return response()->json([
                'success' => false,
                'message' => 'El perfil Mozo no puede anular ni cerrar el pedido.',
            ], 403);
        }

        $cancelReason = trim((string) $request->input('cancel_reason'));
        $tableId = $request->input('table_id');

        if (! $tableId) {
            return response()->json([
                'success' => false,
                'message' => 'Mesa no encontrada',
            ], 404);
        }
        $table = Table::find($tableId);
        if (! $table) {
            return response()->json([
                'success' => false,
                'message' => 'Mesa no encontrada',
            ], 404);
        }

        $orderMovement = OrderMovement::where('table_id', $tableId)
            ->whereIn('status', ['PENDIENTE', 'P'])
            ->first();
        if (! $orderMovement) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un pedido pendiente para esta mesa.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $branchId = (int) ($orderMovement->branch_id ?: $table->branch_id ?: session('branch_id'));
            $branch = Branch::find($branchId);
            if (! $branch) {
                throw new \Exception('No se encontró la sucursal del pedido para revertir stock.');
            }

            $previousCommittedQtyByProduct = $orderMovement->details()
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'C');
                })
                ->get(['product_id', 'quantity'])
                ->groupBy('product_id')
                ->map(function ($rows) {
                    return (float) $rows->sum('quantity');
                });

            // Al cerrar mesa se revierte todo lo comprometido del pedido pendiente.
            $this->applyCommittedStockDelta(
                $branch,
                $branchId,
                $previousCommittedQtyByProduct,
                collect()
            );

            $orderMovement->update([
                'status' => 'CANCELADO',
                'finished_at' => now(),
            ]);

            $movementUpdate = ['moved_at' => now()];
            if ($cancelReason !== '') {
                $movementUpdate['comment'] = $cancelReason;
            }
            Movement::where('id', $orderMovement->movement_id)->update($movementUpdate);

            Table::where('id', $table->id)->update([
                'situation' => 'libre',
                'opened_at' => null,
            ]);

            if ($orderMovement->movement) {
                app(KardexSyncService::class)->syncMovement($orderMovement->movement);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al cerrar mesa y revertir stock', [
                'error' => $e->getMessage(),
                'table_id' => $tableId,
                'order_movement_id' => $orderMovement->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mesa cerrada correctamente',
        ]);
    }

    public function openTable(Request $request)
    {
        $tableId = $request->input('table_id');
        if (! $tableId) {
            return response()->json([
                'success' => false,
                'message' => 'Mesa no encontrada',
            ], 404);
        }
        $table = Table::find($tableId);
        if (! $table) {
            return response()->json([
                'success' => false,
                'message' => 'Mesa no encontrada',
            ], 404);
        }

        if ($table->situation !== 'ocupada') {
            Table::where('id', $table->id)->update([
                'situation' => 'ocupada',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mesa abierta',
        ]);
    }

    /**
     * Lista de personas para el selector de cliente en el POS de pedidos.
     * Incluye: personas con branch_id de la sucursal, o con cualquier rol asignado en role_person para esa sucursal
     * (evita listas vacías si el rol «Cliente» no coincide o faltan datos en el pivot).
     */
    private function resolveClientPeople(?int $branchId): \Illuminate\Support\Collection
    {
        $q = Person::query()
            ->select('people.*')
            ->join('role_person', function ($join) use ($branchId) {
                $join->on('role_person.person_id', '=', 'people.id')
                    ->whereNull('role_person.deleted_at');
                if ($branchId) {
                    $join->where('role_person.branch_id', $branchId);
                }
            })
            ->join('roles', function ($join) {
                $join->on('roles.id', '=', 'role_person.role_id')
                    ->whereRaw("LOWER(TRIM(roles.name)) = 'cliente'")
                    ->whereNull('roles.deleted_at');
            })
            ->orderBy('people.first_name')
            ->orderBy('people.last_name');

        return $q->get(['people.id', 'people.first_name', 'people.last_name', 'people.document_number'])
            ->unique(function (Person $person) {
                $document = trim((string) ($person->document_number ?? '0'));
                $firstName = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($person->first_name ?? ''))), 'UTF-8');
                $lastName = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($person->last_name ?? ''))), 'UTF-8');

                return implode('|', [$document !== '' ? $document : '0', $firstName, $lastName]);
            })
            ->values();
    }

    private function resolveWaiters(?int $branchId): \Illuminate\Support\Collection
    {
        $mozoProfileId = $this->mozoProfileId();
        if ($mozoProfileId === null) {
            return collect();
        }

        return Person::query()
            ->select('people.*')
            ->where('people.branch_id', $branchId)
            ->join('users', function ($join) use ($mozoProfileId) {
                $join->on('users.person_id', '=', 'people.id')
                    ->where('users.profile_id', $mozoProfileId)
                    ->whereNull('users.deleted_at');
            })
            ->orderBy('people.first_name')
            ->orderBy('people.last_name')
            ->get(['people.id', 'people.first_name', 'people.last_name', 'people.pin']);
    }

    private function isValidRuc(?string $document): bool
    {
        $clean = preg_replace('/\D+/', '', (string) $document);

        return strlen($clean) === 11;
    }

    private function isValidDni(?string $document): bool
    {
        $clean = preg_replace('/\D+/', '', (string) $document);

        return strlen($clean) === 8;
    }

    private function isValidIdentityDocumentForBoleta(?string $document): bool
    {
        return $this->isValidDni($document) || $this->isValidRuc($document);
    }

    private function validateCustomerForDocumentType(DocumentType $documentType, ?Person $person, float $total): ?string
    {
        $documentName = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');
        $documentNumber = trim((string) ($person?->document_number ?? ''));

        if (str_contains($documentName, 'factura')) {
            if (! $this->isValidRuc($documentNumber)) {
                return 'La factura de venta requiere un cliente con RUC valido de 11 digitos.';
            }

            return null;
        }

        if (str_contains($documentName, 'boleta') && $total > 700 && ! $this->isValidIdentityDocumentForBoleta($documentNumber)) {
            return 'Para emitir boleta por un monto mayor a S/ 700.00 debes seleccionar un cliente con DNI o RUC valido.';
        }

        return null;
    }

    /**
     * Construye el mapa de stock de ingredientes para productos con receta activa.
     * Clave: product_id (string). Valor: { yield_quantity, ingredients[] }.
     * Usado en el POS para advertir/bloquear cuando el stock de ingredientes es insuficiente.
     */
    private function buildRecipeStockData(?int $branchId, ?int $companyId): array
    {
        if (! $branchId || ! $companyId) {
            return [];
        }

        return Recipe::where('company_id', $companyId)
            ->where('status', 'A')
            ->with([
                'ingredients',
                'ingredients.product.branches' => function ($q) use ($branchId) {
                    $q->where('branches.id', $branchId);
                },
            ])
            ->get()
            ->mapWithKeys(function ($recipe) {
                return [(string) $recipe->product_id => [
                    'yield_quantity' => (float) ($recipe->yield_quantity ?? 1),
                    'ingredients'    => $recipe->ingredients->map(function ($ing) {
                        $pb = $ing->product?->branches?->first();
                        return [
                            'product_id' => $ing->product_id,
                            'name'        => $ing->product?->description ?? 'Ingrediente',
                            'quantity'    => (float) $ing->quantity,
                            'stock'       => $pb ? (float) ($pb->pivot->stock ?? 0) : 0,
                        ];
                    })->values()->toArray(),
                ]];
            })
            ->toArray();
    }
}
