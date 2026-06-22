<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    public const BEHAVIOR_SELLABLE = 'SELLABLE';

    public const BEHAVIOR_SUPPLY = 'SUPPLY';

    public const BEHAVIOR_BOTH = 'BOTH';

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'behavior',
        'icon',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'product_type_id');
    }

    public function isSellable(): bool
    {
        return $this->behavior === self::BEHAVIOR_SELLABLE || $this->behavior === self::BEHAVIOR_BOTH;
    }

    public function isSupply(): bool
    {
        return $this->behavior === self::BEHAVIOR_SUPPLY;
    }

    public function isBoth(): bool
    {
        return $this->behavior === self::BEHAVIOR_BOTH;
    }
}
