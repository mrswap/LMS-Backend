<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{

    protected $fillable = [
        'user_id',
        'program_id',
        'level_id',
        'topic_id',     
        'type',         
        'assessment_attempt_id',
        'certificate_id',
        'score',
        'percentage',
        'issued_at',
        'status',
        'file',
        'meta'
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'status' => 'boolean',
        'meta' => 'array'
    ];

    // 🔗 relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    public function topic()
    {
        return $this->belongsTo(\App\Models\Topic::class);
    }

    // 🔗 file url
    public function getFileAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    // 🟢 status label
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Revoked';
    }
}
