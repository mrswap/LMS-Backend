<?php

namespace App\Models;

class AssessmentQuestion extends BaseModel
{
    protected $fillable = [
        'assessment_id',
        'question_text',
        'question_type',
        'file',
        'marks',
        'order'
    ];

    protected $casts = [
        'marks' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assessment()
    {
        return $this->belongsTo(Assessment::class)->withTrashed();
    }

    public function options()
    {
        return $this->hasMany(AssessmentOption::class, 'question_id');
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

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        $this->options()
            ->cursor()
            ->each(function ($option) {
                $option->delete();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->options()
            ->withTrashed()
            ->cursor()
            ->each(function ($option) {
                $option->restore();
            });
    }
}
