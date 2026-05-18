<?php

namespace App\Models;

class AssessmentOption extends BaseModel
{
    protected $fillable = [
        'question_id',
        'option_text',
        'file',
        'is_correct'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function question()
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */


    public function getFileAttribute($value)
    {
        if (empty($value)) {
            return url('public/uploads/logo.png');
        }

        // already full URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return url('public/' . ltrim($value, '/'));
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOT delete answers
        // answers depend on option_id historically
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
