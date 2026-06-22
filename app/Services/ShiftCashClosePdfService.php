<?php

namespace App\Services;

use App\Models\CashMovements;
use App\Models\CashShiftRelation;
use App\Models\SalesMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftCashClosePdfService
{
    /** @var list<string> */
    public const OPTION_KEYS = [
        'sales_payments_summary',
        'products_sold_summary',
        'cancellations_products',
        'expenses_by_payment_method_paid',
        'discounts_by_product',
        'debts_sales',
        'paid_sales_by_method',
        'sales_details_by_product',
        'sales_cancellations',
        'cancellations_history',
        'income_by_payment_method_paid',
        'discounts_by_person',
        'courtesies',
        'debts_sales_summary',
    ];

    /**
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    public function buildReport(CashShiftRelation $csr, array $options): array
    {
        $csr->loadMissing([
            'branch',
            'cashMovementStart.cashRegister',
            'cashMovementEnd',
        ]);

        $cashMovements = $this->operationalCashMovements($csr);
        $window = $this->timeWindow($csr);

        $saleMovements = $this->collectSaleMovementsFromCash($cashMovements);
        $branchId = (int) $csr->branch_id;

        $paidSalesByMethod = $this->aggregatePaidSalesByPaymentMethod($cashMovements);
        $incomeByMethod = $this->aggregateCashFlowByConceptType($cashMovements, 'I');
        $expenseByMethod = $this->aggregateCashFlowByConceptType($cashMovements, 'E');

        $productsSold = $this->consolidateProductsSold($saleMovements);
        $salesDetails = $this->flattenSalesDetails($saleMovements);
        $discountsByProduct = $this->filterDiscountLines($salesDetails);
        $discountsByPerson = $this->aggregateDiscountsByPerson($saleMovements);
        $courtesies = $this->filterCourtesyLines($salesDetails);

        $debtsSales = $this->queryDebtSalesInWindow($branchId, $window['from'], $window['to']);
        $debtsSummary = [
            'count' => $debtsSales->count(),
            'total' => round((float) $debtsSales->sum('total'), 2),
        ];

        $paidSalesTotal = round((float) $saleMovements->sum('total'), 2);
        $paidSalesCount = $saleMovements->count();

        $trashedSales = $this->trashedSalesInWindow($branchId, $window['from'], $window['to']);
        $cancellationLineItems = $this->lineItemsFromSales($trashedSales);
        $cancellationProductsConsolidated = $this->consolidateProductsSold($trashedSales);
        $trashedSalesTotals = [
            'count' => $trashedSales->count(),
            'total' => round((float) $trashedSales->sum('total'), 2),
        ];

        $ingresosTotal = array_sum($incomeByMethod);
        $egresosTotal = array_sum($expenseByMethod);
        $neto = $ingresosTotal - $egresosTotal;

        $discountsByProductSum = round(
            (float) array_sum(array_column($discountsByProduct, 'discount_amount')),
            2
        );
        $discountsByPersonSum = round(
            (float) array_sum(array_column($discountsByPerson, 'amount')),
            2
        );
        $courtesyTotals = $this->sumQtyAmountRows($courtesies, 'courtesy_qty');
        $cancellationProductsTotals = $this->sumQtyAmountRows($cancellationProductsConsolidated);
        $productsSoldTotals = $this->sumQtyAmountRows($productsSold);
        $salesDetailsTotals = $this->sumQtyAmountRows($salesDetails);
        $paidSalesByMethodTotal = round((float) array_sum($paidSalesByMethod), 2);
        $incomeByMethodTotal = round((float) array_sum($incomeByMethod), 2);
        $expenseByMethodTotal = round((float) array_sum($expenseByMethod), 2);

        return [
            'window' => $window,
            'cash_movements' => $cashMovements,
            'sale_movements' => $saleMovements,
            'paid_sales_by_method' => $paidSalesByMethod,
            'paid_sales_by_method_total' => $paidSalesByMethodTotal,
            'income_by_method_total' => $incomeByMethodTotal,
            'expense_by_method_total' => $expenseByMethodTotal,
            'income_by_method' => $incomeByMethod,
            'expense_by_method' => $expenseByMethod,
            'products_sold' => $productsSold,
            'sales_details' => $salesDetails,
            'discounts_by_product_rows' => $discountsByProduct,
            'discounts_by_person_rows' => $discountsByPerson,
            'courtesy_rows' => $courtesies,
            'debts_sales' => $debtsSales,
            'debts_summary' => $debtsSummary,
            'paid_sales_summary' => [
                'count' => $paidSalesCount,
                'total' => $paidSalesTotal,
            ],
            'trashed_sales' => $trashedSales,
            'trashed_sales_totals' => $trashedSalesTotals,
            'cancellation_line_items' => $cancellationLineItems,
            'cancellation_products_consolidated' => $cancellationProductsConsolidated,
            'cancellation_products_totals' => $cancellationProductsTotals,
            'discounts_by_product_total' => $discountsByProductSum,
            'discounts_by_person_total' => $discountsByPersonSum,
            'courtesy_totals' => $courtesyTotals,
            'products_sold_totals' => $productsSoldTotals,
            'sales_details_totals' => $salesDetailsTotals,
            'totals' => [
                'ingresos' => $ingresosTotal,
                'egresos' => $egresosTotal,
                'neto' => $neto,
            ],
            'options' => $options,
        ];
    }

    /**
     * @return array{from: \Carbon\Carbon, to: \Carbon\Carbon}
     */
    public function timeWindow(CashShiftRelation $csr): array
    {
        $startCm = $csr->cashMovementStart;
        $from = $startCm?->created_at
            ? Carbon::parse($startCm->created_at)
            : Carbon::parse($csr->started_at);
        $to = $csr->cashMovementEnd?->created_at
            ? Carbon::parse($csr->cashMovementEnd->created_at)
            : now();

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Movimientos de caja del turno (excluye apertura y cierre), por registro y ventana temporal.
     */
    public function operationalCashMovements(CashShiftRelation $csr): Collection
    {
        $startCm = $csr->cashMovementStart;
        if (!$startCm) {
            return collect();
        }

        $regId = $startCm->cash_register_id;
        $exclude = array_values(array_filter([
            $csr->cash_movement_start_id,
            $csr->cash_movement_end_id,
        ]));

        $from = $startCm->created_at ?? $csr->started_at;
        $to = $csr->cashMovementEnd?->created_at ?? now();

        return CashMovements::query()
            ->with([
                'paymentConcept',
                'details.paymentMethod',
                'movement.movement.salesMovement.details.product',
            ])
            ->where('cash_register_id', $regId)
            ->whereNotIn('id', $exclude)
            ->whereBetween('created_at', [
                Carbon::parse($from)->startOfSecond(),
                Carbon::parse($to)->endOfSecond(),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SalesMovement>
     */
    public function collectSaleMovementsFromCash(Collection $cashMovements): Collection
    {
        $ids = collect();
        foreach ($cashMovements as $cm) {
            $sm = $this->saleMovementFromCashMovement($cm);
            if ($sm) {
                $ids->push($sm->id);
            }
        }

        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $order = $ids->flip();

        return SalesMovement::query()
            ->whereIn('id', $ids)
            ->with(['movement', 'details.product'])
            ->get()
            ->sortBy(fn ($s) => $order[$s->id] ?? 0)
            ->values();
    }

    public function saleMovementFromCashMovement(CashMovements $cm): ?SalesMovement
    {
        $cobro = $cm->movement;
        if (!$cobro) {
            return null;
        }
        $saleMovement = $cobro->movement;
        if (!$saleMovement) {
            return null;
        }

        return $saleMovement->salesMovement;
    }

    /**
     * @return array<string, float>
     */
    public function aggregatePaidSalesByPaymentMethod(Collection $cashMovements): array
    {
        $totals = [];
        foreach ($cashMovements as $cm) {
            if (!$this->saleMovementFromCashMovement($cm)) {
                continue;
            }
            if (!$cm->details) {
                continue;
            }
            foreach ($cm->details as $detail) {
                if (($detail->type ?? '') === 'DEUDA') {
                    continue;
                }
                $label = $detail->payment_method ?? $detail->paymentMethod?->name ?? 'Otros';
                $totals[$label] = ($totals[$label] ?? 0) + (float) $detail->amount;
            }
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @return array<string, float>
     */
    public function aggregateCashFlowByConceptType(Collection $cashMovements, string $conceptType): array
    {
        $totals = [];
        foreach ($cashMovements as $cm) {
            $tipo = $cm->paymentConcept?->type ?? '';
            if ($tipo !== $conceptType) {
                continue;
            }
            if (!$cm->details) {
                continue;
            }
            foreach ($cm->details as $detail) {
                $label = $detail->payment_method ?? $detail->paymentMethod?->name ?? 'Otros';
                $totals[$label] = ($totals[$label] ?? 0) + (float) $detail->amount;
            }
        }
        ksort($totals);

        return $totals;
    }

    /**
     * @param  Collection<int, SalesMovement>  $saleMovements
     * @return array<int, array{product: string, qty: float, amount: float}>
     */
    public function consolidateProductsSold(Collection $saleMovements): array
    {
        $map = [];
        foreach ($saleMovements as $sm) {
            foreach ($sm->details ?? [] as $line) {
                $pid = $line->product_id ?? 0;
                $name = $line->product_snapshot['name'] ?? $line->description ?? 'Producto #'.$pid;
                $key = (string) $pid.'|'.$name;
                if (!isset($map[$key])) {
                    $map[$key] = ['product' => $name, 'qty' => 0.0, 'amount' => 0.0];
                }
                $map[$key]['qty'] += (float) $line->quantity;
                $map[$key]['amount'] += (float) $line->amount;
            }
        }

        return array_values($map);
    }

    /**
     * @param  Collection<int, SalesMovement>  $saleMovements
     * @return array<int, array<string, mixed>>
     */
    public function flattenSalesDetails(Collection $saleMovements): array
    {
        $rows = [];
        foreach ($saleMovements as $sm) {
            $mov = $sm->movement;
            $ticket = $mov?->number ?? '-';
            foreach ($sm->details ?? [] as $line) {
                $rows[] = [
                    'ticket' => $ticket,
                    'person' => $mov?->person_name ?? '-',
                    'product' => $line->product_snapshot['name'] ?? $line->description ?? '-',
                    'qty' => (float) $line->quantity,
                    'courtesy_qty' => (float) ($line->courtesy_quantity ?? 0),
                    'amount' => (float) $line->amount,
                    'discount_pct' => (float) ($line->discount_percentage ?? 0),
                    'original_amount' => $line->original_amount !== null ? (float) $line->original_amount : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Importe de descuento en una línea (coherente con % o importe original).
     */
    public function lineDiscountAmount(float $lineAmount, float $discountPct, ?float $originalAmount): float
    {
        if ($originalAmount !== null && $originalAmount > 0.0001) {
            return round(max(0.0, $originalAmount - $lineAmount), 2);
        }
        if ($discountPct > 0.0001) {
            $denom = 1 - ($discountPct / 100);
            if ($denom > 0.0001) {
                $orig = $lineAmount / $denom;

                return round(max(0.0, $orig - $lineAmount), 2);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function filterDiscountLines(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $amt = (float) ($r['amount'] ?? 0);
            $pct = (float) ($r['discount_pct'] ?? 0);
            $orig = isset($r['original_amount']) && $r['original_amount'] !== null
                ? (float) $r['original_amount']
                : null;
            $disc = $this->lineDiscountAmount($amt, $pct, $orig);
            if ($disc <= 0.009) {
                continue;
            }
            $r['discount_amount'] = $disc;

            $out[] = $r;
        }

        return array_values($out);
    }

    /**
     * @param  array<int, array{product: string, qty: float, amount: float}|array<string, mixed>  $rows
     * @return array{qty: float, amount: float}
     */
    public function sumQtyAmountRows(array $rows, string $qtyKey = 'qty'): array
    {
        $qty = 0.0;
        $amt = 0.0;
        foreach ($rows as $r) {
            $qty += (float) ($r[$qtyKey] ?? 0);
            $amt += (float) ($r['amount'] ?? 0);
        }

        return ['qty' => $qty, 'amount' => round($amt, 2)];
    }

    /**
     * @param  Collection<int, SalesMovement>  $saleMovements
     * @return array<int, array{person: string, amount: float}>
     */
    public function aggregateDiscountsByPerson(Collection $saleMovements): array
    {
        $totals = [];
        foreach ($saleMovements as $sm) {
            $person = $sm->movement?->person_name ?? '—';
            foreach ($sm->details ?? [] as $line) {
                $amt = (float) ($line->amount ?? 0);
                $pct = (float) ($line->discount_percentage ?? 0);
                $orig = $line->original_amount !== null ? (float) $line->original_amount : null;
                $disc = $this->lineDiscountAmount($amt, $pct, $orig);
                if ($disc > 0.009) {
                    $totals[$person] = ($totals[$person] ?? 0) + $disc;
                }
            }
        }

        $rows = [];
        foreach ($totals as $person => $amount) {
            $rows[] = ['person' => $person, 'amount' => round($amount, 2)];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function filterCourtesyLines(array $rows): array
    {
        return array_values(array_filter($rows, fn ($r) => ($r['courtesy_qty'] ?? 0) > 0.0001));
    }

    /**
     * @return Collection<int, SalesMovement>
     */
    public function queryDebtSalesInWindow(int $branchId, Carbon $from, Carbon $to): Collection
    {
        return SalesMovement::query()
            ->with(['movement', 'details'])
            ->where('branch_id', $branchId)
            ->where('payment_type', 'CREDIT')
            ->whereHas('movement', function ($q) use ($from, $to) {
                $q->whereBetween('moved_at', [$from, $to]);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SalesMovement>
     */
    public function trashedSalesInWindow(int $branchId, Carbon $from, Carbon $to): Collection
    {
        return SalesMovement::onlyTrashed()
            ->with(['movement', 'details'])
            ->where('branch_id', $branchId)
            ->whereBetween('deleted_at', [$from, $to])
            ->orderBy('deleted_at')
            ->get();
    }

    /**
     * @param  Collection<int, SalesMovement>  $sales
     * @return array<int, array<string, mixed>>
     */
    public function lineItemsFromSales(Collection $sales): array
    {
        $rows = [];
        foreach ($sales as $sm) {
            foreach ($sm->details ?? [] as $line) {
                $rows[] = [
                    'product' => $line->product_snapshot['name'] ?? $line->description ?? '-',
                    'qty' => (float) $line->quantity,
                    'amount' => (float) $line->amount,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, bool>
     */
    public static function normalizeOptions(?array $input): array
    {
        $input = $input ?? [];
        $out = [];
        foreach (self::OPTION_KEYS as $key) {
            $out[$key] = !empty($input[$key]);
        }

        return $out;
    }
}
