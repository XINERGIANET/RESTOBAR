<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesMovementDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'detail_type',
        'sales_movement_id',
        'code',
        'description',
        'product_id',
        'product_snapshot',
        'unit_id',
        'tax_rate_id',
        'tax_rate_snapshot',
        'quantity',
        'courtesy_quantity',
        'amount',
        'discount_percentage',
        'original_amount',
        'comment',
        'parent_detail_id',
        'complements',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'tax_rate_snapshot' => 'array',
        'complements' => 'array',
        'quantity' => 'decimal:6',
        'courtesy_quantity' => 'integer',
        'amount' => 'decimal:6',
        'discount_percentage' => 'decimal:6',
        'original_amount' => 'decimal:6',
    ];

    public function salesMovement()
    {
        return $this->belongsTo(SalesMovement::class, 'sales_movement_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
