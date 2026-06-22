<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashMovements extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_concept_id',
        'currency',
        'exchange_rate',
        'total',
        'cash_register_id',
        'cash_register',
        'shift_id',
        'shift_snapshot',
        'movement_id',
        'branch_id',
    ];

    protected $casts = [
        'shift_snapshot' => 'array',
    ];

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function details() 
    {
        return $this->hasMany(CashMovementDetail::class, 'cash_movement_id');
    }
}
