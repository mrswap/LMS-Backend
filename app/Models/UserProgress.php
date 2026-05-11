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

            /*
            |--------------------------------------------------------------
            | ✅ PREVENT DUPLICATE TOPIC PROGRESS
            |--------------------------------------------------------------
            |
            | Only validate duplicates for actual topic rows.
            | Module/level completion rows may have topic_id = null.
            |
            */

            if (!is_null($progress->topic_id)) {

                $exists = self::where('user_id', $progress->user_id)
                    ->where('topic_id', $progress->topic_id)
                    ->when(
                        $progress->id,
                        fn($q) => $q->where('id', '!=', $progress->id)
                    )
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        'Progress already exists for this user and topic.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------
            | ✅ PREVENT REVERTING COMPLETED PROGRESS
            |--------------------------------------------------------------
            |
            | Once completed, progress cannot become incomplete again.
            |
            */

            if (
                $progress->exists &&
                $progress->getOriginal('is_completed') === true &&
                $progress->is_completed === false
            ) {
                throw new \Exception(
                    'Completed progress cannot be reverted.'
                );
            }

            /*
            |--------------------------------------------------------------
            | ✅ AUTO SET completed_at
            |--------------------------------------------------------------
            */

            if (
                $progress->is_completed &&
                is_null($progress->completed_at)
            ) {
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
        // Progress = audit + learning state
    }

    public function cascadeRestore()
    {
        // nothing required
    }
}
