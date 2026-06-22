<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'abbreviation',
        'type',
        'product_type_id',
        'category_id',
        'base_unit_id',
        'kardex',
        'image',
        'complement',
        'complement_mode',
        'classification',
        'features',
        'detail_options',
        'recipe'
    ];

    protected $casts = [
        'kardex' => 'string',
        'type' => 'string',
        'detail_options' => 'array',
        'recipe' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productType()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function productBranches()
    {
        return $this->hasMany(ProductBranch::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'product_branch', 'product_id', 'branch_id')
                    ->withPivot('price', 'stock'); 
    }
}
