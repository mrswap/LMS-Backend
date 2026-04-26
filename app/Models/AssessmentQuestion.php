<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentQuestion extends Model
{
    protected $fillable = [
        'assessment_id',
        'question_text',
        'question_type',
        'file',
        'marks',
        'order'
    ];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function options()
    {
        return $this->hasMany(AssessmentOption::class, 'question_id');
    }

    public function getFileAttribute($value)
    {
        if (!$value) return null;

        return url('public/' . ltrim($value, '/'));
    }
}
