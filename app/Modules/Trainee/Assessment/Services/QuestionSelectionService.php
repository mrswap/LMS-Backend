<?php

namespace App\Modules\Trainee\Assessment\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttemptQuestion;

class QuestionSelectionService
{
    /*
    |--------------------------------------------------------------------------
    | GENERATE QUESTION SET
    |--------------------------------------------------------------------------
    */

    public function generate(
        Assessment $assessment,
        int $userId
    ): array {

        /*
    |--------------------------------------------------------------------------
    | QUESTION LIMIT
    |--------------------------------------------------------------------------
    */

        $questionLimit = $this->getQuestionLimit(
            $assessment
        );

        /*
    |--------------------------------------------------------------------------
    | ALL QUESTIONS
    |--------------------------------------------------------------------------
    */

        $allQuestionIds = $assessment->questions()
            ->pluck('id')
            ->toArray();

        /*
    |--------------------------------------------------------------------------
    | NO QUESTIONS
    |--------------------------------------------------------------------------
    */

        if (empty($allQuestionIds)) {

            return [];
        }

        /*
    |--------------------------------------------------------------------------
    | IF TOTAL QUESTIONS <= LIMIT
    |--------------------------------------------------------------------------
    |
    | Example:
    | Topic has only 5 questions
    | limit also 5
    |
    */

        if (count($allQuestionIds) <= $questionLimit) {

            return collect($allQuestionIds)
                ->shuffle()
                ->values()
                ->toArray();
        }

        /*
    |--------------------------------------------------------------------------
    | PREVIOUSLY USED QUESTIONS
    |--------------------------------------------------------------------------
    */

        $usedQuestionIds = AssessmentAttemptQuestion::whereHas(
            'attempt',
            function ($q) use (
                $userId,
                $assessment
            ) {

                $q->where('user_id', $userId)
                    ->where(
                        'assessment_id',
                        $assessment->id
                    )
                    ->whereIn('status', [
                        'passed',
                        'failed'
                    ]);
            }
        )
            ->pluck('question_id')
            ->unique()
            ->toArray();

        /*
    |--------------------------------------------------------------------------
    | UNUSED QUESTIONS
    |--------------------------------------------------------------------------
    */

        $unusedQuestionIds = array_values(
            array_diff(
                $allQuestionIds,
                $usedQuestionIds
            )
        );

        /*
    |--------------------------------------------------------------------------
    | FIRST / SECOND ATTEMPT
    |--------------------------------------------------------------------------
    */

        if (
            count($unusedQuestionIds)
            >=
            $questionLimit
        ) {

            return collect($unusedQuestionIds)
                ->shuffle()
                ->take($questionLimit)
                ->values()
                ->toArray();
        }

        /*
    |--------------------------------------------------------------------------
    | THIRD+ ATTEMPT
    |--------------------------------------------------------------------------
    */

        return collect($allQuestionIds)
            ->shuffle()
            ->take($questionLimit)
            ->values()
            ->toArray();
    }
    /*
|--------------------------------------------------------------------------
| QUESTION LIMIT
|--------------------------------------------------------------------------
*/

    private function getQuestionLimit(
        Assessment $assessment
    ): int {

        return match ($assessment->type) {

            'topic' => 5,

            'module' => 15,

            default => $assessment->questions()
                ->count(),
        };
    }
}
