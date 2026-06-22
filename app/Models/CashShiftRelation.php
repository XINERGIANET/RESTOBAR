<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashShiftRelation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'started_at',
        'ended_at',
        'status',
        'cash_movement_start_id',
        'cash_movement_end_id',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    
    public function cashMovementStart()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_start_id');
    }

    public function cashMovementEnd()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_end_id');
    }

    public function movements()
    {
        return $this->hasMany(CashMovements::class, 'shift_id');
    }

    public function getTotalIngresosAttribute()
    {
        return $this->movements
            ->where('id', '!=', $this->cash_movement_start_id) 
            ->where('id', '!=', $this->cash_movement_end_id)   
            ->flatMap(function ($movement) {
                return $movement->details->where('type', 'I'); 
            })
            ->sum('amount');
    }

    public function getTotalEgresosAttribute()
    {
        return $this->movements
            ->where('id', '!=', $this->cash_movement_start_id)
            ->where('id', '!=', $this->cash_movement_end_id)
            ->flatMap(function ($movement) {
                return $movement->details->where('type', 'E');
            })
            ->sum('amount');
    }
}
