<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parameters extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parameters';
    protected $fillable = ['description', 'value', 'status', 'parameter_category_id'];
    
    public function parameterCategory()
    {
        return $this->belongsTo(ParameterCategories::class, 'parameter_category_id', 'id');
    }
}
