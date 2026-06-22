<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderMovementDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_movement_id',
        'code',
        'description',
        'product_id',
        'product_snapshot',
        'unit_id',
        'tax_rate_id',
        'tax_rate_snapshot',
        'quantity',
        'courtesy_quantity',
        'takeaway_quantity',
        'amount',
        'comment',
        'commanded_at',
        'branch_id',
        'parent_detail_id',
        'complements',
        'status',
    ];

    protected $casts = [
        'commanded_at' => 'datetime',
        'product_snapshot' => 'array',
        'tax_rate_snapshot' => 'array',
        'complements' => 'array',
        'quantity' => 'decimal:6',
        'courtesy_quantity' => 'decimal:6',
        'takeaway_quantity' => 'decimal:6',
        'amount' => 'decimal:6',
    ];

    public function orderMovement()
    {
        return $this->belongsTo(OrderMovement::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(){
        return $this->belongsTo(TaxRate::class);
    }

}
