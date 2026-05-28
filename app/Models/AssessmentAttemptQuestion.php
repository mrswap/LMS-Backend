<?php

namespace App\Models;

class AssessmentAttemptQuestion extends BaseModel
{
    protected $fillable = [
        'attempt_id',
        'question_id',
    ];

    public function attempt()
    {
        return $this->belongsTo(
            AssessmentAttempt::class,
            'attempt_id'
        );
    }

    public function question()
    {
        return $this->belongsTo(
            AssessmentQuestion::class,
            'question_id'
        );
    }
}
