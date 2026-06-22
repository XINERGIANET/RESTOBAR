<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseMovementDetail extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_movement_details';

    protected $fillable = [
        'tipo_detalle',
        'purchase_movement_id',
        'codigo',
        'descripcion',
        'producto_id',
        'json_producto',
        'unidad_id',
        'json_unidad',
        'igv_id',
        'json_igv',
        'cantidad',
        'monto',
        'comentario',
        'situacion',
        'branch_id',
    ];

    protected $casts = [
        'json_producto' => 'object',
        'json_unidad'  => 'object',
        'json_igv'     => 'object',
        'cantidad'     => 'decimal:6',
        'monto'        => 'decimal:6',
        'created_at'   => 'datetime',
    ];

    public function purchaseMovement()
    {
        return $this->belongsTo(PurchaseMovement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

