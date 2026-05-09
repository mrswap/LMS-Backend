<?php

namespace App\Models;

class Permission extends BaseModel
{
    protected $fillable = [
        'module',
        'name',
        'label',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
