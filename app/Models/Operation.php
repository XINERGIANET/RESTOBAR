<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Operation extends Model
{
    protected $table = 'operations';
    protected $fillable = ['name', 'icon', 'action', 'view_id', 'view_id_action', 'color', 'status', 'type'];

    public function view()
    {
        return $this->belongsTo(View::class, 'view_id');
    }

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
