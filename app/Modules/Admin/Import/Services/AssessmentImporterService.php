<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Models\Topic;

class AssessmentImporterService
{
    /*
    |--------------------------------------------------------------------------
    | Import Assessments
    |--------------------------------------------------------------------------
    */

    public function import(
        array $questions
    ): void {

        /*
        |--------------------------------------------------------------------------
        | GROUP QUESTIONS BY TOPIC
        |--------------------------------------------------------------------------
        */

        $grouped = [];

        foreach ($questions as $question) {

            $grouped[$question['topic_code']][] = $question;
        }

        /*
        |--------------------------------------------------------------------------
        | LOOP TOPICS
        |--------------------------------------------------------------------------
        */

        foreach ($grouped as $topicCode => $items) {

            /*
            |--------------------------------------------------------------------------
            | FIND TOPIC
            |--------------------------------------------------------------------------
            |
            | OLD ISSUE:
            | Topic::where('title', 'LIKE', '%Topic 1.1.2:%')
            |
            | Your imported title:
            | Topic 1.1.2: Blood Flow Pathways
            |
            | Sometimes spacing/colon/html mismatch happens.
            | So now smarter matching added.
            */

            $topic = Topic::query()

                ->where(function ($query) use ($topicCode) {

                    $query

                        // exact topic code
                        ->where(
                            'title',
                            'LIKE',
                            'Topic ' . $topicCode . ':%'
                        )

                        // fallback
                        ->orWhere(
                            'title',
                            'LIKE',
                            '%' . $topicCode . '%'
                        );
                })

                ->first();

            /*
            |--------------------------------------------------------------------------
            | SKIP IF TOPIC NOT FOUND
            |--------------------------------------------------------------------------
            */

            if (!$topic) {

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE ASSESSMENT
            |--------------------------------------------------------------------------
            */

            $exists = Assessment::query()

                ->where(
                    'assessmentable_id',
                    $topic->id
                )

                ->where(
                    'assessmentable_type',
                    Topic::class
                )

                ->exists();

            if ($exists) {

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL MARKS
            |--------------------------------------------------------------------------
            */

            $totalMarks = count($items);

            /*
            |--------------------------------------------------------------------------
            | PASSING SCORE
            |--------------------------------------------------------------------------
            |
            | 2/3 of total marks
            */

            $passingScore = (int) ceil(
                ($totalMarks * 2) / 3
            );

            /*
            |--------------------------------------------------------------------------
            | CREATE ASSESSMENT
            |--------------------------------------------------------------------------
            */

            $assessment = Assessment::create([

                'assessmentable_id' =>
                    $topic->id,

                'assessmentable_type' =>
                    Topic::class,

                'type' =>
                    'topic',

                'title' =>
                    'Topic Assessment',

                'description' =>
                    'Imported Assessment',

                /*
                |--------------------------------------------------------------------------
                | DEFAULT DURATION = 10
                |--------------------------------------------------------------------------
                */

                'duration' =>
                    10,

                'passing_score' =>
                    $passingScore,

                'total_marks' =>
                    $totalMarks,

                'status' =>
                    true,

                'created_by' =>
                    $topic->created_by,
            ]);

            /*
            |--------------------------------------------------------------------------
            | QUESTIONS
            |--------------------------------------------------------------------------
            */

            foreach ($items as $index => $item) {

                $question = AssessmentQuestion::create([

                    'assessment_id' =>
                        $assessment->id,

                    'question_text' =>
                        trim($item['question']),

                    'question_type' =>
                        'mcq',

                    'marks' =>
                        1,

                    'order' =>
                        $index + 1,
                ]);

                /*
                |--------------------------------------------------------------------------
                | OPTIONS
                |--------------------------------------------------------------------------
                */

                foreach ($item['options'] as $option) {

                    $optionText =
                        trim($option['text']);

                    /*
                    |--------------------------------------------------------------------------
                    | SMART OPTION LETTER DETECTION
                    |--------------------------------------------------------------------------
                    |
                    | SUPPORTS:
                    |
                    | A. Option
                    | B) Option
                    | C Option
                    | D- Option
                    */

                    $letter = '';

                    if (
                        preg_match(
                            '/^\s*([A-Z])[\.\)\-\:]?\s*/i',
                            $optionText,
                            $matches
                        )
                    ) {

                        $letter = strtoupper(
                            $matches[1]
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CLEAN ANSWER
                    |--------------------------------------------------------------------------
                    */

                    $correctAnswer = strtoupper(
                        trim(
                            preg_replace(
                                '/[^A-Z]/i',
                                '',
                                $item['answer'] ?? ''
                            )
                        )
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | STORE OPTION
                    |--------------------------------------------------------------------------
                    */

                    AssessmentOption::create([

                        'question_id' =>
                            $question->id,

                        'option_text' =>
                            $optionText,

                        'is_correct' =>
                            $letter === $correctAnswer,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE MARKS
            |--------------------------------------------------------------------------
            */

            $assessment
                ->recalculateQuestionMarks();
        }
    }
}