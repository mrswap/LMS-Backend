<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Models\Module;
use App\Models\Topic;

class AssessmentImporterService
{
    /*
    |--------------------------------------------------------------------------
    | Import Assessments
    |--------------------------------------------------------------------------
    */

    public function import(
        array $parsedData
    ): void {

        /*
        |--------------------------------------------------------------------------
        | EXTRACT
        |--------------------------------------------------------------------------
        */

        $questions =
            $parsedData['questions']
            ?? [];

        $checklists =
            $parsedData['checklists']
            ?? [];

        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        if (empty($questions)) {

            logger()->warning(
                'NO QUESTIONS FOUND'
            );

            return;
        }

        logger()->info('IMPORT QUESTIONS', [
            'count' => count($questions),
        ]);

        /*
        |--------------------------------------------------------------------------
        | GROUP QUESTIONS
        |--------------------------------------------------------------------------
        */

        $grouped = [];

        foreach ($questions as $question) {

            $assessmentType =
                $question['assessment_type']
                ?? 'topic';

            /*
            |--------------------------------------------------------------------------
            | TOPIC
            |--------------------------------------------------------------------------
            */

            if ($assessmentType === 'topic') {

                $topicCode =
                    $question['topic_code']
                    ?? null;

                if (!$topicCode) {
                    continue;
                }

                $key =
                    'topic_' . $topicCode;

                $grouped[$key][] =
                    $question;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MODULE
            |--------------------------------------------------------------------------
            */

            if ($assessmentType === 'module') {

                $moduleCode =
                    $question['module_code']
                    ?? null;

                if (!$moduleCode) {
                    continue;
                }

                $key =
                    'module_' . $moduleCode;

                $grouped[$key][] =
                    $question;

                continue;
            }
        }

        logger()->info('GROUPED ASSESSMENTS', [
            'groups' => array_keys($grouped),
        ]);

        /*
        |--------------------------------------------------------------------------
        | LOOP GROUPS
        |--------------------------------------------------------------------------
        */

        foreach ($grouped as $groupKey => $items) {

            if (empty($items)) {
                continue;
            }

            $firstItem =
                $items[0];

            $assessmentableId = null;

            $assessmentableType = null;

            $assessmentType = null;

            $assessmentTitle = null;

            $createdBy = null;

            /*
            |--------------------------------------------------------------------------
            | TOPIC ASSESSMENT
            |--------------------------------------------------------------------------
            */

            if (
                ($firstItem['assessment_type'] ?? 'topic')
                === 'topic'
            ) {

                $topicCode =
                    $firstItem['topic_code']
                    ?? null;

                logger()->info('TOPIC LOOKUP', [
                    'topic_code' => $topicCode
                ]);

                $topic = Topic::query()

                    ->whereRaw(
                        "REPLACE(title, ' ', '') LIKE ?",
                        [
                            '%Topic' .
                                str_replace(' ', '', $topicCode) .
                                ':%'
                        ]
                    )

                    ->first();

                logger()->info('TOPIC FOUND', [
                    'topic_id' =>
                    $topic?->id,

                    'title' =>
                    $topic?->title,
                ]);

                if (!$topic) {

                    continue;
                }

                $assessmentableId =
                    $topic->id;

                $assessmentableType =
                    Topic::class;

                $assessmentType =
                    'topic';

                $assessmentTitle =
                    'Topic Assessment';

                $createdBy =
                    $topic->created_by;
            }

            /*
            |--------------------------------------------------------------------------
            | MODULE ASSESSMENT
            |--------------------------------------------------------------------------
            */ elseif (
                ($firstItem['assessment_type'] ?? null)
                === 'module'
            ) {

                $moduleCode =
                    $firstItem['module_code']
                    ?? null;

                logger()->info('MODULE LOOKUP', [
                    'module_code' => $moduleCode
                ]);

                $module = Module::query()

                    ->whereRaw(
                        "REPLACE(title, ' ', '') LIKE ?",
                        [
                            '%Module' .
                                str_replace(' ', '', $moduleCode) .
                                ':%'
                        ]
                    )

                    ->first();

                logger()->info('MODULE FOUND', [
                    'module_id' =>
                    $module?->id,

                    'title' =>
                    $module?->title,
                ]);

                if (!$module) {

                    continue;
                }

                $assessmentableId =
                    $module->id;

                $assessmentableType =
                    Module::class;

                $assessmentType =
                    'module';

                $assessmentTitle =
                    'Module Final Assessment';

                $createdBy =
                    $module->created_by;
            }

            /*
            |--------------------------------------------------------------------------
            | INVALID
            |--------------------------------------------------------------------------
            */

            if (
                !$assessmentableId
                || !$assessmentableType
            ) {

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
                    $assessmentableId
                )

                ->where(
                    'assessmentable_type',
                    $assessmentableType
                )

                ->where(
                    'type',
                    $assessmentType
                )

                ->exists();

            if ($exists) {

                logger()->warning('ASSESSMENT EXISTS', [
                    'group' => $groupKey
                ]);

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL MARKS
            |--------------------------------------------------------------------------
            */

            $totalMarks =
                count($items);

            /*
            |--------------------------------------------------------------------------
            | PASSING SCORE
            |--------------------------------------------------------------------------
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
                $assessmentableId,

                'assessmentable_type' =>
                $assessmentableType,

                'type' =>
                $assessmentType,

                'title' =>
                $assessmentTitle,

                'description' =>
                'Imported Assessment',

                'duration' =>
                10,

                'passing_score' =>
                $passingScore,

                'total_marks' =>
                $totalMarks,

                'status' =>
                true,

                'created_by' =>
                $createdBy,
            ]);

            logger()->info('ASSESSMENT CREATED', [
                'assessment_id' =>
                $assessment->id,

                'type' =>
                $assessmentType,
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
                        trim(
                            $item['question']
                                ?? ''
                        ),

                        'question_type' =>
                        'mcq',

                        'marks' =>
                        1,

                        'order' =>
                        $index + 1,

                        'is_case' => ($item['question_type'] ?? 'normal')
                            === 'case',

                        'case_title' =>
                        $item['case_title']
                            ?? null,

                        'case_text' =>
                        $item['case_text']
                            ?? null,

                        'case_order' =>
                        $item['case_order']
                            ?? null,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | OPTIONS
                |--------------------------------------------------------------------------
                */

                foreach (
                    ($item['options'] ?? [])
                    as $option
                ) {

                    $optionText =
                        trim(
                            $option['text']
                                ?? ''
                        );

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

                    $correctAnswer = strtoupper(
                        trim(
                            preg_replace(
                                '/[^A-Z]/i',
                                '',
                                $item['answer']
                                    ?? ''
                            )
                        )
                    );

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
            | RECALCULATE
            |--------------------------------------------------------------------------
            */

            $assessment
                ->recalculateQuestionMarks();
        }
    }
}
