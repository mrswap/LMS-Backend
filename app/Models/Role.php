<?php

namespace App\Models;

class Role extends BaseModel
{
    protected $fillable = [
        'name',
        'label',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function usersWithTrashed()
    {
        return $this->hasMany(User::class)->withTrashed();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (Soft Delete Safe)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::deleting(function ($role) {

            // ❗ Block system roles
            if ($role->is_system) {
                throw new \Exception('System roles cannot be deleted.');
            }

            // ❗ Check INCLUDING soft-deleted users
            if ($role->usersWithTrashed()->count() > 0) {
                throw new \Exception('Role is assigned to users.');
            }
        });
    }
}
