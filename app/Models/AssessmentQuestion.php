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
        return $value ? url('public/' . ltrim($value, '/')) : null;
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
