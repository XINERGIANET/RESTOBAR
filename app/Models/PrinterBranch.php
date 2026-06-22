<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterBranch extends Model
{
    protected $table = 'printers_branch';

    protected $fillable = [
        'name',
        'width',
        'branch_id',
        'ip',
        'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function productBranches()
    {
        return $this->belongsToMany(
            ProductBranch::class,
            'product_branch_printer',
            'printer_id',
            'product_branch_id'
        )->withTimestamps();
    }

    public function areas()
    {
        return $this->belongsToMany(
            Area::class,
            'area_printer',
            'printer_id',
            'area_id'
        )->withTimestamps();
    }
}
