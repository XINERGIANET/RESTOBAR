<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Role;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'fecha_nacimiento',
        'genero',
        'person_type',
        'phone',
        'email',
        'pin',
        'document_number',
        'address',
        'location_id',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'person_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_person')
            ->withPivot('branch_id')
            ->withTimestamps();
    }
}
