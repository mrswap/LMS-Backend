<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAnswer extends Model
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
    ];
}
