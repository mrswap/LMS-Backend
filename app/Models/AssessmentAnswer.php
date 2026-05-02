<?php

namespace App\Models;

class AssessmentAnswer extends BaseModel
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'question_text_snapshot',
        'options_snapshot',
        'correct_option_id_snapshot',
        'marks_snapshot',
        'selected_option_id',
        'is_correct',
        'marks_obtained'
    ];

    protected $casts = [
        'options_snapshot' => 'array',
        'is_correct'       => 'boolean',
        'marks_obtained'   => 'float',
        'marks_snapshot'   => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships (for debugging/reporting only)
    |--------------------------------------------------------------------------
    */

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id')->withTrashed();
    }

    public function question()
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOTHING
        // This is immutable audit data
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
