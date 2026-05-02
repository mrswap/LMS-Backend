<?php

namespace App\Models;

class AssessmentFeedback extends BaseModel
{
    protected $fillable = [
        'user_id',
        'assessment_id',
        'attempt_id',
        'rating',
        'review'
    ];

    protected $casts = [
        'rating' => 'integer',
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

    public function assessment()
    {
        return $this->belongsTo(Assessment::class)->withTrashed();
    }

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOTHING
        // Feedback should not delete attempts/users
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // nothing required
    }
}
