<?php

namespace App\Models;

class UserContentProgress extends BaseModel
{
    protected $fillable = [
        'user_id',
        'topic_content_id',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function content()
    {
        return $this->belongsTo(TopicContent::class, 'topic_content_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (CRITICAL)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::saving(function ($progress) {

            // ❗ prevent duplicate entries
            $exists = self::where('user_id', $progress->user_id)
                ->where('topic_content_id', $progress->topic_content_id)
                ->when($progress->id, fn($q) => $q->where('id', '!=', $progress->id))
                ->exists();

            if ($exists) {
                throw new \Exception('Content progress already exists.');
            }

            // ❗ prevent un-reading
            if ($progress->exists && $progress->is_read) {

                if ($progress->isDirty('is_read') && !$progress->is_read) {
                    throw new \Exception('Read progress cannot be reverted.');
                }
            }

            // ❗ auto set read_at
            if ($progress->is_read && !$progress->read_at) {
                $progress->read_at = now();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOTHING
    }

    public function cascadeRestore()
    {
        // nothing required
    }
}
