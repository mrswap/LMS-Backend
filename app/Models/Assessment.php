<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
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

    public function assessmentable()
    {
        return $this->morphTo();
    }

    public function questions()
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    public function getFileAttribute($value)
    {
        if (!$value) return null;

        return url($value); // ✅ correct
    }
}
