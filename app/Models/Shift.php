<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
        'start_time',
        'end_time',
        'branch_id',
    ];
    
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
