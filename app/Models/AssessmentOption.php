<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'file',
        'is_correct'
    ];

    public function question()
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
    
    public function getFileAttribute($value)
    {
        if (!$value) return null;

        return url('public/' . ltrim($value, '/'));
    }
}
