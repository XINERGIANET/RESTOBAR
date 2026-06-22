<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ruc',
        'company_id',
        'legal_name',
        'logo',
        'address',
        'location_id',
        'allow_zero_stock_sales',
    ];

    protected $casts = [
        'allow_zero_stock_sales' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function people()
    {
        return $this->hasMany(Person::class);
    }

    public function views(): BelongsToMany
    {
        return $this->belongsToMany(View::class, 'view_branch')
            ->withTimestamps()
            ->withPivot('deleted_at');
    }

    public function productTypes()
    {
        return $this->hasMany(ProductType::class, 'branch_id');
    }

    public function branchPaymentMethods()
    {
        return $this->hasMany(BranchPaymentMethod::class);
    }

    public function electronicBillingConfig()
    {
        return $this->hasOne(BranchElectronicBillingConfig::class);
    }
}
