<?php

namespace App\Modules\Admin\Import\Services;

class AssessmentParserService
{
    /*
    |--------------------------------------------------------------------------
    | Parse Assessments
    |--------------------------------------------------------------------------
    */

    public function parse(
        string $html
    ): array {

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

        $currentQuestion = null;

        /*
        |--------------------------------------------------------------------------
        | LOOP
        |--------------------------------------------------------------------------
        */

        foreach ($lines as $line) {

            /*
            |--------------------------------------------------------------------------
            | QUESTION
            |--------------------------------------------------------------------------
            |
            | 1.1.2.Q1 Q1 Which vessel carries blood?
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

                    'topic_code' =>
                    trim($matches[1]),

                    'question_code' =>
                    trim($matches[2]),

                    'question' =>
                    trim($questionText),

                    'options' => [],

                    'answer' => null,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | OPTION
            |--------------------------------------------------------------------------
            |
            | 1.1.2.Q1.O1 A. Aorta
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
            | ANSWER
            |--------------------------------------------------------------------------
            |
            | Supported:
            |
            | 1.1.2.Q1.A Correct Answer: B
            | 1.1.2.Q1.A Answer: C
            | Correct Answer: D
            | Answer: A
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

        return $questions;
    }
}
