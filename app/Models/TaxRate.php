<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'code',
        'description',
        'tax_rate',
        'order_num',
        'status',
    ];

    public function productBranch()
    {
        return $this->hasMany(ProductBranch::class);
    }
}
