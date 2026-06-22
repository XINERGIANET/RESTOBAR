<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentSplitDetail extends Model
{
    protected $fillable = [
        'order_payment_split_id',
        'order_movement_detail_id',
        'quantity',
        'amount',
        'tax_rate_snapshot',
        'product_snapshot',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'amount' => 'decimal:6',
        'tax_rate_snapshot' => 'array',
        'product_snapshot' => 'array',
    ];

    public function orderPaymentSplit(): BelongsTo
    {
        return $this->belongsTo(OrderPaymentSplit::class, 'order_payment_split_id');
    }

    public function orderMovementDetail(): BelongsTo
    {
        return $this->belongsTo(OrderMovementDetail::class);
    }
}
