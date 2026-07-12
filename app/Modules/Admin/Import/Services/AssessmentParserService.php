<?php

namespace App\Modules\Admin\Import\Services;

use App\Modules\Admin\Import\DTO\AssessmentImportDTO;

class AssessmentParserService {
    /*
    |--------------------------------------------------------------------------
    | Parse Assessments
    |--------------------------------------------------------------------------
    */

    public function parse(
        string $html
    ): AssessmentImportDTO {


        logger()->info('AssessmentParserService Started');
        /*
        |--------------------------------------------------------------------------
        | PRESERVE BREAKS
        |--------------------------------------------------------------------------
        */

        $html = preg_replace(
            '/<br\s*\/?>/i',
            "\n",
            $html
        );

        $html = preg_replace(
            '/<\/p>/i',
            "</p>\n",
            $html
        );

        $html = preg_replace(
            '/<\/tr>/i',
            "</tr>\n",
            $html
        );

        $html = preg_replace(
            '/<hr[^>]*>/i',
            "\n",
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | CLEAN TEXT
        |--------------------------------------------------------------------------
        */

        $text = strip_tags($html);

        $text = html_entity_decode($text);

        $text = preg_replace(
            "/\r\n|\r/",
            "\n",
            $text
        );

        /*
        |--------------------------------------------------------------------------
        | SPLIT LINES
        |--------------------------------------------------------------------------
        */

        $lines = explode("\n", $text);

        $lines = array_map(function ($line) {

            $line = trim($line);

            $line = preg_replace(
                '/\s+/',
                ' ',
                $line
            );

            return trim($line);
        }, $lines);

        $lines = array_values(
            array_filter($lines)
        );

        /*
        |--------------------------------------------------------------------------
        | STORAGE
        |--------------------------------------------------------------------------
        */

        $questions = [];

        $checklists = [];

        $currentQuestion = null;

        $currentCaseTitle = null;

        $currentCaseText = null;

        $currentCaseOrder = 0;

        $isCollectingCaseDescription = false;

        /*
        |--------------------------------------------------------------------------
        | LOOP
        |--------------------------------------------------------------------------
        */

        foreach ($lines as $line) {

            /*
            |--------------------------------------------------------------------------
            | CASE TITLE
            |--------------------------------------------------------------------------
            |
            | 1.CT1 Bradycardia Case
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(CT\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                $currentCaseOrder++;

                $currentCaseTitle = trim(
                    $matches[3]
                );

                $currentCaseText = '';

                $isCollectingCaseDescription = false;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | CASE DESCRIPTION
            |--------------------------------------------------------------------------
            |
            | 1.CD1
            | Patient presents with dizziness...
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(CD\d+)\s*(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                $currentCaseText = trim(
                    $matches[3]
                );

                $isCollectingCaseDescription = true;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOPIC QUESTION
            |--------------------------------------------------------------------------
            |
            | 1.1.2.Q1 Question
            |
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(Q\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
                &&
                !str_contains($line, '.O1')
                &&
                !str_contains($line, '.O2')
                &&
                !str_contains($line, '.O3')
                &&
                !str_contains($line, '.O4')
                &&
                !str_contains($line, '.A')
            ) {

                if ($currentQuestion) {

                    $questions[] =
                        $currentQuestion;
                }

                $isCollectingCaseDescription = false;

                $questionText =
                    trim($matches[3]);

                /*
                |--------------------------------------------------------------------------
                | REMOVE EXTRA Q1
                |--------------------------------------------------------------------------
                */

                $questionText = preg_replace(
                    '/^Q\d+\s*/i',
                    '',
                    $questionText
                );

                $currentQuestion = [

                    'assessment_type' => 'topic',

                    'module_code' => null,

                    'topic_code' =>
                    trim($matches[1]),

                    'question_code' =>
                    trim($matches[2]),

                    'question_type' =>
                    'normal',

                    'case_title' => null,

                    'case_text' => null,

                    'case_order' => null,

                    'question' =>
                    trim($questionText),

                    'options' => [],

                    'answer' => null,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MODULE MCQ
            |--------------------------------------------------------------------------
            |
            | 1.MMQ1 What is preload?
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(MMQ\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if ($currentQuestion) {

                    $questions[] =
                        $currentQuestion;
                }

                $isCollectingCaseDescription = false;

                $currentQuestion = [

                    'assessment_type' => 'module',

                    'module_code' =>
                    trim($matches[1]),

                    'topic_code' => null,

                    'question_code' =>
                    trim($matches[2]),

                    'question_type' =>
                    'normal',

                    'case_title' => null,

                    'case_text' => null,

                    'case_order' => null,

                    'question' =>
                    trim($matches[3]),

                    'options' => [],

                    'answer' => null,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | CASE QUESTION
            |--------------------------------------------------------------------------
            |
            | 1.CMQ1 Which structure failed?
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(CMQ\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if ($currentQuestion) {

                    $questions[] =
                        $currentQuestion;
                }

                $isCollectingCaseDescription = false;

                $currentQuestion = [

                    'assessment_type' => 'module',

                    'module_code' =>
                    trim($matches[1]),

                    'topic_code' => null,

                    'question_code' =>
                    trim($matches[2]),

                    'question_type' =>
                    'case',

                    'case_title' =>
                    $currentCaseTitle,

                    'case_text' =>
                    trim($currentCaseText),

                    'case_order' =>
                    $currentCaseOrder,

                    'question' =>
                    trim($matches[3]),

                    'options' => [],

                    'answer' => null,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOPIC OPTIONS
            |--------------------------------------------------------------------------
            |
            | 1.1.1.Q1.O1 A. Option
            |
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(Q\d+)\.(O\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if (!$currentQuestion) {
                    continue;
                }

                $currentQuestion['options'][] = [

                    'code' =>
                    trim($matches[3]),

                    'text' =>
                    trim($matches[4]),
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MODULE / CASE OPTIONS
            |--------------------------------------------------------------------------
            |
            | 1.MMQ1.O1 A. Option
            | 1.CMQ1.O1 A. Option
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(MMQ\d+|CMQ\d+)\.(O\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if (!$currentQuestion) {
                    continue;
                }

                $currentQuestion['options'][] = [

                    'code' =>
                    trim($matches[3]),

                    'text' =>
                    trim($matches[4]),
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | ANSWERS
            |--------------------------------------------------------------------------
            |
            | 1.Q1.A Correct Answer: A
            | 1.MMQ1.A Correct Answer: B
            | 1.CMQ1.A Correct Answer: C
            |
            */

            if (

                preg_match(
                    '/Correct\s*Answer\s*:\s*([A-Z])/i',
                    $line,
                    $matches
                )

                ||

                preg_match(
                    '/Answer\s*:\s*([A-Z])/i',
                    $line,
                    $matches
                )

            ) {

                if (!$currentQuestion) {
                    continue;
                }

                $currentQuestion['answer'] =
                    strtoupper(
                        trim($matches[1])
                    );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | COMPETENCY CHECKLIST
            |--------------------------------------------------------------------------
            |
            | 1.CCL1 Identify all chambers
            |
            */

            if (
                preg_match(
                    '/^(\d+)\.(CCL\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                $checklists[] = [

                    'module_code' =>
                    trim($matches[1]),

                    'code' =>
                    trim($matches[2]),

                    'text' =>
                    trim($matches[3]),
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MULTILINE CASE DESCRIPTION
            |--------------------------------------------------------------------------
            */

            if (
                $isCollectingCaseDescription
                &&
                !empty($line)

                &&

                !preg_match(
                    '/^(\d+)\.(CMQ\d+)/i',
                    $line
                )

                &&

                !preg_match(
                    '/^(\d+)\.(MMQ\d+)/i',
                    $line
                )

                &&

                !preg_match(
                    '/^(\d+)\.(CT\d+)/i',
                    $line
                )

                &&

                !preg_match(
                    '/^(\d+)\.(CCL\d+)/i',
                    $line
                )
            ) {

                $currentCaseText .=
                    ' ' . trim($line);

                continue;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PUSH LAST
        |--------------------------------------------------------------------------
        */

        if ($currentQuestion) {

            $questions[] =
                $currentQuestion;
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD DTO
        |--------------------------------------------------------------------------
        */

        $moduleAssessment = [];

        $topicAssessments = [];

        foreach ($questions as $question) {

            /*
            |--------------------------------------------------------------------------
            | MODULE EXAM
            |--------------------------------------------------------------------------
            */

            if ($question['assessment_type'] === 'module') {

                $moduleAssessment['questions'][] = $question;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOPIC QUIZ
            |--------------------------------------------------------------------------
            */

            $topicCode = $question['topic_code'];

            if (! isset($topicAssessments[$topicCode])) {

                $topicAssessments[$topicCode] = [

                    'topic_code' => $topicCode,

                    'questions' => [],
                ];
            }

            $topicAssessments[$topicCode]['questions'][] = $question;
        }

        /*
        |--------------------------------------------------------------------------
        | RETURN DTO
        |--------------------------------------------------------------------------
        */

        return new AssessmentImportDTO(

            moduleAssessment: $moduleAssessment,

            topicAssessments: array_values($topicAssessments)

        );
    }
}
