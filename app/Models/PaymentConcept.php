<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentConcept extends Model
{
    protected $fillable = ['description', 'type', 'restricted'];

    protected $casts = [
        'restricted' => 'boolean',
    ];
}
