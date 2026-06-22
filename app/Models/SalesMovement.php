<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_snapshot',
        'series',
        'year',
        'detail_type',
        'consumption',
        'payment_type',
        'status',
        'sale_type',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax',
        'total',
        'movement_id',
        'branch_id',
        'account_receivable_payable_id',
        'credit_days',
        'debt_due_at',
    ];

    protected $casts = [
        'branch_snapshot' => 'array',
        'exchange_rate' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'debt_due_at' => 'datetime',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function accountReceivablePayable()
    {
        return $this->belongsTo(AccountReceivablePayable::class);
    }

    public function details()
    {
        return $this->hasMany(SalesMovementDetail::class, 'sales_movement_id');
    }
}
