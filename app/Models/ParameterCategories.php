<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParameterCategories extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parameter_categories';
    protected $fillable = ['description'];

    public function parameters()
    {
        return $this->hasMany(Parameters::class, 'parameter_category_id', 'id');
    }
}
