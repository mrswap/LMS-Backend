<?php

namespace App\Modules\Trainee\Assessment\Services;

use App\Models\AssessmentAttempt;

class AssessmentService
{
    public function evaluateAttempt(AssessmentAttempt $attempt)
    {
        
    $attempt->load(['answers', 'assessment.questions']);

        $answers = $attempt->answers;
        $questions = $attempt->assessment->questions;

        $total = $questions->count(); // ✅ FIXED (not answers count)

        $correct = 0;
        $wrong = 0;
        $skipped = 0;
        $marks = 0;

        foreach ($questions as $question) {

            // answer find for this question
            $answer = $answers->firstWhere('question_id', $question->id);

            // ❗ skipped case (no answer OR null)
            if (!$answer || is_null($answer->selected_option_id)) {
                $skipped++;
                continue;
            }

            // ✅ correct answer
            if ($answer->selected_option_id == $answer->correct_option_id_snapshot) {

                $correct++;
                $marks += $answer->marks_snapshot;

                $answer->update([
                    'is_correct' => true,
                    'marks_obtained' => $answer->marks_snapshot
                ]);
            } else {
                // ❌ wrong answer
                $wrong++;

                $answer->update([
                    'is_correct' => false,
                    'marks_obtained' => 0
                ]);
            }
        }

        // ✅ correct percentage calculation
        $totalMarks = $attempt->assessment->total_marks;

        $percentage = $totalMarks > 0
            ? ($marks / $totalMarks) * 100
            : 0;

        return [
            'total' => $total,
            'correct' => $correct,
            'wrong' => $wrong,
            'skipped' => $skipped,
            'marks' => $marks,
            'percentage' => round($percentage, 2),
        ];
    }
}
