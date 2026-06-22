<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ProductsImport implements ToCollection, WithStartRow, WithMultipleSheets
{
    private int $branchId;
    public array $errors = [];
    public int $imported = 0;
    public int $updated = 0;

    // Columnas por índice (0-based):
    // A=0 Codigo | B=1 nombre_producto | C=2 abreviacion | D=3 nombre_categoria
    // E=4 tipo_menu | F=5 tipo_producto | G=6 kardex | H=7 precio
    // I=8 precio_compra | J=9 stock | K=10 unidad

    public function __construct(int $branchId)
    {
        $this->branchId = $branchId;
    }

    public function sheets(): array
    {
        return [0 => $this]; // solo procesar la primera hoja ("Productos")
    }

    public function startRow(): int
    {
        return 2; // fila 1 es el encabezado, empezamos en la 2
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Ignorar filas completamente vacías
            $values = $row->toArray();
            if (empty(array_filter($values, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            try {
                $this->processRow($values, $rowNum);
            } catch (\Throwable $e) {
                $this->errors[] = "Fila {$rowNum}: " . $e->getMessage();
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $codigo          = isset($row[0]) ? trim((string) $row[0]) : '';
        $nombre          = isset($row[1]) ? trim((string) $row[1]) : '';
        $abreviacion     = isset($row[2]) ? trim((string) $row[2]) : '';
        $nombreCategoria = isset($row[3]) ? trim((string) $row[3]) : '';
        $tipoMenu        = strtoupper(trim((string) ($row[4] ?? 'VENTAS_PEDIDOS')));
        $tipoProducto    = isset($row[5]) ? trim((string) $row[5]) : '';
        $kardex          = strtoupper(trim((string) ($row[6] ?? 'N')));
        $precio          = isset($row[7]) ? (float) $row[7] : 0.0;
        $precioCompra    = isset($row[8]) ? (float) $row[8] : 0.0;
        $stock           = isset($row[9]) ? (int) $row[9] : 0;
        $unidad          = isset($row[10]) ? trim((string) $row[10]) : '';

        // ── Validaciones requeridas ───────────────────────────────────────────
        if ($codigo === '') {
            $this->errors[] = "Fila {$rowNum}: El campo 'Codigo' es requerido.";
            return;
        }
        if ($nombre === '') {
            $this->errors[] = "Fila {$rowNum}: El campo 'nombre_producto' es requerido.";
            return;
        }
        if ($nombreCategoria === '') {
            $this->errors[] = "Fila {$rowNum}: El campo 'nombre_categoria' es requerido.";
            return;
        }
        if ($unidad === '') {
            $this->errors[] = "Fila {$rowNum}: El campo 'unidad' es requerido.";
            return;
        }

        // Validar tipo_menu
        if (!in_array($tipoMenu, ['VENTAS_PEDIDOS', 'COMPRAS', 'GENERAL'])) {
            $tipoMenu = 'VENTAS_PEDIDOS';
        }

        // Validar kardex
        if (!in_array($kardex, ['S', 'N'])) {
            $kardex = 'N';
        }

        // ── Buscar unidad ────────────────────────────────────────────────────
        $unit = Unit::whereRaw('LOWER(TRIM(description)) = ?', [strtolower($unidad)])->first();
        if (!$unit) {
            $this->errors[] = "Fila {$rowNum}: Unidad '{$unidad}' no encontrada. Verifica el nombre en la hoja Referencia.";
            return;
        }

        // ── Categoría ────────────────────────────────────────────────────────
        $category = $this->resolveCategory($nombreCategoria, $tipoMenu);

        // ── Tipo de producto ─────────────────────────────────────────────────
        $productType = $this->resolveProductType($tipoProducto);

        $type = ($productType && $productType->isSupply()) ? 'INGREDENT' : 'PRODUCT';

        // ── Crear o actualizar ───────────────────────────────────────────────
        DB::transaction(function () use (
            $codigo, $nombre, $abreviacion, $category, $unit, $productType,
            $type, $kardex, $precio, $precioCompra, $stock
        ) {
            $existingProduct = DB::table('products')
                ->join('product_branch', 'product_branch.product_id', '=', 'products.id')
                ->where('products.code', $codigo)
                ->where('product_branch.branch_id', $this->branchId)
                ->whereNull('products.deleted_at')
                ->whereNull('product_branch.deleted_at')
                ->select('products.id')
                ->first();

            if ($existingProduct) {
                Product::where('id', $existingProduct->id)->update([
                    'description'     => $nombre,
                    'abbreviation'    => $abreviacion !== '' ? $abreviacion : $nombre,
                    'category_id'     => $category->id,
                    'base_unit_id'    => $unit->id,
                    'product_type_id' => $productType?->id,
                    'kardex'          => $kardex,
                    'updated_at'      => now(),
                ]);

                ProductBranch::where('product_id', $existingProduct->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')
                    ->update([
                        'price'          => $precio,
                        'purchase_price' => $precioCompra,
                        'stock'          => $stock,
                        'updated_at'     => now(),
                    ]);

                $this->updated++;
            } else {
                $product = Product::create([
                    'code'             => $codigo,
                    'description'      => $nombre,
                    'abbreviation'     => $abreviacion !== '' ? $abreviacion : $nombre,
                    'type'             => $type,
                    'product_type_id'  => $productType?->id,
                    'category_id'      => $category->id,
                    'base_unit_id'     => $unit->id,
                    'kardex'           => $kardex,
                    'complement'       => 'NO',
                    'complement_mode'  => '',
                    'classification'   => 'GOOD',
                    'features'         => '',
                    'recipe'           => false,
                ]);

                ProductBranch::create([
                    'product_id'       => $product->id,
                    'branch_id'        => $this->branchId,
                    'status'           => 'A',
                    'price'            => $precio,
                    'purchase_price'   => $precioCompra,
                    'stock'            => $stock,
                    'stock_minimum'    => 0,
                    'stock_maximum'    => 0,
                    'minimum_sell'     => 0,
                    'minimum_purchase' => 0,
                    'favorite'         => 'N',
                    'tax_rate_id'      => null,
                    'unit_sale'        => (string) $unit->id,
                    'duration_minutes' => 0,
                ]);

                $this->imported++;
            }
        });
    }

    private function resolveCategory(string $nombre, string $tipoMenu): Category
    {
        $category = Category::whereRaw('LOWER(TRIM(description)) = ?', [strtolower($nombre)])->first();

        if (!$category) {
            $category = Category::create([
                'description'  => $nombre,
                'abbreviation' => strtoupper(substr($nombre, 0, 10)),
            ]);
        }

        $exists = DB::table('category_branch')
            ->where('category_id', $category->id)
            ->where('branch_id', $this->branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            DB::table('category_branch')->insert([
                'category_id' => $category->id,
                'branch_id'   => $this->branchId,
                'menu_type'   => $tipoMenu,
                'status'      => 'E',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return $category;
    }

    private function resolveProductType(?string $nombre): ?ProductType
    {
        if (empty($nombre)) {
            return ProductType::where('branch_id', $this->branchId)
                ->where('behavior', ProductType::BEHAVIOR_SELLABLE)
                ->first();
        }

        return ProductType::where('branch_id', $this->branchId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($nombre)])
            ->first()
            ?? ProductType::where('branch_id', $this->branchId)
                ->where('behavior', ProductType::BEHAVIOR_SELLABLE)
                ->first();
    }
}
