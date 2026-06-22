<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductBranch extends Model
{
    protected $table = 'product_branch';

    protected $fillable = [
        'status',
        'expiration_date',
        'stock_minimum',
        'stock_maximum',
        'minimum_sell',
        'minimum_purchase',
        'favorite',
        'tax_rate_id',
        'unit_sale',
        'product_id',
        'branch_id',
        'duration_minutes',
        'supplier_id',
        'stock',
        'price',
        'purchase_price',
    ];

    protected $casts = [
        'stock' => 'decimal:4',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'stock_minimum' => 'decimal:4',
        'stock_maximum' => 'decimal:4',
        'minimum_sell' => 'decimal:4',
        'minimum_purchase' => 'decimal:4',
        'favorite' => 'string',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Ticketeras asignadas (varias por producto+sucursal) vía pivote product_branch_printer.
     */
    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(
            PrinterBranch::class,
            'product_branch_printer',
            'product_branch_id',
            'printer_id'
        )->withTimestamps();
    }
}
