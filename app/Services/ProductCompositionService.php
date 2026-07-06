<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductPromotionGroup;
use App\Models\Recipe;
use Illuminate\Support\Collection;

class ProductCompositionService
{
    private array $recipeCache = [];
    private array $productNameCache = [];
    private array $promotionCache = [];

    public function buildPromotionCatalog(?int $branchId): array
    {
        if (! $branchId) {
            return [];
        }

        $promotions = Product::query()
            ->where('type', 'PRODUCT')
            ->where('is_promotion', true)
            ->whereHas('productBranches', fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'promotionGroups.items.product',
                'promotionGroups.items.product.productBranches' => fn ($q) => $q->where('branch_id', $branchId),
            ])
            ->get();

        $catalog = [];
        foreach ($promotions as $promotion) {
            $catalog[(string) $promotion->id] = $this->promotionDefinitionFromModel($promotion, $branchId);
        }

        return $catalog;
    }

    public function syncPromotionGroups(Product $product, array $groups): void
    {
        $product->promotionGroups()->delete();

        if (! $product->is_promotion) {
            return;
        }

        foreach (array_values($groups) as $groupIndex => $groupData) {
            $group = $product->promotionGroups()->create([
                'name' => $this->cleanNullableString($groupData['name'] ?? null),
                'required_quantity' => $this->toPositiveDecimal($groupData['required_quantity'] ?? 1, 1),
                'sort_order' => $groupIndex,
            ]);

            foreach (array_values($groupData['items'] ?? []) as $itemIndex => $itemData) {
                $group->items()->create([
                    'product_id' => (int) ($itemData['product_id'] ?? 0),
                    'default_quantity' => max(0, round((float) ($itemData['default_quantity'] ?? 0), 6)),
                    'sort_order' => $itemIndex,
                ]);
            }
        }
    }

    public function normalizePromotionSelection(int $productId, array $selection = []): array
    {
        $definition = $this->getPromotionDefinition($productId);
        if (! $definition) {
            return [];
        }

        $selectionByGroup = collect($selection)
            ->map(function ($row) {
                return [
                    'group_id' => (int) ($row['group_id'] ?? 0),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => max(0, round((float) ($row['quantity'] ?? 0), 6)),
                ];
            })
            ->filter(fn ($row) => $row['group_id'] > 0 && $row['product_id'] > 0 && $row['quantity'] > 0)
            ->groupBy('group_id');

        $normalized = [];
        foreach ($definition['groups'] as $group) {
            $groupId = (int) $group['id'];
            $required = max(0, round((float) ($group['required_quantity'] ?? 0), 6));
            $allowedItems = collect($group['items'] ?? [])->keyBy(fn ($item) => (int) ($item['product_id'] ?? 0));

            $rows = collect($selectionByGroup->get($groupId, []))
                ->filter(fn ($row) => $allowedItems->has((int) $row['product_id']))
                ->values();

            if ($rows->isEmpty()) {
                $rows = collect($group['default_selection'] ?? []);
            }

            $sum = round((float) $rows->sum('quantity'), 6);
            if (abs($sum - $required) > 0.000001) {
                $rows = collect($group['default_selection'] ?? []);
                $sum = round((float) $rows->sum('quantity'), 6);
            }

            if ($required > 0 && abs($sum - $required) > 0.000001) {
                continue;
            }

            foreach ($rows as $row) {
                $meta = $allowedItems->get((int) $row['product_id']);
                $normalized[] = [
                    'group_id' => $groupId,
                    'group_name' => $group['name'] ?? null,
                    'product_id' => (int) $row['product_id'],
                    'product_name' => $meta['product_name'] ?? ('Producto #' . (int) $row['product_id']),
                    'quantity' => max(0, round((float) $row['quantity'], 6)),
                ];
            }
        }

        return $normalized;
    }

    public function selectionSignature(array $selection = []): string
    {
        $rows = collect($selection)
            ->map(function ($row) {
                return [
                    'group_id' => (int) ($row['group_id'] ?? 0),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => round((float) ($row['quantity'] ?? 0), 6),
                ];
            })
            ->sortBy([
                ['group_id', 'asc'],
                ['product_id', 'asc'],
                ['quantity', 'asc'],
            ])
            ->values()
            ->all();

        return md5(json_encode($rows));
    }

    public function expandRawItemToStockMap(int $branchId, array $rawItem): array
    {
        $productId = (int) ($rawItem['product_id'] ?? $rawItem['pId'] ?? 0);
        $qty = max(0, round((float) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 0), 6));
        if ($productId <= 0 || $qty <= 0) {
            return [];
        }

        $selection = $rawItem['promotionSelection']
            ?? $rawItem['promotion_selection']
            ?? data_get($rawItem, 'product_snapshot.promotion_selection')
            ?? [];

        return $this->expandProductToStockMap($branchId, $productId, $qty, is_array($selection) ? $selection : []);
    }

    public function expandDetailToStockMap(int $branchId, $detail): array
    {
        $productId = (int) ($detail->product_id ?? 0);
        $qty = max(0, round((float) ($detail->quantity ?? 0), 6));
        if ($productId <= 0 || $qty <= 0) {
            return [];
        }

        $selection = data_get($detail->product_snapshot, 'promotion_selection', []);

        return $this->expandProductToStockMap($branchId, $productId, $qty, is_array($selection) ? $selection : []);
    }

    public function expandProductToStockMap(int $branchId, int $productId, float $quantity, array $promotionSelection = [], array $stack = []): array
    {
        $quantity = max(0, round($quantity, 6));
        if ($productId <= 0 || $quantity <= 0) {
            return [];
        }

        $stackKey = $productId . ':' . count($stack);
        if (in_array($productId, $stack, true)) {
            throw new \RuntimeException('Se detectó una composición circular en promociones/recetas.');
        }
        $stack[] = $productId;

        $product = Product::query()->find($productId);
        if (! $product) {
            return [];
        }

        if ((bool) ($product->is_promotion ?? false)) {
            $selectionRows = $this->normalizePromotionSelection($productId, $promotionSelection);
            $out = [];
            foreach ($selectionRows as $row) {
                $componentQty = max(0, round($quantity * (float) ($row['quantity'] ?? 0), 6));
                if ($componentQty <= 0) {
                    continue;
                }
                $out = $this->mergeStockMaps(
                    $out,
                    $this->expandProductToStockMap($branchId, (int) $row['product_id'], $componentQty, [], $stack)
                );
            }

            return $out;
        }

        $recipe = $this->getActiveRecipe($branchId, $productId);
        if ($recipe && (float) ($recipe->yield_quantity ?? 0) > 0) {
            $yield = (float) $recipe->yield_quantity;
            $out = [];
            foreach ($recipe->ingredients as $ingredient) {
                $ingredientProductId = (int) ($ingredient->product_id ?? 0);
                $ingredientQty = (float) ($ingredient->quantity ?? 0);
                if ($ingredientProductId <= 0 || $ingredientQty <= 0) {
                    continue;
                }

                $rawConsumption = ($ingredientQty / $yield) * $quantity;
                $consumption = $this->roundUpToQuarter($rawConsumption);
                if ($consumption <= 0) {
                    continue;
                }

                $key = (string) $ingredientProductId;
                if (! isset($out[$key])) {
                    $out[$key] = [
                        'product_id' => $ingredientProductId,
                        'product_name' => $this->productName($ingredientProductId),
                        'quantity' => 0.0,
                        'strict' => true,
                    ];
                }
                $out[$key]['quantity'] = round((float) $out[$key]['quantity'] + $consumption, 6);
                $out[$key]['strict'] = true;
            }

            return $out;
        }

        return [
            (string) $productId => [
                'product_id' => $productId,
                'product_name' => $this->productName($productId),
                'quantity' => $quantity,
                'strict' => false,
            ],
        ];
    }

    public function mergeStockMaps(array $left, array $right): array
    {
        foreach ($right as $key => $row) {
            if (! isset($left[$key])) {
                $left[$key] = $row;
                continue;
            }

            $left[$key]['quantity'] = round((float) $left[$key]['quantity'] + (float) ($row['quantity'] ?? 0), 6);
            $left[$key]['strict'] = (bool) ($left[$key]['strict'] ?? false) || (bool) ($row['strict'] ?? false);
        }

        return $left;
    }

    public function productName(int $productId): string
    {
        if (! isset($this->productNameCache[$productId])) {
            $this->productNameCache[$productId] = (string) (
                Product::query()->where('id', $productId)->value('description') ?? ('Producto #' . $productId)
            );
        }

        return $this->productNameCache[$productId];
    }

    public function promotionDefinitionFromModel(Product $promotion, int $branchId): array
    {
        $promotion->loadMissing([
            'promotionGroups.items.product',
            'promotionGroups.items.product.productBranches' => fn ($q) => $q->where('branch_id', $branchId),
        ]);

        $groups = $promotion->promotionGroups
            ->sortBy('sort_order')
            ->map(function (ProductPromotionGroup $group) use ($branchId) {
                $items = $group->items
                    ->sortBy('sort_order')
                    ->map(function ($item) use ($branchId) {
                        $product = $item->product;
                        $branch = $product?->productBranches?->firstWhere('branch_id', $branchId);

                        return [
                            'product_id' => (int) ($product?->id ?? 0),
                            'product_name' => (string) ($product?->description ?? 'Producto'),
                            'default_quantity' => round((float) ($item->default_quantity ?? 0), 6),
                            'stock' => round((float) ($branch?->stock ?? 0), 6),
                            'price' => round((float) ($branch?->price ?? 0), 2),
                            'image' => $product?->image ? asset('storage/' . $product->image) : null,
                            'has_recipe' => (bool) $this->getActiveRecipe($branchId, (int) ($product?->id ?? 0)),
                        ];
                    })
                    ->filter(fn ($item) => (int) ($item['product_id'] ?? 0) > 0)
                    ->values();

                return [
                    'id' => (int) $group->id,
                    'name' => $group->name ?: ('Grupo ' . ((int) $group->sort_order + 1)),
                    'required_quantity' => round((float) ($group->required_quantity ?? 0), 6),
                    'items' => $items->all(),
                    'default_selection' => $items
                        ->filter(fn ($item) => (float) ($item['default_quantity'] ?? 0) > 0)
                        ->map(fn ($item) => [
                            'group_id' => (int) $group->id,
                            'product_id' => (int) $item['product_id'],
                            'quantity' => round((float) $item['default_quantity'], 6),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'product_id' => (int) $promotion->id,
            'product_name' => (string) ($promotion->description ?? 'Promoción'),
            'allows_mix_and_match' => (bool) ($promotion->promotion_mix_and_match ?? false),
            'groups' => $groups,
        ];
    }

    private function getPromotionDefinition(int $productId): ?array
    {
        if (array_key_exists($productId, $this->promotionCache)) {
            return $this->promotionCache[$productId];
        }

        $promotion = Product::query()
            ->with([
                'promotionGroups.items.product',
            ])
            ->find($productId);

        if (! $promotion || ! $promotion->is_promotion) {
            return $this->promotionCache[$productId] = null;
        }

        return $this->promotionCache[$productId] = [
            'product_id' => (int) $promotion->id,
            'product_name' => (string) ($promotion->description ?? 'Promoción'),
            'allows_mix_and_match' => (bool) ($promotion->promotion_mix_and_match ?? false),
            'groups' => $promotion->promotionGroups
                ->sortBy('sort_order')
                ->map(function (ProductPromotionGroup $group) {
                    $items = $group->items
                        ->sortBy('sort_order')
                        ->map(function ($item) {
                            return [
                                'product_id' => (int) ($item->product_id ?? 0),
                                'product_name' => (string) ($item->product?->description ?? ('Producto #' . (int) ($item->product_id ?? 0))),
                                'default_quantity' => round((float) ($item->default_quantity ?? 0), 6),
                            ];
                        })
                        ->values()
                        ->all();

                    return [
                        'id' => (int) $group->id,
                        'name' => $group->name ?: ('Grupo ' . ((int) $group->sort_order + 1)),
                        'required_quantity' => round((float) ($group->required_quantity ?? 0), 6),
                        'items' => $items,
                        'default_selection' => collect($items)
                            ->filter(fn ($item) => (float) ($item['default_quantity'] ?? 0) > 0)
                            ->map(fn ($item) => [
                                'group_id' => (int) $group->id,
                                'product_id' => (int) $item['product_id'],
                                'quantity' => round((float) ($item['default_quantity'] ?? 0), 6),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function getActiveRecipe(int $branchId, int $productId): ?Recipe
    {
        $cacheKey = $branchId . ':' . $productId;
        if (! array_key_exists($cacheKey, $this->recipeCache)) {
            $this->recipeCache[$cacheKey] = Recipe::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('status', 'A')
                ->with('ingredients')
                ->first();
        }

        return $this->recipeCache[$cacheKey];
    }

    private function roundUpToQuarter(float $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        return round(ceil($qty * 4) / 4, 6);
    }

    private function cleanNullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : null;
    }

    private function toPositiveDecimal($value, float $default = 0): float
    {
        $number = round((float) $value, 6);
        return $number > 0 ? $number : $default;
    }
}
