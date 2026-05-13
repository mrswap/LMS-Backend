<?php

namespace App\Models;

class SupportThread extends BaseModel
{
    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    const STATUS_OPEN = 'open';

    const STATUS_RESOLVED = 'resolved';

    const STATUS_REOPENED = 'reopened';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'user_id',

        'program_id',
        'level_id',
        'module_id',
        'chapter_id',
        'topic_id',

        'status',

        'last_message_at',

        'resolved_by',
        'resolved_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'last_message_at' => 'datetime',

        'resolved_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function program()
    {
        return $this->belongsTo(Program::class)
            ->withTrashed();
    }

    public function level()
    {
        return $this->belongsTo(Level::class)
            ->withTrashed();
    }

    public function module()
    {
        return $this->belongsTo(Module::class)
            ->withTrashed();
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class)
            ->withTrashed();
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class)
            ->withTrashed();
    }

    public function messages()
    {
        return $this->hasMany(
            SupportMessage::class,
            'thread_id'
        )
            ->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(
            SupportMessage::class,
            'thread_id'
        )
            ->latestOfMany();
    }
    
    public function resolver()
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | UNREAD
    |--------------------------------------------------------------------------
    */

    public function unreadMessages()
    {
        return $this->hasMany(
            SupportMessage::class,
            'thread_id'
        )
            ->whereNull('read_at');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [

            self::STATUS_OPEN,

            self::STATUS_REOPENED,
        ]);
    }

    public function markResolved($userId = null): void
    {
        $this->update([

            'status' => self::STATUS_RESOLVED,

            'resolved_by' => $userId,

            'resolved_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([

            'status' => self::STATUS_REOPENED,

            'resolved_by' => null,

            'resolved_at' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BOOT
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($thread) {

            if (! $thread->status) {

                $thread->status = self::STATUS_OPEN;
            }
        });
    }
}
