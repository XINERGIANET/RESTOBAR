<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Movement;
use Illuminate\Http\Request;
use App\Models\DocumentType;
use App\Models\CashRegister;
use App\Models\PaymentConcept;
use App\Models\CashMovements;
use App\Models\Shift;
use App\Models\CashShiftRelation;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Bank;
use App\Models\DigitalWallet;
use App\Models\Card;
use App\Models\CashMovementDetail;
use App\Models\MovementType;
use App\Models\Operation;
use App\Support\InsensitiveSearch;


class PettyCashController extends Controller
{


    public function redirectBase(Request $request)
    {
        $branchId = $request->session()->get('branch_id');
        $selectedBoxId = effective_cash_register_id($branchId ? (int) $branchId : null);
        if ($selectedBoxId) {
            $params = ['cash_register_id' => $selectedBoxId];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('petty-cash.index', $params);
        }
        return redirect()->route('boxes.index')->with('error', 'Seleccione una caja de trabajo antes de operar caja chica.');
    }

    public function index(Request $request, $cash_register_id = null)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $filterTipo = $request->input('tipo', '');
        if (!in_array($filterTipo, ['', 'ingreso', 'egreso'], true)) {
            $filterTipo = '';
        }
        $selectedCashShiftRelationId = $request->filled('cash_shift_relation_id')
            ? (int) $request->input('cash_shift_relation_id')
            : null;
        $selectedPaymentConceptFilterId = $request->filled('payment_concept_id')
            ? (int) $request->input('payment_concept_id')
            : null;
        $selectedMovementTypeId = $request->filled('movement_type_id')
            ? (int) $request->input('movement_type_id')
            : null;
        $selectedDocumentTypeId = $request->filled('document_type_id')
            ? (int) $request->input('document_type_id')
            : null;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
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

        $cashRegisters = $branchId
            ? CashRegister::where('status', '1')->where('branch_id', $branchId)->orderBy('number', 'asc')->get()
            : CashRegister::where('status', '1')->orderBy('number', 'asc')->get();
        $selectedBoxId = effective_cash_register_id($branchId ? (int) $branchId : null);
        if (! $selectedBoxId) {
            return redirect()->route('boxes.index')->with('error', 'Seleccione una caja de trabajo antes de operar caja chica.');
        }
        if ($cash_register_id !== null && (int) $cash_register_id !== (int) $selectedBoxId) {
            $params = ['cash_register_id' => $selectedBoxId];
            if ($request->query()) {
                $params = array_merge($request->query(), $params);
            }

            return redirect()->route('petty-cash.index', $params);
        }

        $summary = $this->getShiftSummary($selectedBoxId);

        $documentTypes = DocumentType::where('movement_type_id', 4)->get();

        $docIngreso = $documentTypes->firstWhere('name', 'Ingreso');
        $ingresoDocId = $docIngreso ? $docIngreso->id : '';
        $docEgreso = $documentTypes->firstWhere('name', 'Egreso');
        $egresoDocId = $docEgreso ? $docEgreso->id : '';

        $conceptsIngreso = PaymentConcept::where('type', 'I')
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Apertura%');
            })
            ->get();

        $conceptsEgreso = PaymentConcept::where('type', 'E')
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Cierre%');
            })
            ->get();

        $paymentConceptFilterOptions = PaymentConcept::query()
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Apertura%')
                    ->orWhere('description', 'like', '%Cierre%');
            })
            ->orderBy('description')
            ->get();

        $documentTypeFilterOptions = DocumentType::query()
            ->whereIn('movement_type_id', [1, 2, 4, 5])
            ->orderBy('name')
            ->get();

        $cashShiftSessions = collect();
        if ($branchId && $selectedBoxId) {
            $cashShiftSessions = CashShiftRelation::query()
                ->where('branch_id', $branchId)
                ->whereHas('cashMovementStart', function ($q) use ($selectedBoxId) {
                    $q->where('cash_register_id', $selectedBoxId);
                })
                ->with(['cashMovementStart.shift', 'cashMovementEnd'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        $movementsQuery = Movement::query()
            ->select('movements.*')
            ->with([
                'documentType',
                'movementType',
                'cashMovement',
                'cashMovement.details',
                'cashMovement.paymentConcept',
                'cashMovement.shift',
            ])
            ->whereHas('cashMovement', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            });

        $csrApplied = null;
        if ($selectedCashShiftRelationId && $branchId) {
            $csrApplied = CashShiftRelation::query()
                ->with(['cashMovementStart', 'cashMovementEnd'])
                ->where('branch_id', $branchId)
                ->where('id', $selectedCashShiftRelationId)
                ->whereHas('cashMovementStart', function ($q) use ($selectedBoxId) {
                    $q->where('cash_register_id', $selectedBoxId);
                })
                ->first();
        }

        // Ventana temporal: por sesión de caja (turno) elegida, o desde la última apertura si no hay filtro.
        if ($selectedCashShiftRelationId) {
            if ($csrApplied && $csrApplied->cashMovementStart) {
                $startMid = $csrApplied->cashMovementStart->movement_id;
                $movementsQuery->where('movements.id', '>=', $startMid);
                if ($csrApplied->cashMovementEnd) {
                    $movementsQuery->where('movements.id', '<=', $csrApplied->cashMovementEnd->movement_id);
                }
            } else {
                $movementsQuery->whereRaw('1 = 0');
            }
        } elseif ($summary['lastOpeningMovement']) {
            $movementsQuery->where('movements.id', '>=', $summary['lastOpeningMovement']->id);
        } else {
            $movementsQuery->whereRaw('1 = 0');
        }

        if ($selectedDocumentTypeId) {
            $movementsQuery->where('movements.document_type_id', $selectedDocumentTypeId);
        }


        if ($selectedPaymentConceptFilterId) {
            $movementsQuery->whereHas('cashMovement', function ($q) use ($selectedPaymentConceptFilterId) {
                $q->where('payment_concept_id', $selectedPaymentConceptFilterId);
            });
        }

        if ($selectedMovementTypeId) {
            $movementsQuery->where('movements.movement_type_id', $selectedMovementTypeId);
        }

        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateFrom)) {
            $movementsQuery->whereDate('movements.moved_at', '>=', $dateFrom);
        }
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateTo)) {
            $movementsQuery->whereDate('movements.moved_at', '<=', $dateTo);
        }

        $movements = $movementsQuery->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                InsensitiveSearch::whereInsensitiveLike($q, 'person_name', $search);
                InsensitiveSearch::whereInsensitiveLike($q, 'user_name', $search, 'or');
                InsensitiveSearch::whereInsensitiveLike($q, 'responsible_name', $search, 'or');
                InsensitiveSearch::whereInsensitiveLike($q, 'number', $search, 'or');
            });
        })
            ->orderBy('movements.id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $shifts = Shift::where('branch_id', session('branch_id'))->get();
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId ? (int) $branchId : null)
            ->orderBy('order_num', 'asc')
            ->get();
        $banks = Bank::where('status', true)->orderBy('order_num', 'asc')->get();
        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();
        $digitalWallets = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();
        $cards = Card::where('status', true)->orderBy('order_num', 'asc')->get();


        return view('petty_cash.index', [
            'title' => 'Caja Chica',
            'movements' => $movements,
            'documentTypes' => $documentTypes,
            'hasOpening' => $summary['hasOpening'],
            'lastOpeningMovement' => $summary['lastOpeningMovement'],
            'ingresoDocId' => $ingresoDocId,
            'egresoDocId' => $egresoDocId,
            'cashRegisters' => $cashRegisters,
            'conceptsIngreso' => $conceptsIngreso,
            'conceptsEgreso' => $conceptsEgreso,
            'selectedBoxId' => $selectedBoxId,
            'shifts' => $shifts,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'banks' => $banks,
            'digitalWallets' => $digitalWallets,
            'cards' => $cards,
            'paymentConceptFilterOptions' => $paymentConceptFilterOptions,
            'documentTypeFilterOptions' => $documentTypeFilterOptions,
            'operaciones' => $operaciones,
            'perPage' => $perPage,
            'currentBalance' => $summary['currentBalance'],
            'currentTurnBreakdown' => $summary['currentTurnBreakdown'],
            'currentTurnSummary' => $summary['currentTurnSummary'],
            'lastClosingTotal' => $summary['lastClosingTotal'],
            'lastClosingBreakdown' => $summary['lastClosingBreakdown'],
            'turnSummary' => $summary['turnSummary'],
            'selectedPaymentConceptFilterId' => $selectedPaymentConceptFilterId,
            'selectedMovementTypeId' => $selectedMovementTypeId,
            'selectedDocumentTypeId' => $selectedDocumentTypeId,
            'filterTipo' => $filterTipo,
        ]);
    }

    public function cierre(Request $request, $cash_register_id)
    {
        $selectedBoxId = effective_cash_register_id(session('branch_id') ? (int) session('branch_id') : null);
        if (! $selectedBoxId || (int) $cash_register_id !== (int) $selectedBoxId) {
            return redirect()->route('petty-cash.index', ['cash_register_id' => $selectedBoxId ?: $cash_register_id, 'view_id' => $request->input('view_id')])
                ->with('error', 'La caja activa de la sesión no coincide con la solicitada.');
        }

        $branchId = $request->session()->get('branch_id');
        $viewId = $request->input('view_id');

        $summary = $this->getShiftSummary($selectedBoxId);

        if (!$summary['hasOpening']) {
            return redirect()->route('petty-cash.index', ['cash_register_id' => $selectedBoxId, 'view_id' => $viewId])
                ->with('error', 'No hay un turno activo para esta caja. Realice una Apertura de Caja primero.');
        }

        $documentTypes = DocumentType::where('movement_type_id', 4)->get();
        $docEgreso = $documentTypes->firstWhere('name', 'Egreso');
        $egresoDocId = $docEgreso ? $docEgreso->id : '';

        $conceptsEgreso = PaymentConcept::where('type', 'E')
            ->where(function ($query) {
                $query->where('restricted', false)
                    ->orWhere('description', 'like', '%Cierre%');
            })
            ->get();

        $shifts = Shift::where('branch_id', session('branch_id'))->get();
        $banks = Bank::where('status', true)->orderBy('order_num', 'asc')->get();
        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();
        $digitalWallets = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();
        $cards = Card::where('status', true)->orderBy('order_num', 'asc')->get();

        return view('petty_cash.cierre', [
            'title' => 'Registrar Cierre de Caja',
            'cash_register_id' => $selectedBoxId,
            'selectedBoxId' => $selectedBoxId,
            'viewId' => $viewId,
            'egresoDocId' => $egresoDocId,
            'conceptsEgreso' => $conceptsEgreso,
            'shifts' => $shifts,
            'banks' => $banks,
            'paymentGateways' => $paymentGateways,
            'digitalWallets' => $digitalWallets,
            'cards' => $cards,
            'currentBalance'       => $summary['currentBalance'],
            'currentTurnBreakdown' => $summary['currentTurnBreakdown'],
            'currentTurnSummary'   => $summary['currentTurnSummary'],
            'shiftMovements'       => $summary['shiftMovements'],
            'aperturaEfectivo'     => $summary['aperturaEfectivo'],
            'turnSummary'          => $summary['turnSummary'],
            'lastOpeningMovement'  => $summary['lastOpeningMovement'],
        ]);
    }

    private function getShiftSummary($selectedBoxId)
    {
        $lastShiftRelation = CashShiftRelation::where('branch_id', session('branch_id'))
            ->whereHas('cashMovementStart', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            })
            ->latest('id')
            ->first();

        $hasOpening = $lastShiftRelation && $lastShiftRelation->status == '1';

        $lastOpeningMovement = Movement::query()
            ->whereHas('cashMovement', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            })
            ->whereHas('cashMovement.paymentConcept', function ($query) {
                $query->where('description', 'like', '%Apertura%');
            })
            ->orderBy('id', 'desc')
            ->first();

        $currentBalance = 0;
        $currentTurnBreakdown = [];
        $currentTurnSummary = ['ventas' => 0, 'ingresos' => 0, 'egresos' => 0];
        $shiftMovements = [];
        $aperturaEfectivo = 0;
        $turnSummary = ['ventas' => 0, 'ingresos' => 0, 'egresos' => 0];

        if ($hasOpening && $lastOpeningMovement) {
            $shiftMovements = Movement::with(['movementType', 'cashMovement.paymentConcept', 'cashMovement.details', 'cashMovement.shift'])
                ->whereHas('cashMovement', function ($q) use ($selectedBoxId) {
                    $q->where('cash_register_id', $selectedBoxId);
                })
                ->where('id', '>=', $lastOpeningMovement->id)
                ->orderBy('id', 'asc')
                ->get();

            $aperturaEfectivo = (float) $lastOpeningMovement->cashMovement?->details->where('payment_method', 'Efectivo')->sum('amount') ?? 0;

            $currentTurnMovementIds = Movement::query()
                ->whereHas('cashMovement', function ($q) use ($selectedBoxId) {
                    $q->where('cash_register_id', $selectedBoxId);
                })
                ->where('id', '>=', $lastOpeningMovement->id)
                ->pluck('id');
            $currentTurnCashMovementIds = CashMovements::whereIn('movement_id', $currentTurnMovementIds)->pluck('id');

            $detailsEfectivo = CashMovementDetail::with('cashMovement.paymentConcept')
                ->whereIn('cash_movement_id', $currentTurnCashMovementIds)
                ->where('payment_method', 'Efectivo')
                ->get();
            $efectivoIngresos = $detailsEfectivo->filter(fn($d) => ($d->cashMovement?->paymentConcept?->type ?? '') === 'I')->sum('amount');
            $efectivoEgresos = $detailsEfectivo->filter(fn($d) => ($d->cashMovement?->paymentConcept?->type ?? '') === 'E')->sum('amount');
            $currentBalance = round((float) $efectivoIngresos - (float) $efectivoEgresos, 2);

            $allDetailsForBreakdown = CashMovementDetail::with('cashMovement.paymentConcept')
                ->whereIn('cash_movement_id', $currentTurnCashMovementIds)
                ->get();
            $labelForDetail = function ($d) {
                $pm = trim($d->payment_method ?? '');
                if ($pm === 'Efectivo') return 'Efectivo';
                if (stripos($pm, 'Billetera') !== false && !empty(trim($d->digital_wallet ?? ''))) return trim($d->digital_wallet);
                if ((stripos($pm, 'Tarjeta') !== false || stripos($pm, 'Crédito') !== false) && !empty(trim($d->card ?? ''))) return trim($d->card);
                if (stripos($pm, 'Transferencia') !== false && !empty(trim($d->bank ?? ''))) return trim($d->bank);
                return $pm ?: 'Otro';
            };
            $byLabel = $allDetailsForBreakdown->groupBy($labelForDetail);
            $currentTurnBreakdown = $byLabel->map(function ($items, $label) {
                $ingresos = round($items->filter(fn($d) => ($d->cashMovement?->paymentConcept?->type ?? '') === 'I')->sum('amount'), 2);
                $egresos = round($items->filter(fn($d) => ($d->cashMovement?->paymentConcept?->type ?? '') === 'E')->sum('amount'), 2);
                return [
                    'method'   => $label,
                    'ingresos' => $ingresos,
                    'egresos'  => $egresos,
                    'saldo'    => round($ingresos - $egresos, 2),
                ];
            })->filter(fn($row) => $row['ingresos'] > 0 || $row['egresos'] > 0)->values()->all();

            $currentTurnMovements = CashMovements::with(['movement.movementType', 'paymentConcept', 'details'])
                ->where('cash_register_id', $selectedBoxId)
                ->whereHas('movement', function ($q) use ($lastOpeningMovement) {
                    $q->where('id', '>=', $lastOpeningMovement->id);
                })
                ->get();
            foreach ($currentTurnMovements as $cm) {
                $efectivoAmount = (float) $cm->details->where('payment_method', 'Efectivo')->sum('amount');
                if ($efectivoAmount <= 0) continue;
                $type = $cm->paymentConcept?->type ?? '';
                $desc = strtolower($cm->paymentConcept?->description ?? '');
                if (str_contains($desc, 'apertura')) continue;
                if (str_contains($desc, 'cierre')) continue;
                if (($cm->movement->movement_type_id ?? 0) == 2 || str_contains($desc, 'venta')) {
                    $currentTurnSummary['ventas'] += $efectivoAmount;
                } elseif ($type === 'I') {
                    $currentTurnSummary['ingresos'] += $efectivoAmount;
                } elseif ($type === 'E') {
                    $currentTurnSummary['egresos'] += $efectivoAmount;
                }
            }
            $currentTurnSummary['ventas'] = round($currentTurnSummary['ventas'], 2);
            $currentTurnSummary['ingresos'] = round($currentTurnSummary['ingresos'], 2);
            $currentTurnSummary['egresos'] = round($currentTurnSummary['egresos'], 2);
            
            foreach ($shiftMovements as $mov) {
                $total = (float) $mov->cashMovement?->total ?? 0;
                $type = $mov->cashMovement?->paymentConcept?->type ?? '';
                $desc = strtolower($mov->cashMovement?->paymentConcept?->description ?? '');
                if (str_contains($desc, 'apertura') || str_contains($desc, 'cierre')) continue;
                
                if (($mov->movement_type_id ?? 0) == 2 || str_contains($desc, 'venta')) {
                    $turnSummary['ventas'] += $total;
                } elseif ($type === 'I') {
                    $turnSummary['ingresos'] += $total;
                } elseif ($type === 'E') {
                    $turnSummary['egresos'] += $total;
                }
            }
            $turnSummary['ventas'] = round($turnSummary['ventas'], 2);
            $turnSummary['ingresos'] = round($turnSummary['ingresos'], 2);
            $turnSummary['egresos'] = round($turnSummary['egresos'], 2);
        }

        $lastClosingTotal = 0;
        $lastClosingBreakdown = [];

        $lastCierreMovement = Movement::query()
            ->whereHas('cashMovement', function ($query) use ($selectedBoxId) {
                $query->where('cash_register_id', $selectedBoxId);
            })
            ->whereHas('cashMovement.paymentConcept', function ($query) {
                $query->where('description', 'like', '%Cierre%');
            })
            ->with(['cashMovement', 'cashMovement.details'])
            ->orderBy('id', 'desc')
            ->first();

        if ($lastCierreMovement && $lastCierreMovement->cashMovement) {
            $lastClosingTotal = round((float) $lastCierreMovement->cashMovement->total, 2);
            $lastClosingBreakdown = $lastCierreMovement->cashMovement->details
                ->groupBy('payment_method')
                ->map(fn($items) => round($items->sum('amount'), 2))
                ->map(fn($amount, $method) => ['method' => $method ?: 'Otro', 'amount' => $amount])
                ->values()
                ->all();
        }

        return [
            'hasOpening'           => $hasOpening,
            'lastOpeningMovement'  => $lastOpeningMovement,
            'currentBalance'       => $currentBalance,
            'currentTurnBreakdown' => $currentTurnBreakdown,
            'currentTurnSummary'   => $currentTurnSummary,
            'lastClosingTotal'     => $lastClosingTotal,
            'lastClosingBreakdown' => $lastClosingBreakdown,
            'turnSummary'          => $turnSummary,
            'shiftMovements'       => $shiftMovements,
            'aperturaEfectivo'     => $aperturaEfectivo,
        ];
    }

    public function show(Request $request, $cash_register_id, $movement_id)
    {
        $movement = Movement::with([
            'documentType',
            'cashMovement.details',
            'cashMovement.shift',
            'cashMovement.paymentConcept',
        ])->findOrFail($movement_id);
        $viewId = $request->input('view_id');
        return view('petty_cash.show', compact('cash_register_id', 'movement', 'viewId'));
    }

    public function store(Request $request, $cash_register_id)
    {
        $request->merge(['cash_register_id' => $cash_register_id]);

        $validatedData = $request->all();
        if (empty($validatedData['shift_id'])) {
            $lastOpening = CashShiftRelation::where('branch_id', session('branch_id'))
                ->where('status', '1')
                ->latest('id')
                ->first()?->cashMovementStart?->shift_id;
            if ($lastOpening) {
                $request->merge(['shift_id' => $lastOpening]);
            }
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:255',
            'document_type_id' => 'nullable|exists:document_types,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'shift_id' => 'required|exists:shifts,id',

            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0.00',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.number' => 'nullable|string|max:100',
        ]);

        $restrictedPm = PaymentMethod::paymentMethodIdsForBranchOrNull(session('branch_id') ? (int) session('branch_id') : null);
        if ($restrictedPm !== null) {
            foreach ($request->payments as $p) {
                $pmId = (int) ($p['payment_method_id'] ?? 0);
                if ($pmId > 0 && !in_array($pmId, $restrictedPm, true)) {
                    return back()
                        ->withErrors(['error' => 'Método de pago no permitido para esta sucursal.'])
                        ->withInput();
                }
            }
        }

        // La Apertura de caja siempre se permite (es quien crea el turno activo).
        // Cualquier otro movimiento requiere un turno activo en esta caja.
        $concept = PaymentConcept::findOrFail($validated['payment_concept_id']);
        $isApertura = str_contains(strtolower($concept->description), 'apertura');

        if (!$isApertura) {
            $hasActiveShift = CashShiftRelation::where('branch_id', session('branch_id'))
                ->where('status', '1')
                ->whereNull('ended_at')
                ->whereNull('cash_movement_end_id')
                ->whereHas('cashMovementStart', function ($query) use ($cash_register_id) {
                    $query->where('cash_register_id', $cash_register_id);
                })
                ->exists();

            if (!$hasActiveShift) {
                return back()
                    ->withErrors(['error' => 'No hay un turno activo para esta caja. Realice una Apertura de Caja primero.'])
                    ->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request, $validated, $cash_register_id) {
                $selectedShift = Shift::findOrFail($request->shift_id);
                $shiftSnapshotData = [
                    'name' => $selectedShift->name,
                    'start_time' => $selectedShift->start_time,
                    'end_time' => $selectedShift->end_time
                ];
                $shiftSnapshotJson = json_encode($shiftSnapshotData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $typeId = 4;
                $lastRecord = Movement::select('movements.*')
                    ->join('cash_movements', 'movements.id', '=', 'cash_movements.movement_id')
                    ->where('movements.movement_type_id', $typeId)
                    ->where('cash_movements.cash_register_id', $cash_register_id)
                    ->latest('movements.id')
                    ->lockForUpdate()
                    ->first();

                $nextSequence = $lastRecord ? intval($lastRecord->number) + 1 : 1;
                $generatedNumber = str_pad($nextSequence, 8, '0', STR_PAD_LEFT);

                $totalAmount = collect($request->payments)->sum('amount');

                $movement = Movement::create([
                    'number' => $generatedNumber,
                    'moved_at' => now(),
                    'user_id' => session('user_id'),
                    'user_name' => session('user_name'),
                    'person_id' => session('person_id'),
                    'person_name' => session('person_fullname'),
                    'responsible_id' => session('user_id'),
                    'responsible_name' => session('person_fullname') ?: session('user_name'),
                    'comment' => $validated['comment'],
                    'status' => '1',
                    'movement_type_id' => $typeId,
                    'document_type_id' => $request->document_type_id,
                    'branch_id' => session('branch_id'),
                    'shift_id' => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                ]);

                $box = CashRegister::find($request->cash_register_id);
                $boxName = $box ? $box->number : 'Caja Desconocida';

                $cashMovement = CashMovements::create([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'currency' => 'PEN',
                    'exchange_rate' => 3.71,
                    'total' => $totalAmount,
                    'cash_register_id' => $cash_register_id,
                    'cash_register' => $boxName,
                    'shift_id' => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                    'movement_id' => $movement->id,
                    'branch_id' => session('branch_id'),
                ]);

                foreach ($request->payments as $paymentData) {

                    $cardName = !empty($paymentData['card_id'])
                        ? Card::find($paymentData['card_id'])?->description
                        : null;

                    $bankName = !empty($paymentData['bank_id'])
                        ? Bank::find($paymentData['bank_id'])?->description
                        : null;

                    $walletName = !empty($paymentData['digital_wallet_id'])
                        ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description
                        : null;

                    $gatewayName = !empty($paymentData['payment_gateway_id'])
                        ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description
                        : null;

                    $individualComment = ($paymentData['payment_method_id'] != 1 && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $validated['comment'];

                    CashMovementDetail::create([
                        'cash_movement_id' => $cashMovement->id,
                        'branch_id' => session('branch_id'),

                        'type' => 'PAGADO',
                        'status' => 'A',
                        'paid_at' => now(),

                        'amount' => $paymentData['amount'],
                        'payment_method_id' => $paymentData['payment_method_id'],
                        'payment_method' => $paymentData['payment_method'] ?? 'Desconocido',
                        'comment' => $individualComment,

                        'number' => $paymentData['number'] ?? null,

                        'card_id' => $paymentData['card_id'] ?? null,
                        'card' => $cardName,

                        'bank_id' => $paymentData['bank_id'] ?? null,
                        'bank' => $bankName,

                        'digital_wallet_id' => $paymentData['digital_wallet_id'] ?? null,
                        'digital_wallet' => $walletName,

                        'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                        'payment_gateway' => $gatewayName,
                    ]);
                }

                // LÓGICA DE APERTURA / CIERRE
                $concept = PaymentConcept::find($validated['payment_concept_id']);
                $conceptName = strtolower($concept->description);

                if (str_contains($conceptName, 'apertura')) {
                    CashShiftRelation::create([
                        'started_at' => now(),
                        'status' => '1',
                        'cash_movement_start_id' => $cashMovement->id,
                        'branch_id' => session('branch_id'),
                    ]);
                } elseif (str_contains($conceptName, 'cierre')) {
                    $openRelation = CashShiftRelation::where('branch_id', session('branch_id'))
                        ->where('status', '1')
                        ->latest('id')
                        ->first();

                    if ($openRelation) {
                        $openRelation->update([
                            'ended_at' => now(),
                            'status' => '0',
                            'cash_movement_end_id' => $cashMovement->id,
                        ]);
                    }
                }
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('petty-cash.index', $params)
                ->with('success', 'Movimiento registrado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al guardar: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Request $request, $cash_register_id, $id)
    {
        $movement = Movement::with(['cashMovement.details', 'cashMovement'])->findOrFail($id);

        $currentConceptId = $movement->cashMovement->payment_concept_id;
        $currentConcept = PaymentConcept::find($currentConceptId);
        $desc = $currentConcept ? strtolower($currentConcept->description) : '';
        $isSpecialEvent = str_contains($desc, 'apertura') || str_contains($desc, 'cierre');

        if ($isSpecialEvent) {
            if ($currentConcept->type == 'I') {
                $conceptsIngreso = collect([$currentConcept]);
                $conceptsEgreso = collect([]);
            } else {
                $conceptsIngreso = collect([]);
                $conceptsEgreso = collect([$currentConcept]);
            }
        } else {
            $conceptsIngreso = PaymentConcept::where('type', 'I')
                ->where('restricted', false)
                ->get();

            $conceptsEgreso = PaymentConcept::where('type', 'E')
                ->where('restricted', false)
                ->get();

            if ($currentConcept && $currentConcept->restricted && !$isSpecialEvent) {
                if ($currentConcept->type == 'I') {
                    $conceptsIngreso->push($currentConcept);
                } else {
                    $conceptsEgreso->push($currentConcept);
                }
            }
        }

        $shifts = Shift::all();
        $cards = Card::where('status', true)->orderBy('order_num', 'asc')->get();
        $banks = Bank::where('status', true)->orderBy('order_num', 'asc')->get();
        $digitalWallets = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();
        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();

        $viewId = $request->input('view_id');

        return view('petty_cash.edit', compact(
            'cash_register_id',
            'movement',
            'shifts',
            'conceptsIngreso',
            'conceptsEgreso',
            'cards',
            'banks',
            'digitalWallets',
            'paymentGateways',
            'viewId'
        ));
    }

    public function update(Request $request, $cash_register_id, $id)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:255',
            'shift_id' => 'required|exists:shifts,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.number' => 'nullable|string|max:100',
        ]);

        $restrictedPm = PaymentMethod::paymentMethodIdsForBranchOrNull(session('branch_id') ? (int) session('branch_id') : null);
        if ($restrictedPm !== null) {
            foreach ($request->payments as $p) {
                $pmId = (int) ($p['payment_method_id'] ?? 0);
                if ($pmId > 0 && !in_array($pmId, $restrictedPm, true)) {
                    return back()
                        ->withErrors(['error' => 'Método de pago no permitido para esta sucursal.'])
                        ->withInput();
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $validated, $id, $cash_register_id) {

                $movement = Movement::findOrFail($id);
                $cashMovement = CashMovements::where('movement_id', $movement->id)->firstOrFail();

                $selectedShift = Shift::findOrFail($request->shift_id);
                $shiftSnapshotJson = json_encode([
                    'name' => $selectedShift->name,
                    'start_time' => $selectedShift->start_time,
                    'end_time' => $selectedShift->end_time
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $newTotalAmount = collect($request->payments)->sum('amount');

                $movement->update([
                    'comment' => $validated['comment'],
                    'shift_id' => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                    'document_type_id' => $request->document_type_id
                ]);

                $cashMovement->update([
                    'payment_concept_id' => $validated['payment_concept_id'],
                    'total' => $newTotalAmount,
                    'shift_id' => $selectedShift->id,
                    'shift_snapshot' => $shiftSnapshotJson,
                ]);

                CashMovementDetail::where('cash_movement_id', $cashMovement->id)->delete();

                foreach ($request->payments as $paymentData) {

                    $cardName = !empty($paymentData['card_id']) ? Card::find($paymentData['card_id'])?->description : null;
                    $bankName = !empty($paymentData['bank_id']) ? Bank::find($paymentData['bank_id'])?->description : null;
                    $walletName = !empty($paymentData['digital_wallet_id']) ? DigitalWallet::find($paymentData['digital_wallet_id'])?->description : null;
                    $gatewayName = !empty($paymentData['payment_gateway_id']) ? PaymentGateways::find($paymentData['payment_gateway_id'])?->description : null;

                    $individualComment = ($paymentData['payment_method_id'] != 1 && !empty($paymentData['number']))
                        ? $paymentData['number']
                        : $validated['comment'];

                    CashMovementDetail::create([
                        'cash_movement_id' => $cashMovement->id,
                        'branch_id' => session('branch_id'),
                        'type' => 'PAGADO',
                        'status' => 'A',
                        'paid_at' => $movement->moved_at,

                        'amount' => $paymentData['amount'],
                        'payment_method_id' => $paymentData['payment_method_id'],
                        'payment_method' => $paymentData['payment_method'] ?? 'Desconocido',

                        'comment' => $individualComment,

                        'number' => $paymentData['number'] ?? null,
                        'card_id' => $paymentData['card_id'] ?? null,
                        'card' => $cardName,
                        'bank_id' => $paymentData['bank_id'] ?? null,
                        'bank' => $bankName,
                        'digital_wallet_id' => $paymentData['digital_wallet_id'] ?? null,
                        'digital_wallet' => $walletName,
                        'payment_gateway_id' => $paymentData['payment_gateway_id'] ?? null,
                        'payment_gateway' => $gatewayName,
                    ]);
                }
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('petty-cash.index', $params)
                ->with('success', 'Movimiento actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, $cash_register_id, $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $movement = Movement::with('cashMovement.details')->findOrFail($id);

                if ($movement->cashMovement) {
                    $movement->cashMovement->details()->delete();
                    $movement->cashMovement()->delete();
                }

                $movement->delete();
            });

            $params = ['cash_register_id' => $cash_register_id];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }

            return redirect()->route('petty-cash.index', $params)
                ->with('success', 'Movimiento eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}
