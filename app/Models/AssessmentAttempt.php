<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_id',
        'started_at',
        'submitted_at',
        'score',
        'percentage',
        'status',
        'time_taken',
        'submit_type'
    ];

    protected $dates = ['started_at', 'submitted_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
    public function answers()
    {
        return $this->hasMany(AssessmentAnswer::class, 'attempt_id');
    }
    public function assessment()
    {
        return $this->belongsTo(\App\Models\Assessment::class, 'assessment_id');
    }
}
