<?php

namespace App\Modules\Trainee\Assessment\Services;

use App\Models\AssessmentAttempt;

class AssessmentService
{
    public function evaluateAttempt(
        AssessmentAttempt $attempt
    ) {

        /*
    |--------------------------------------------------------------------------
    | LOAD RELATIONS
    |--------------------------------------------------------------------------
    */

        $attempt->load([

            'answers',

            'attemptQuestions.question'
        ]);

        /*
    |--------------------------------------------------------------------------
    | ANSWERS
    |--------------------------------------------------------------------------
    */

        $answers = $attempt->answers;

        /*
    |--------------------------------------------------------------------------
    | ASSIGNED QUESTIONS ONLY
    |--------------------------------------------------------------------------
    */

        $questions = $attempt->attemptQuestions
            ->pluck('question');

        /*
    |--------------------------------------------------------------------------
    | TOTAL
    |--------------------------------------------------------------------------
    */

        $total = $questions->count();

        $correct = 0;

        $wrong = 0;

        $skipped = 0;

        $marks = 0;

        /*
    |--------------------------------------------------------------------------
    | LOOP QUESTIONS
    |--------------------------------------------------------------------------
    */

        foreach ($questions as $question) {

            /*
        |--------------------------------------------------------------------------
        | FIND ANSWER
        |--------------------------------------------------------------------------
        */

            $answer = $answers->firstWhere(
                'question_id',
                $question->id
            );

            /*
        |--------------------------------------------------------------------------
        | SKIPPED
        |--------------------------------------------------------------------------
        */

            if (
                ! $answer ||
                is_null($answer->selected_option_id)
            ) {

                $skipped++;

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | CORRECT
        |--------------------------------------------------------------------------
        */

            if (
                $answer->selected_option_id ==
                $answer->correct_option_id_snapshot
            ) {

                $correct++;
                $marks += (float) $answer->marks_snapshot;

                $marks = round($marks, 2);



                $answer->update([

                    'is_correct' => true,

                    'marks_obtained' =>
                    $answer->marks_snapshot
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | WRONG
        |--------------------------------------------------------------------------
        */ else {

                $wrong++;

                $answer->update([

                    'is_correct' => false,

                    'marks_obtained' => 0
                ]);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | TOTAL AVAILABLE MARKS
    |--------------------------------------------------------------------------
    */

        $totalMarks = $questions->sum(
            'marks'
        );

        /*
    |--------------------------------------------------------------------------
    | PERCENTAGE
    |--------------------------------------------------------------------------
    */


        $totalMarks = round(
            (float) $totalMarks,
            2
        );

        $marks = round(
            (float) $marks,
            2
        );

        $percentage = $totalMarks > 0
            ? ($marks / $totalMarks) * 100
            : 0;

        $percentage = round(
            min($percentage, 100),
            2
        );

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return [

            'total' => $total,

            'correct' => $correct,

            'wrong' => $wrong,

            'skipped' => $skipped,

            'marks' => round($marks, 2),

            'total_marks' => round($totalMarks, 2),

            'percentage' => round($percentage, 2),
        ];
    }
}
