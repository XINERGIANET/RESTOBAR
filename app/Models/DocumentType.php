<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_types';

    protected $fillable = [
        'name',
        'stock',
        'movement_type_id',
    ];

    public function movementType()
    {
        return $this->belongsTo(MovementType::class);
    }
}
