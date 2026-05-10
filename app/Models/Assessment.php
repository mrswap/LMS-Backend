<?php

namespace App\Models;

use App\Models\Traits\HasPublishStatus;

class Assessment extends BaseModel
{
    use HasPublishStatus;

    protected $hasPublishStatus = true;
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

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // delete question structure
        $this->questions()
            ->cursor()
            ->each(function ($question) {
                $question->delete();
            });

        // ❗ DO NOT delete attempts
        // attempts = audit history
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->questions()
            ->withTrashed()
            ->cursor()
            ->each(function ($question) {
                $question->restore();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Recalculate Question Marks
    |--------------------------------------------------------------------------
    */
    public function recalculateQuestionMarks()
    {
        $questions = $this->questions()
            ->orderBy('id')
            ->get();

        $totalQuestions = $questions->count();

        if ($totalQuestions <= 0) {
            return;
        }

        $baseMark = floor(($this->total_marks / $totalQuestions) * 100) / 100;

        $distributed = 0;

        foreach ($questions as $index => $question) {

            // last question gets remaining balance
            if ($index === $totalQuestions - 1) {

                $mark = round($this->total_marks - $distributed, 2);
            } else {

                $mark = $baseMark;

                $distributed += $mark;
            }

            $question->updateQuietly([
                'marks' => $mark
            ]);
        }
    }
}
