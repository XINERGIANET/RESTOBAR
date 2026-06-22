<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseMovementDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_movement_id',
        'product_id',
        'product_snapshot',
        'unit_id',
        'quantity',
        'comment',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'quantity' => 'decimal:6',
    ];

    public function warehouseMovement()
    {
        return $this->belongsTo(WarehouseMovement::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
