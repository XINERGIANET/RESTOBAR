<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPromotionGroupItem extends Model
{
    protected $fillable = [
        'promotion_group_id',
        'product_id',
        'default_quantity',
        'sort_order',
    ];

    protected $casts = [
        'default_quantity' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ProductPromotionGroup::class, 'promotion_group_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
