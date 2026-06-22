<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class WarehouseMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status',
        'movement_id',
        'branch_id',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function details()
    {
        return $this->hasMany(WarehouseMovementDetail::class);
    }
}
