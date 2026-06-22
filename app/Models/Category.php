<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;


class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'description',
        'abbreviation',
        'image',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeForBranchMenu($query, int $branchId, string $menuType)
    {
        return $query->whereIn('categories.id', function ($sub) use ($branchId, $menuType) {
            $sub->select('category_id')
                ->from('category_branch')
                ->where('branch_id', $branchId)
                ->whereIn('menu_type', [$menuType, 'GENERAL'])
                ->whereNull('deleted_at');
        });
    }
}
