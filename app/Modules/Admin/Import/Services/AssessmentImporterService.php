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
        | GROUP BY TOPIC CODE
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
            | AVOID DUPLICATE ASSESSMENT
            |--------------------------------------------------------------------------
            */

            $exists = Assessment::where(

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

                'type' => 'mcq',

                'title' => 'Topic Assessment',

                'description' =>
                'Imported MCQ Assessment',

                'duration' => 0,

                'passing_score' => 0,

                'total_marks' => count($items),

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

                    preg_match(
                        '/^([A-Z])\./',
                        $optionText,
                        $match
                    );

                    $optionLetter =
                        strtoupper(
                            $match[1] ?? ''
                        );

                    AssessmentOption::create([

                        'question_id' =>
                        $question->id,

                        'option_text' =>
                        $optionText,

                        'is_correct' =>
                        $optionLetter ===
                            strtoupper(
                                $item['answer']
                            ),
                    ]);
                }
            }
        }
    }
}
