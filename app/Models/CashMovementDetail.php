<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovementDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cash_movement_id',
        'type',
        'due_at',
        'paid_at',
        'payment_method_id',
        'payment_method',
        'number',
        'card_id',
        'card',
        'bank_id',
        'bank',
        'digital_wallet_id',
        'digital_wallet',
        'payment_gateway_id',
        'payment_gateway',
        'amount',
        'comment',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'amount' => 'decimal:6', 
    ];

    public function cashMovement()
    {
        return $this->belongsTo(CashMovements::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function digitalWallet()
    {
        return $this->belongsTo(DigitalWallet::class);
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateways::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
