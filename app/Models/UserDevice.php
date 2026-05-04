<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'device_type',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query)
    {
        return $query->whereNotNull('fcm_token');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function updateToken($token)
    {
        $this->update([
            'fcm_token'   => $token,
            'last_used_at' => now(),
        ]);
    }

    public function deactivate()
    {
        $this->update([
            'fcm_token' => null,
        ]);
    }
}
