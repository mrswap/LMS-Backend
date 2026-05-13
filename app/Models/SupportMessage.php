<?php

namespace App\Models;

class SupportMessage extends BaseModel
{
    protected $fillable = [

        'thread_id',
        'sender_id',

        'message',
        'attachment',

        'is_admin',

        'read_at',
    ];

    protected $casts = [

        'is_admin' => 'boolean',

        'read_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function thread()
    {
        return $this->belongsTo(SupportThread::class)
            ->withTrashed();
    }

    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_id'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getAttachmentAttribute($value)
    {
        return $value
            ? url('public/' . ltrim($value, '/'))
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {

            $this->update([
                'read_at' => now(),
            ]);
        }
    }
}
