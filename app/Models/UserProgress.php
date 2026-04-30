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


    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function program()
    {
        return $this->belongsTo(\App\Models\Program::class);
    }

    public function level()
    {
        return $this->belongsTo(\App\Models\Level::class);
    }

    public function module()
    {
        return $this->belongsTo(\App\Models\Module::class);
    }

    public function chapter()
    {
        return $this->belongsTo(\App\Models\Chapter::class);
    }

    public function topic()
    {
        return $this->belongsTo(\App\Models\Topic::class);
    }
}
