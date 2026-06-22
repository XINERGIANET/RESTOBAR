<?php

namespace App\Http\Controllers;

use App\Models\CashShiftRelation;
use App\Models\Operation;
use App\Models\CashRegister;
use App\Models\DocumentType;
use App\Models\PaymentConcept;
use App\Models\Shift;
use App\Models\CashMovementDetail;
use App\Models\CashMovements;
use App\Models\Bank;
use App\Models\PaymentMethod;
use App\Models\Card;
use App\Models\PaymentGateways;
use App\Models\DigitalWallet;
use App\Services\ShiftCashClosePdfService;
use App\Support\InsensitiveSearch;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiftCashController extends Controller
{
    public function redirectBase(Request $request)
    {
        $branchId = \effective_branch_id();
        $selectedBoxId = effective_cash_register_id($branchId);
        if ($selectedBoxId) {
            $params = ['cash_register_id' => $selectedBoxId];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            return redirect()->route('shift-cash.index', $params);
        }

        abort(422, 'Seleccione una caja de trabajo antes de gestionar turnos.');
    }

    public function index(Request $request, $cash_register_id = null)
    {
        $branchId = \effective_branch_id();
        $cashRegistersQuery = CashRegister::where('status', '1');
        if ($branchId) {
            $cashRegistersQuery->where('branch_id', $branchId);
        }
        $cashRegisters = $cashRegistersQuery->orderBy('number', 'asc')->get();

        $selectedBoxId = effective_cash_register_id($branchId);
        if (! $selectedBoxId) {
            abort(422, 'Seleccione una caja de trabajo antes de gestionar turnos.');
        }

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $viewId = $request->input('view_id');
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

        if ($cash_register_id !== null && (int) $cash_register_id !== (int) $selectedBoxId) {
            $params = ['cash_register_id' => $selectedBoxId];
            if ($request->filled('view_id')) {
                $params['view_id'] = $request->input('view_id');
            }
            if ($request->filled('search')) {
                $params['search'] = $request->input('search');
            }
            if ($request->filled('per_page')) {
                $params['per_page'] = $request->input('per_page');
            }

            return redirect()->route('shift-cash.index', $params);
        }

        $shift_cash = CashShiftRelation::query()
            ->with([
                'cashMovementStart.movement.documentType',
                'cashMovementStart.movement.movementType',
                'cashMovementEnd.movement.documentType',
                'cashMovementEnd.movement.movementType',
                'branch',                
                'movements.paymentConcept',
                'movements.details.paymentMethod',
                'movements.movement.salesMovement',
                'movements.movement.warehouseMovement',
                'movements.movement.orderMovement' => function ($query) {
                    $query->where('status', 'FINALIZADO');
                }
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('cashMovementStart', function($q) use ($selectedBoxId) {
                $q->where('cash_register_id', $selectedBoxId);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q2) use ($search) {
                    $q2->whereHas('cashMovementStart.movement', function ($q) use ($search) {
                        InsensitiveSearch::whereInsensitiveLike($q, 'number', $search);
                    })
                    ->orWhereHas('cashMovementEnd.movement', function ($q) use ($search) {
                        InsensitiveSearch::whereInsensitiveLike($q, 'number', $search);
                    });
                });
            })
            ->orderBy('started_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        
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

        $shifts = Shift::where('branch_id', session('branch_id'))->get();
        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId !== null ? (int) $branchId : null)
            ->orderBy('order_num', 'asc')
            ->get();
        $banks = Bank::where('status', true)->orderBy('order_num', 'asc')->get();
        $paymentGateways = PaymentGateways::where('status', true)->orderBy('order_num', 'asc')->get();
        $digitalWallets = DigitalWallet::where('status', true)->orderBy('order_num', 'asc')->get();
        $cards = Card::where('status', true)->orderBy('order_num', 'asc')->get();

        return view('shift_cash.index', [
            'title'           => 'Gestión de Turnos',
            'shift_cash'      => $shift_cash,
            'search'          => $search,
            'perPage'         => $perPage,
            'operaciones'     => $operaciones,
            'viewId'          => $viewId,
            'documentTypes'   => $documentTypes,
            'ingresoDocId'    => $ingresoDocId,
            'egresoDocId'     => $egresoDocId,
            'cashRegisters'   => $cashRegisters,
            'selectedBoxId'   => $selectedBoxId,
            'conceptsIngreso' => $conceptsIngreso,
            'conceptsEgreso'  => $conceptsEgreso,
            'shifts'          => $shifts,
            'paymentMethods'  => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'banks'           => $banks,
            'digitalWallets'  => $digitalWallets,
            'cards'           => $cards,
        ]);
    }

    public function print(Request $request, CashShiftRelation $shiftCash)
    {
        $branchId = \effective_branch_id();
        if ($branchId !== null && (int) $shiftCash->branch_id !== (int) $branchId) {
            abort(403);
        }

        $shiftCash->load([
            'cashMovementStart.movement.documentType',
            'cashMovementStart.movement.movementType',
            'cashMovementStart.cashRegister',
            'cashMovementEnd.movement.documentType',
            'cashMovementEnd.movement.movementType',
            'branch.company',
        ]);

        $options = ShiftCashClosePdfService::normalizeOptions($request->input('options'));
        $report = app(ShiftCashClosePdfService::class)->buildReport($shiftCash, $options);

        $printedAt = now();
        $viewData = [
            'shift' => $shiftCash,
            'report' => $report,
            'options' => $options,
            'printedAt' => $printedAt,
            'autoPrint' => false,
        ];

        $docName = 'cierre-caja-' . ($shiftCash->cashMovementEnd?->movement?->number ?? $shiftCash->id);
        $docName = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string) $docName);
        $fileName = $docName . '.pdf';

        try {
            // Misma secuencia que SalesController::exportPdf; descarga como OrderController::exportPdf (download)
            $pdf = PDF::loadView('shift_cash.print', $viewData);

            $pdf->setPaper('a4')
                ->setOption('margin-bottom', 10)
                ->setOption('encoding', 'utf-8')
                ->setOption('enable-local-file-access', true);

            return $pdf->download($fileName);
        } catch (\Throwable $e) {
            Log::warning('PDF cierre de caja (Snappy): ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $viewData['autoPrint'] = true;
            $viewData['pdfGenerationFailed'] = true;

            return response()
                ->view('shift_cash.print', $viewData, 200)
                ->header('X-Pdf-Error', '1');
        }
    }

    /**
     * Consolidado de productos vendidos en el turno (ventas cobradas en caja),
     * sin depender del kardex. Incluye turnos en curso hasta el momento actual.
     */
    public function productsSold(Request $request, CashShiftRelation $shiftCash)
    {
        $branchId = \effective_branch_id();
        if ($branchId !== null && (int) $shiftCash->branch_id !== (int) $branchId) {
            abort(403);
        }

        $shiftCash->load([
            'cashMovementStart.cashRegister',
            'branch.company',
        ]);

        $svc = app(ShiftCashClosePdfService::class);
        $cashMovements = $svc->operationalCashMovements($shiftCash);
        $saleMovements = $svc->collectSaleMovementsFromCash($cashMovements);
        $productsSold = $svc->consolidateProductsSold($saleMovements);
        $totals = $svc->sumQtyAmountRows($productsSold);
        $window = $svc->timeWindow($shiftCash);
        $enCurso = $shiftCash->ended_at === null;

        return view('shift_cash.products_sold', [
            'shift' => $shiftCash,
            'productsSold' => $productsSold,
            'totals' => $totals,
            'window' => $window,
            'enCurso' => $enCurso,
            'viewId' => $request->input('view_id'),
        ]);
    }
}
