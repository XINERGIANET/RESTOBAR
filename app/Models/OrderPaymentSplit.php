<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPaymentSplit extends Model
{
    protected $fillable = [
        'order_movement_id',
        'sequence',
        'mode',
        'subtotal',
        'tax',
        'total',
        'status',
        'movement_id',
        'electronic_invoice_status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:6',
        'tax' => 'decimal:6',
        'total' => 'decimal:6',
    ];

    public function orderMovement(): BelongsTo
    {
        return $this->belongsTo(OrderMovement::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderPaymentSplitDetail::class, 'order_payment_split_id');
    }
}
