<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivablePayable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'person_id',
        'movement_id',
        'total',
        'balance',
        'total_paid',
        'due_at',
        'status',
        'paid_at',
        'branch_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'total' => 'decimal:2',
        'balance' => 'decimal:2',
        'total_paid' => 'decimal:2',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

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
        return $this->hasMany(AccountReceivablePayableDetail::class);
    }

    public function orderMovement()
    {
        return $this->hasOne(OrderMovement::class, 'account_receivable_payable_id');
    }
}
