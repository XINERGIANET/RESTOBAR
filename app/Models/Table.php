<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'capacity',
        'status',
        'situation',
        'opened_at',
        'area_id',
        'branch_id',
        ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
