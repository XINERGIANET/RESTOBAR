<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BranchParameter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'value',
        'parameter_id',
        'branch_id', 
    ];

    public function parameter()
    {
        return $this->belongsTo(Parameters::class, 'parameter_id', 'id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_parameters', 'parameter_id', 'branch_id')
                    ->withPivot('value')
                    ->withTimestamps();
    }
}