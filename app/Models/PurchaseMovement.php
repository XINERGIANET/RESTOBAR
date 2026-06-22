<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseMovement extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_movements';

    protected $fillable = [
        'json_persona', 'serie', 'anio', 'tipo_detalle', 'incluye_igv',
        'tipo_pago', 'afecta_caja', 'moneda', 'tipocambio', 'subtotal',
        'igv', 'total', 'afecta_kardex', 'movement_id', 'branch_id',
        'payment_image',
    ];

    protected $casts = [
        'json_persona' => 'object', 
        'created_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseMovementDetail::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }
}
