<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Branch;
use App\Models\Product;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            // 1. Obtener y ordenar productos finales
            $productosFinales = Product::where('type', 'PRODUCT')
                ->whereHas('productBranches', fn($q) => $q->where('branch_id', $branch->id))
                ->orderBy('created_at', 'asc')
                ->get();

            $contador = 1;
            foreach ($productosFinales as $producto) {
                $producto->code = 'CH_' . str_pad($contador, 3, '0', STR_PAD_LEFT);
                $producto->save();
                $contador++;
            }

            // 2. Obtener y ordenar insumos/ingredientes
            $insumos = Product::where('type', 'INGREDENT')
                ->whereHas('productBranches', fn($q) => $q->where('branch_id', $branch->id))
                ->orderBy('created_at', 'asc')
                ->get();

            $contadorInsumo = 1;
            foreach ($insumos as $insumo) {
                $insumo->code = 'IN_' . str_pad($contadorInsumo, 3, '0', STR_PAD_LEFT);
                $insumo->save();
                $contadorInsumo++;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this migration is not safely possible as we don't have the old codes
    }
};
