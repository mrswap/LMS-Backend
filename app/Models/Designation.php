<?php

namespace App\Models;

class Designation extends BaseModel
{
    protected $fillable = [
        'name',
        'label',
        'is_active'
    ];

    protected $casts = [
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

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (Soft Delete Safe)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::deleting(function ($designation) {

            // ❗ Block delete if ANY user (even soft deleted) exists
            if ($designation->usersWithTrashed()->count() > 0) {
                throw new \Exception('Designation is assigned to users.');
            }
        });
    }
}
