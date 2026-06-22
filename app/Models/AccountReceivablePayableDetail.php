<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivablePayableDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_receivable_payable_id',
        'movement_id',
        'amount',
        'branch_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function accountReceivablePayable()
    {
        return $this->belongsTo(AccountReceivablePayable::class);
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
