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
        'is_ai',
        'ai_provider',
        'ai_meta',
        'read_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_ai' => 'boolean',
        'ai_meta' => 'array',
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'sender_name',
        'ai_meta'
    ];

    protected $hidden = [
        'ai_meta',
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

    public function isAi(): bool
    {
        return $this->is_ai === true;
    }

    public function getSenderNameAttribute()
    {
        if ($this->is_ai) {

            return config(
                'ai.name',
                'AVANTE-AI'
            );
        }

        return $this->sender?->name
            ?? 'Unknown User';
    }
}
