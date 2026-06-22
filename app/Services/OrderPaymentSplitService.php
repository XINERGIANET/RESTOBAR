<?php

namespace App\Services;

use App\Models\OrderMovement;
use App\Models\OrderMovementDetail;
use App\Models\OrderPaymentSplitDetail;
use Illuminate\Support\Collection;

class OrderPaymentSplitService
{
    private const MONEY_EPS = 0.02;

    /**
     * Cantidad ya facturada por línea de pedido (suma de splits previos).
     *
     * @return array<int, float> detail_id => qty billed
     */
    public function billedQuantityByDetailId(OrderMovement $orderMovement): array
    {
        $sums = OrderPaymentSplitDetail::query()
            ->whereHas('orderPaymentSplit', fn ($q) => $q->where('order_movement_id', $orderMovement->id))
            ->get()
            ->groupBy('order_movement_detail_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('quantity'));

        return $sums->all();
    }

    /**
     * Total del pedido ya cubierto por splits anteriores.
     */
    public function totalPaidBySplits(OrderMovement $orderMovement): float
    {
        return (float) $orderMovement->paymentSplits()->sum('total');
    }

    /**
     * Importe pendiente de cobro (orden total - splits completados).
     */
    public function remainingOrderTotal(OrderMovement $orderMovement): float
    {
        $orderTotal = round((float) ($orderMovement->total ?? 0), 2);
        $paid = round($this->totalPaidBySplits($orderMovement), 2);

        return max(0, round($orderTotal - $paid, 2));
    }

    /**
     * @param  array<int, array{detail_id: int, quantity: float}>  $selection
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, tax: float, total: float}
     */
    public function buildProductSplit(OrderMovement $orderMovement, array $selection): array
    {
        $billed = $this->billedQuantityByDetailId($orderMovement);
        $lines = [];
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        $activeDetails = $orderMovement->details
            ->filter(fn ($d) => ($d->status ?? 'A') !== 'C')
            ->keyBy('id');

        foreach ($selection as $row) {
            $detailId = (int) ($row['detail_id'] ?? 0);
            $wantQty = (float) ($row['quantity'] ?? 0);
            if ($detailId <= 0 || $wantQty <= 0) {
                continue;
            }

            /** @var OrderMovementDetail|null $detail */
            $detail = $activeDetails->get($detailId);
            if (! $detail) {
                throw new \InvalidArgumentException('Línea de pedido inválida en la división.');
            }

            $qty = (float) $detail->quantity;
            $courtesy = max(0, min((float) ($detail->courtesy_quantity ?? 0), $qty));
            $billableQty = max(0, $qty - $courtesy);
            $alreadyBilled = (float) ($billed[$detailId] ?? 0);
            $remainingQty = max(0, $billableQty - $alreadyBilled);

            if ($wantQty > $remainingQty + 0.000001) {
                throw new \InvalidArgumentException('Cantidad a cobrar mayor a la pendiente en una línea.');
            }

            if ($billableQty <= 0) {
                continue;
            }

            $lineAmountFull = (float) ($detail->amount ?? 0);
            $unitGross = $lineAmountFull / $billableQty;
            $portionAmount = round($unitGross * $wantQty, 2);

            $taxFactor = $this->taxFactorFromDetail($detail);
            $lineSub = $taxFactor > 0 ? round($portionAmount / (1 + $taxFactor), 2) : $portionAmount;
            $lineTax = round($portionAmount - $lineSub, 2);

            $lines[] = [
                'order_movement_detail_id' => $detail->id,
                'quantity' => $wantQty,
                'amount' => $portionAmount,
                'tax_rate_snapshot' => $detail->tax_rate_snapshot,
                'product_snapshot' => $detail->product_snapshot,
                'detail' => $detail,
                'line_subtotal' => $lineSub,
                'line_tax' => $lineTax,
            ];

            $subtotal += $lineSub;
            $tax += $lineTax;
            $total += $portionAmount;
        }

        if (count($lines) === 0) {
            throw new \InvalidArgumentException('Seleccione al menos un producto con cantidad para dividir la cuenta.');
        }

        $subtotal = round($subtotal, 2);
        $tax = round($tax, 2);
        $total = round($total, 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * Consumo secuencial de líneas hasta cubrir el monto solicitado.
     *
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, tax: float, total: float}
     */
    public function buildAmountSplit(OrderMovement $orderMovement, float $targetTotal): array
    {
        if ($targetTotal <= 0) {
            throw new \InvalidArgumentException('El monto a cobrar debe ser mayor a cero.');
        }

        $remainingMoney = round($targetTotal, 2);
        $billed = $this->billedQuantityByDetailId($orderMovement);

        $activeDetails = $orderMovement->details
            ->filter(fn ($d) => ($d->status ?? 'A') !== 'C')
            ->sortBy('id')
            ->values();

        $lines = [];
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($activeDetails as $detail) {
            if ($remainingMoney <= 0.0001) {
                break;
            }

            $qty = (float) $detail->quantity;
            $courtesy = max(0, min((float) ($detail->courtesy_quantity ?? 0), $qty));
            $billableQty = max(0, $qty - $courtesy);
            if ($billableQty <= 0) {
                continue;
            }

            $alreadyBilled = (float) ($billed[$detail->id] ?? 0);
            $lineRemainingQty = max(0, $billableQty - $alreadyBilled);
            if ($lineRemainingQty <= 0) {
                continue;
            }

            $lineAmountFull = (float) ($detail->amount ?? 0);
            $unitGross = $lineAmountFull / $billableQty;
            $lineTotalRemaining = round($unitGross * $lineRemainingQty, 2);

            $take = round(min($remainingMoney, $lineTotalRemaining), 2);
            if ($take <= 0) {
                continue;
            }

            $qtyTaken = $lineTotalRemaining > 0 ? ($take / $lineTotalRemaining) * $lineRemainingQty : $lineRemainingQty;
            $qtyTaken = round($qtyTaken, 6);

            $taxFactor = $this->taxFactorFromDetail($detail);
            $lineSub = $taxFactor > 0 ? round($take / (1 + $taxFactor), 2) : $take;
            $lineTax = round($take - $lineSub, 2);

            $lines[] = [
                'order_movement_detail_id' => $detail->id,
                'quantity' => $qtyTaken,
                'amount' => $take,
                'tax_rate_snapshot' => $detail->tax_rate_snapshot,
                'product_snapshot' => $detail->product_snapshot,
                'detail' => $detail,
                'line_subtotal' => $lineSub,
                'line_tax' => $lineTax,
            ];

            $subtotal += $lineSub;
            $tax += $lineTax;
            $total += $take;
            $remainingMoney = round($remainingMoney - $take, 2);
        }

        if (count($lines) === 0) {
            throw new \InvalidArgumentException('No hay líneas pendientes para cubrir ese monto.');
        }

        if ($remainingMoney > self::MONEY_EPS) {
            throw new \InvalidArgumentException('El monto excede lo pendiente por cobrar en el pedido.');
        }

        $total = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);
        $subtotal = round(array_sum(array_map(fn ($l) => (float) $l['line_subtotal'], $lines)), 2);
        $tax = round(array_sum(array_map(fn ($l) => (float) $l['line_tax'], $lines)), 2);

        if (abs($total - $targetTotal) > self::MONEY_EPS && count($lines) > 0) {
            $diff = round($targetTotal - $total, 2);
            $lastIdx = count($lines) - 1;
            $lines[$lastIdx]['amount'] = round((float) $lines[$lastIdx]['amount'] + $diff, 2);
            $lastDetail = $lines[$lastIdx]['detail'];
            $tf = $this->taxFactorFromDetail($lastDetail);
            $lines[$lastIdx]['line_subtotal'] = $tf > 0 ? round($lines[$lastIdx]['amount'] / (1 + $tf), 2) : $lines[$lastIdx]['amount'];
            $lines[$lastIdx]['line_tax'] = round($lines[$lastIdx]['amount'] - $lines[$lastIdx]['line_subtotal'], 2);
            $total = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);
            $subtotal = round(array_sum(array_map(fn ($l) => (float) $l['line_subtotal'], $lines)), 2);
            $tax = round(array_sum(array_map(fn ($l) => (float) $l['line_tax'], $lines)), 2);
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    private function taxFactorFromDetail(OrderMovementDetail $detail): float
    {
        $fromRel = $detail->relationLoaded('taxRate') && $detail->taxRate
            ? (float) $detail->taxRate->tax_rate
            : null;
        $pct = (float) data_get($detail->tax_rate_snapshot, 'tax_rate', $fromRel ?? 10);

        return $pct > 0 ? ($pct / 100) : 0.10;
    }
}
