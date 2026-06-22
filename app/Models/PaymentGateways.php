<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateways extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_gateways';
    protected $fillable = ['description', 'order_num', 'status'];
}
