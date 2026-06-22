<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class View extends Model
{
    protected $table = 'views';
    protected $fillable = ['name', 'abbreviation', 'status'];

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'view_id');
    }

    public function menuOptions(): HasMany
    {
        return $this->hasMany(MenuOption::class, 'view_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'view_branch')
            ->withTimestamps()
            ->withPivot('deleted_at');
    }
}
