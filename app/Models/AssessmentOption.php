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
        return $value ? url('public/' . ltrim($value, '/')) : null;
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