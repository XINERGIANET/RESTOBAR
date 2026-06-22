<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function people()
    {
        return $this->belongsToMany(Person::class, 'role_person')
            ->withPivot('branch_id')
            ->withTimestamps();
    }
}
