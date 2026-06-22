<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modules'; 
    protected $fillable = ['name', 'icon', 'order_num'];

    public function menuOptions()
    {
        return $this->hasMany(MenuOption::class, 'module_id');
    }
}