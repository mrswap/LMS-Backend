<?php

namespace App\Models;

class UserProgress extends BaseModel
{
    protected $fillable = [
        'user_id',
        'program_id',
        'level_id',
        'module_id',
        'chapter_id',
        'topic_id',
        'is_unlocked',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
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

    public function program()
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function level()
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    public function module()
    {
        return $this->belongsTo(Module::class)->withTrashed();
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class)->withTrashed();
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class)->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (CRITICAL)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::saving(function ($progress) {

            // ❗ prevent duplicate entries
            $exists = self::where('user_id', $progress->user_id)
                ->where('topic_id', $progress->topic_id)
                ->when($progress->id, fn($q) => $q->where('id', '!=', $progress->id))
                ->exists();

            if ($exists) {
                throw new \Exception('Progress already exists for this user and topic.');
            }

            // ❗ lock completed records
            if ($progress->exists && $progress->is_completed) {

                if ($progress->isDirty('is_completed') && !$progress->is_completed) {
                    throw new \Exception('Completed progress cannot be reverted.');
                }
            }

            // ❗ auto set completed_at
            if ($progress->is_completed && !$progress->completed_at) {
                $progress->completed_at = now();
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
        // progress = audit + learning state
    }

    public function cascadeRestore()
    {
        // nothing required
    }
}
