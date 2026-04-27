<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentFeedback extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_id',
        'attempt_id',
        'rating',
        'review'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }
}
