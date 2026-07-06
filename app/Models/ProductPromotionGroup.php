<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPromotionGroup extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'required_quantity',
        'sort_order',
    ];

    protected $casts = [
        'required_quantity' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(ProductPromotionGroupItem::class, 'promotion_group_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
