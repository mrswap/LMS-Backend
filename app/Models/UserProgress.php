<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProgress extends Model
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
}
