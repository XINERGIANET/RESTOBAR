<?php

namespace App\Services;

use App\Models\Kardex;
use App\Models\Movement;
use App\Models\ProductBranch;
use App\Models\Recipe;

class KardexSyncService
{
    public function syncMovement(Movement $movement): void
    {
        $movement->loadMissing([
            'documentType',
            'movementType',
            'orderMovement.details.unit',
            'salesMovement.details.unit',
            'purchaseMovement.details',
            'warehouseMovement.details.unit',
        ]);

        $this->deleteMovement($movement->id);

        $productBranchPairs = [];

        if ($movement->orderMovement && in_array((string) ($movement->orderMovement->status ?? ''), ['PENDIENTE', 'P', 'FINALIZADO', 'F'], true)) {
            $detailProductIds = $movement->orderMovement->details
                ->map(fn ($detail) => (int) ($detail->product_id ?? 0))
                ->filter(fn ($productId) => $productId > 0)
                ->unique()
                ->values()
                ->all();

            $recipesByProductId = $this->recipesByProductForBranch($detailProductIds, (int) $movement->branch_id);

            foreach ($movement->orderMovement->details as $detail) {
                if ((string) ($detail->status ?? 'A') === 'C') {
                    continue;
                }

                $productId = (int) ($detail->product_id ?? 0);
                $quantity = (float) ($detail->quantity ?? 0);
                $unitId = (int) ($detail->unit_id ?? 0);

                if ($productId <= 0 || $unitId <= 0 || $quantity <= 0) {
                    continue;
                }

                $recipe = $recipesByProductId[$productId] ?? null;

                // Si hay receta activa para el producto en esta sucursal, descontar ingredientes (no el producto final)
                // para evitar doble conteo y mantener el stock basado en insumos.
                if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
                    $yield = (float) $recipe->yield_quantity;
                    foreach ($recipe->ingredients as $ingredient) {
                        $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                        $ingredientUnitId = (int) ($ingredient->unit_id ?? 0);
                        $ingredientQty = (float) ($ingredient->quantity ?? 0);

                        if ($ingredientProductId <= 0 || $ingredientUnitId <= 0 || $ingredientQty <= 0) {
                            continue;
                        }

                        $rawConsumption = ($ingredientQty / $yield) * $quantity;
                        $consumption = $this->roundUpToQuarter($rawConsumption);
                        if ($consumption <= 0) {
                            continue;
                        }

                        $qtySigned = -$consumption;
                        $this->createEntry($movement, [
                            'detalle_id' => $detail->id,
                            'producto_id' => $ingredientProductId,
                            'unidad_id' => $ingredientUnitId,
                            'cantidad' => $qtySigned,
                            'preciounitario' => 0,
                            'moneda' => (string) ($movement->orderMovement->currency ?? 'PEN'),
                            'tipocambio' => (float) ($movement->orderMovement->exchange_rate ?? 1),
                            'total' => 0,
                        ]);

                        $productBranchPairs[$this->pairKey($ingredientProductId, (int) $movement->branch_id)] = [
                            'producto_id' => $ingredientProductId,
                            'sucursal_id' => (int) $movement->branch_id,
                        ];
                    }

                    continue;
                }

                // Sin receta: mantener el flujo actual (descontar el producto vendido)
                $unitPrice = $quantity > 0 ? ((float) ($detail->amount ?? 0) / $quantity) : 0;
                $qtySigned = -$quantity;

                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => $productId,
                    'unidad_id' => $unitId,
                    'cantidad' => $qtySigned,
                    'preciounitario' => $unitPrice,
                    'moneda' => (string) ($movement->orderMovement->currency ?? 'PEN'),
                    'tipocambio' => (float) ($movement->orderMovement->exchange_rate ?? 1),
                    'total' => $qtySigned * $unitPrice,
                ]);

                $productBranchPairs[$this->pairKey($productId, (int) $movement->branch_id)] = [
                    'producto_id' => $productId,
                    'sucursal_id' => (int) $movement->branch_id,
                ];
            }

            $this->rebuildStocksForPairs($productBranchPairs);

            return;
        }

        if ($movement->salesMovement && $movement->status === 'A') {
            $detailProductIds = $movement->salesMovement->details
                ->map(fn ($detail) => (int) ($detail->product_id ?? 0))
                ->filter(fn ($productId) => $productId > 0)
                ->unique()
                ->values()
                ->all();

            $recipesByProductId = $this->recipesByProductForBranch($detailProductIds, (int) $movement->branch_id);

            foreach ($movement->salesMovement->details as $detail) {
                $productId = (int) ($detail->product_id ?? 0);
                $quantity = (float) ($detail->quantity ?? 0);
                $unitId = (int) ($detail->unit_id ?? 0);

                if ($productId <= 0 || $unitId <= 0 || $quantity <= 0) {
                    continue;
                }

                $recipe = $recipesByProductId[$productId] ?? null;

                if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
                    $yield = (float) $recipe->yield_quantity;
                    foreach ($recipe->ingredients as $ingredient) {
                        $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                        $ingredientUnitId = (int) ($ingredient->unit_id ?? 0);
                        $ingredientQty = (float) ($ingredient->quantity ?? 0);

                        if ($ingredientProductId <= 0 || $ingredientUnitId <= 0 || $ingredientQty <= 0) {
                            continue;
                        }

                        $rawConsumption = ($ingredientQty / $yield) * $quantity;
                        $consumption = $this->roundUpToQuarter($rawConsumption);
                        if ($consumption <= 0) {
                            continue;
                        }

                        $qtySigned = -$consumption;
                        $this->createEntry($movement, [
                            'detalle_id' => $detail->id,
                            'producto_id' => $ingredientProductId,
                            'unidad_id' => $ingredientUnitId,
                            'cantidad' => $qtySigned,
                            'preciounitario' => 0,
                            'moneda' => (string) ($movement->salesMovement->currency ?? 'PEN'),
                            'tipocambio' => (float) ($movement->salesMovement->exchange_rate ?? 1),
                            'total' => 0,
                        ]);

                        $productBranchPairs[$this->pairKey($ingredientProductId, (int) $movement->branch_id)] = [
                            'producto_id' => $ingredientProductId,
                            'sucursal_id' => (int) $movement->branch_id,
                        ];
                    }

                    continue;
                }

                $unitPrice = $quantity > 0 ? ((float) ($detail->amount ?? 0) / $quantity) : 0;
                $qtySigned = -$quantity;

                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => $productId,
                    'unidad_id' => $unitId,
                    'cantidad' => $qtySigned,
                    'preciounitario' => $unitPrice,
                    'moneda' => (string) ($movement->salesMovement->currency ?? 'PEN'),
                    'tipocambio' => (float) ($movement->salesMovement->exchange_rate ?? 1),
                    'total' => $qtySigned * $unitPrice,
                ]);

                $productBranchPairs[$this->pairKey($productId, (int) $movement->branch_id)] = [
                    'producto_id' => $productId,
                    'sucursal_id' => (int) $movement->branch_id,
                ];
            }

            $this->rebuildStocksForPairs($productBranchPairs);

            return;
        }

        if ($movement->purchaseMovement && $movement->purchaseMovement->afecta_kardex === 'S') {
            foreach ($movement->purchaseMovement->details as $detail) {
                $productId = (int) ($detail->producto_id ?? 0);
                $quantity = (float) ($detail->cantidad ?? 0);
                $unitId = (int) ($detail->unidad_id ?? 0);

                if ($productId <= 0 || $unitId <= 0 || $quantity <= 0) {
                    continue;
                }

                $unitPrice = (float) ($detail->monto ?? 0);
                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => $productId,
                    'unidad_id' => $unitId,
                    'cantidad' => $quantity,
                    'preciounitario' => $unitPrice,
                    'moneda' => (string) ($movement->purchaseMovement->moneda ?? 'PEN'),
                    'tipocambio' => (float) ($movement->purchaseMovement->tipocambio ?? 1),
                    'total' => $quantity * $unitPrice,
                ]);

                $productBranchPairs[$this->pairKey($productId, (int) $movement->branch_id)] = [
                    'producto_id' => $productId,
                    'sucursal_id' => (int) $movement->branch_id,
                ];
            }

            $this->rebuildStocksForPairs($productBranchPairs);

            return;
        }

        if ($movement->warehouseMovement && in_array((string) ($movement->warehouseMovement->status ?? ''), ['A', 'FINALIZADO'], true)) {
            $sign = $this->resolveWarehouseSign($movement);

            foreach ($movement->warehouseMovement->details as $detail) {
                $productId = (int) ($detail->product_id ?? 0);
                $quantity = (float) ($detail->quantity ?? 0);
                $unitId = (int) ($detail->unit_id ?? 0);

                if ($productId <= 0 || $unitId <= 0 || $quantity <= 0) {
                    continue;
                }

                $qtySigned = $sign * $quantity;

                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => $productId,
                    'unidad_id' => $unitId,
                    'cantidad' => $qtySigned,
                    'preciounitario' => 0,
                    'moneda' => 'PEN',
                    'tipocambio' => 1,
                    'total' => 0,
                ]);

                $productBranchPairs[$this->pairKey($productId, (int) $movement->branch_id)] = [
                    'producto_id' => $productId,
                    'sucursal_id' => (int) $movement->branch_id,
                ];
            }

            $this->rebuildStocksForPairs($productBranchPairs);
        }
    }

    public function deleteMovement(int $movementId): void
    {
        $pairs = Kardex::query()
            ->where('movimiento_id', $movementId)
            ->get(['producto_id', 'sucursal_id'])
            ->map(function ($row) {
                return [
                    'producto_id' => (int) $row->producto_id,
                    'sucursal_id' => (int) $row->sucursal_id,
                ];
            })
            ->unique(fn ($row) => $this->pairKey($row['producto_id'], $row['sucursal_id']))
            ->values()
            ->all();

        Kardex::query()->where('movimiento_id', $movementId)->delete();

        if (!empty($pairs)) {
            $this->rebuildStocksForPairs($pairs);
        }
    }

    private function createEntry(Movement $movement, array $payload): void
    {
        $lastStock = (float) (Kardex::query()
            ->where('producto_id', (int) $payload['producto_id'])
            ->where('sucursal_id', (int) $movement->branch_id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('stockactual') ?? 0);

        $currentStock = round($lastStock + (float) $payload['cantidad'], 6);

        Kardex::query()->create([
            'detalle_id' => $payload['detalle_id'] ?? null,
            'producto_id' => (int) $payload['producto_id'],
            'unidad_id' => (int) $payload['unidad_id'],
            'cantidad' => round((float) $payload['cantidad'], 6),
            'preciounitario' => round((float) ($payload['preciounitario'] ?? 0), 6),
            'moneda' => (string) ($payload['moneda'] ?? 'PEN'),
            'tipocambio' => round((float) ($payload['tipocambio'] ?? 1), 3),
            'total' => round((float) ($payload['total'] ?? 0), 6),
            'fecha' => $movement->moved_at ?? now(),
            'situacion' => 'E',
            'usuario_id' => $movement->user_id,
            'usuario' => $movement->user_name ?: 'Sistema',
            'movimiento_id' => $movement->id,
            'tipomovimiento_id' => $movement->movement_type_id,
            'tipodocumento_id' => $movement->document_type_id,
            'sucursal_id' => (int) $movement->branch_id,
            'stockanterior' => $lastStock,
            'stockactual' => $currentStock,
        ]);
    }

    private function resolveWarehouseSign(Movement $movement): int
    {
        $docName = strtolower((string) ($movement->documentType?->name ?? ''));
        $number = strtolower((string) ($movement->number ?? ''));

        if (str_starts_with($number, 'e-') || str_contains($docName, 'entrada') || str_contains($docName, 'entry')) {
            return 1;
        }

        if (str_starts_with($number, 's-') || str_contains($docName, 'salida') || str_contains($docName, 'exit') || str_contains($docName, 'output')) {
            return -1;
        }

        return 1;
    }

    private function rebuildStocksForPairs(array $pairs): void
    {
        foreach ($pairs as $pair) {
            $this->rebuildStocksForProductBranch((int) $pair['producto_id'], (int) $pair['sucursal_id']);
        }
    }

    private function rebuildStocksForProductBranch(int $productId, int $branchId): void
    {
        $currentOperationalStock = (float) (ProductBranch::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->value('stock') ?? 0);

        $kardexSignedSum = (float) (Kardex::query()
            ->where('producto_id', $productId)
            ->where('sucursal_id', $branchId)
            ->sum('cantidad') ?? 0);

        // Anclar la reconstruccion al stock operativo actual:
        // opening + sum(cantidades) = stock actual en product_branch.
        $openingStock = round($currentOperationalStock - $kardexSignedSum, 6);

        $rows = Kardex::query()
            ->where('producto_id', $productId)
            ->where('sucursal_id', $branchId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'cantidad']);

        $stock = $openingStock;
        foreach ($rows as $row) {
            $previous = $stock;
            $stock = round($stock + (float) $row->cantidad, 6);
            Kardex::query()
                ->where('id', $row->id)
                ->update([
                    'stockanterior' => $previous,
                    'stockactual' => $stock,
                ]);
        }
    }

    private function pairKey(int $productId, int $branchId): string
    {
        return $productId . ':' . $branchId;
    }

    /**
     * Resuelve recetas activas por producto para una sucursal.
     *
     * @return array<int, \App\Models\Recipe>
     */
    private function recipesByProductForBranch(array $productIds, int $branchId): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn ($id) => $id > 0)));
        if (empty($productIds) || $branchId <= 0) {
            return [];
        }

        return Recipe::query()
            ->where('branch_id', $branchId)
            ->where('status', 'A')
            ->whereIn('product_id', $productIds)
            ->with(['ingredients'])
            ->get()
            ->keyBy('product_id')
            ->all();
    }

    private function roundUpToQuarter(float $qty): float
    {
        if ($qty <= 0) {
            return 0;
        }

        // Regla operativa: consumir en incrementos de 1/4 (0.25) y redondear hacia arriba
        // para evitar que el inventario quede "inflado" por subconsumos.
        $rounded = ceil($qty * 4) / 4;

        return round($rounded, 6);
    }
}
