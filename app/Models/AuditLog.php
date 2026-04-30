<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'description',
        'ip',
        'device',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    /*
    |-----------------------------------------
    | 🔗 RELATIONSHIP
    |-----------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}