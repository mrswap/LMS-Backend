<?php

namespace App\Models;

class Assessment extends BaseModel
{
    protected $fillable = [
        'assessmentable_id',
        'assessmentable_type',
        'type',
        'title',
        'description',
        'file',
        'duration',
        'passing_score',
        'total_marks',
        'status',
        'created_by'
    ];

    protected $casts = [
        'duration'      => 'integer',
        'passing_score' => 'float',
        'total_marks'   => 'float',
        'status'        => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assessmentable()
    {
        return $this->morphTo()->withTrashed();
    }

    public function questions()
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    public function attempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
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
        // delete questions (structure)
        $this->questions()->get()->each->delete();

        // ❗ DO NOT delete attempts
        // attempts = audit records
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->questions()->withTrashed()->get()->each->restore();
    }
}
