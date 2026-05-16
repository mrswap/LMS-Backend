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
    | Import
    |--------------------------------------------------------------------------
    */

    public function import(
        array $questions
    ): void {

        /*
        |--------------------------------------------------------------------------
        | GROUP BY TOPIC
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
            */

            $topic = Topic::query()

                ->where(
                    'title',
                    'LIKE',
                    '%Topic ' . $topicCode . ':%'
                )

                ->first();

            if (!$topic) {

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE
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
            | CREATE ASSESSMENT
            |--------------------------------------------------------------------------
            */

            $assessment = Assessment::create([

                'assessmentable_id' =>
                $topic->id,

                'assessmentable_type' =>
                Topic::class,

                'type' => 'topic',

                'title' =>
                'Topic Assessment',

                'description' =>
                'Imported Assessment',

                'duration' => 0,

                'passing_score' => 0,

                'total_marks' =>
                count($items),

                'status' => true,

                'created_by' =>
                $topic->created_by,
            ]);

            /*
            |--------------------------------------------------------------------------
            | QUESTIONS
            |--------------------------------------------------------------------------
            */

            foreach ($items as $index => $item) {

                $question =
                    AssessmentQuestion::create([

                        'assessment_id' =>
                        $assessment->id,

                        'question_text' =>
                        $item['question'],

                        'question_type' =>
                        'mcq',

                        'marks' => 1,

                        'order' =>
                        $index + 1,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | OPTIONS
                |--------------------------------------------------------------------------
                */

                foreach (
                    $item['options']
                    as $option
                ) {

                    $optionText =
                        trim($option['text']);

                    /*
                    |--------------------------------------------------------------------------
                    | EXTRACT OPTION LETTER
                    |--------------------------------------------------------------------------
                    */

                    preg_match(
                        '/^([A-Z])\./',
                        $optionText,
                        $matches
                    );

                    $letter =
                        strtoupper(
                            $matches[1] ?? ''
                        );

                    AssessmentOption::create([

                        'question_id' =>
                        $question->id,

                        'option_text' =>
                        $optionText,

                        'is_correct' =>
                        $letter ===
                            strtoupper(
                                $item['answer']
                            ),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE
            |--------------------------------------------------------------------------
            */

            $assessment
                ->recalculateQuestionMarks();
        }
    }
}
