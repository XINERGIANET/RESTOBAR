<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_methods';
    protected $fillable = ['description', 'order_num', 'status'];

    /**
     * Restringe la consulta a los métodos habilitados para la sucursal (tabla branch_payment_methods).
     * Si la sucursal no tiene filas en el pivote, no se aplica filtro (equivalente a todos los activos).
     *
     * @return array<int>|null null = sin restricción explícita
     */
    public static function paymentMethodIdsForBranchOrNull(?int $branchId): ?array
    {
        if (!$branchId) {
            return null;
        }

        $ids = DB::table('branch_payment_methods')
            ->where('branch_id', $branchId)
            ->where('status', 'E')
            ->pluck('payment_method_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return null;
        }

        return $ids;
    }

    /**
     * Aplica restricción por sucursal si existe configuración en branch_payment_methods.
     */
    public function scopeRestrictedToBranch(Builder $query, ?int $branchId): Builder
    {
        $ids = self::paymentMethodIdsForBranchOrNull($branchId);
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids);
    }
}

