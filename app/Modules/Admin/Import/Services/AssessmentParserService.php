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
        | Normalize
        |--------------------------------------------------------------------------
        */

        $text = strip_tags($html);

        $text = html_entity_decode($text);

        $text = preg_replace(
            "/\r\n|\r/",
            "\n",
            $text
        );

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
        | Storage
        |--------------------------------------------------------------------------
        */

        $questions = [];

        $currentQuestion = null;

        /*
        |--------------------------------------------------------------------------
        | Loop
        |--------------------------------------------------------------------------
        */

        foreach ($lines as $line) {

            /*
            |--------------------------------------------------------------------------
            | QUESTION
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(Q\d+)\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if ($currentQuestion) {

                    $questions[] =
                        $currentQuestion;
                }

                $currentQuestion = [

                    'topic_code' =>
                        trim($matches[1]),

                    'question_code' =>
                        trim($matches[2]),

                    'question' =>
                        trim($matches[3]),

                    'options' => [],

                    'answer' => null,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | OPTION
            |--------------------------------------------------------------------------
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

                $currentQuestion
                ['options'][] = [

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
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(Q\d+)\.A\s+(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                if (!$currentQuestion) {
                    continue;
                }

                $answer = trim(
                    $matches[3]
                );

                if (
                    preg_match(
                        '/([A-Z])$/i',
                        $answer,
                        $answerMatch
                    )
                ) {

                    $answer = strtoupper(
                        $answerMatch[1]
                    );
                }

                $currentQuestion
                ['answer']
                    = $answer;

                continue;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Push last
        |--------------------------------------------------------------------------
        */

        if ($currentQuestion) {

            $questions[] =
                $currentQuestion;
        }

        return $questions;
    }
}