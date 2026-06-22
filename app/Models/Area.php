<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(
            PrinterBranch::class,
            'area_printer',
            'area_id',
            'printer_id'
        )->withTimestamps();
    }
}
