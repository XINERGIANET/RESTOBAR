<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'moved_at',
        'user_id',
        'user_name',
        'person_id',
        'person_name',
        'responsible_id',
        'responsible_name',
        'comment',
        'status',
        'movement_type_id',
        'document_type_id',
        'branch_id',
        'parent_movement_id',
        'electronic_invoice_provider',
        'electronic_invoice_status',
        'electronic_invoice_external_id',
        'electronic_invoice_series',
        'electronic_invoice_number',
        'electronic_invoice_file_name',
        'electronic_invoice_pdf_ticket_url',
        'electronic_invoice_pdf_a4_url',
        'electronic_invoice_xml_url',
        'electronic_invoice_cdr_url',
        'electronic_invoice_response',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
        'electronic_invoice_response' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function movementType()
    {
        return $this->belongsTo(MovementType::class);
    }
        public function movement()
    {
        return $this->belongsTo(Movement::class, 'parent_movement_id');
    }


    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }  
    public function cashMovement()
    {
        return $this->hasOne(CashMovements::class, 'movement_id');
    }

    public function salesMovement()
    {
        return $this->hasOne(SalesMovement::class, 'movement_id');
    }

    public function purchaseMovement()
    {
        return $this->hasOne(PurchaseMovement::class, 'movement_id');
    }

    public function orderMovement()
    {
        return $this->hasOne(OrderMovement::class, 'movement_id');
    }

    public function warehouseMovement()
    {
        return $this->hasOne(WarehouseMovement::class, 'movement_id');
    }
}
