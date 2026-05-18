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
        return $this->hasMany(User::class)
            ->withTrashed();
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isSystemRole(): bool
    {
        return (bool) $this->is_system;
    }

    public function canManagePublishStatus(): bool
    {
        return $this->isSystemRole();
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

            /*
            |--------------------------------------------------------------------------
            | BLOCK SYSTEM ROLE DELETE
            |--------------------------------------------------------------------------
            */

            if ($role->isSystemRole()) {

                throw new \Exception(
                    'System roles cannot be deleted.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | BLOCK IF ASSIGNED
            |--------------------------------------------------------------------------
            */

            if (
                $role->usersWithTrashed()->count() > 0
            ) {

                throw new \Exception(
                    'Role is assigned to users.'
                );
            }
        });
    }
}
