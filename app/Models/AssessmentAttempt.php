<?php

namespace App\Models;

class AssessmentAttempt extends BaseModel
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

    protected $casts = [
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'score'        => 'float',
        'percentage'   => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function answers()
    {
        return $this->hasMany(AssessmentAnswer::class, 'attempt_id');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOT delete answers
        // Attempts are audit records
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
