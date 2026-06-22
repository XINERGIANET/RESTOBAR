<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache; // <--- Importante

class MenuOption extends Model
{
    protected $table = 'menu_option';
    protected $fillable = ['name', 'icon', 'action', 'module_id', 'view_id', 'status', 'quick_access'];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    // --- AGREGA ESTO PARA QUE SE ACTUALICE AL GUARDAR/BORRAR ---
    protected static function booted()
    {
        static::saved(function ($option) {
            Cache::forget('sidebar_menu');
        });

        static::deleted(function ($option) {
            Cache::forget('sidebar_menu');
        });
    }
}