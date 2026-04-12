<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'label',
        'is_system',
        'is_active',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected static function booted()
    {
        static::deleting(function ($role) {

            if ($role->is_system) {
                throw new \Exception('System roles cannot be deleted.');
            }

            if ($role->users()->count() > 0) {
                throw new \Exception('Role is assigned to users.');
            }
        });
    }
}
