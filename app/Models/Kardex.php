<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kardex extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kardex';

    protected $fillable = [
        'detalle_id',
        'producto_id',
        'unidad_id',
        'cantidad',
        'preciounitario',
        'moneda',
        'tipocambio',
        'total',
        'fecha',
        'situacion',
        'usuario_id',
        'usuario',
        'movimiento_id',
        'tipomovimiento_id',
        'tipodocumento_id',
        'sucursal_id',
        'stockanterior',
        'stockactual',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'preciounitario' => 'decimal:6',
        'tipocambio' => 'decimal:3',
        'total' => 'decimal:6',
        'fecha' => 'datetime',
        'stockanterior' => 'decimal:6',
        'stockactual' => 'decimal:6',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unidad_id');
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class, 'movimiento_id');
    }
}
