<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class TextToSpeechService
{
    /*
    |--------------------------------------------------------------------------
    | GENERATE AUDIO
    |--------------------------------------------------------------------------
    */

    public function generate(
        string $text,
        string $fileName,
        ?int $topicId = null,
        string $languageCode = 'en'
    ): string {

        $languageCode = strtolower(
            trim($languageCode)
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | DIRECTORY
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | uploads/curriculum/programs/topic-audio/
            |     1/
            |       en/
            |       hi/
            |       pa/
            |
            */

            $directory =
                'uploads/curriculum/programs/topic-audio';

            /*
            |--------------------------------------------------------------------------
            | TOPIC DIRECTORY
            |--------------------------------------------------------------------------
            */

            if ($topicId) {

                $directory .= '/' . $topicId;
            }

            /*
            |--------------------------------------------------------------------------
            | LANGUAGE DIRECTORY
            |--------------------------------------------------------------------------
            */

            $directory .= '/' . $languageCode;

            /*
            |--------------------------------------------------------------------------
            | FINAL PATH
            |--------------------------------------------------------------------------
            */

            $path =
                $directory .
                '/' .
                $fileName .
                '.mp3';

            $fullPath = public_path($path);

            /*
            |--------------------------------------------------------------------------
            | CREATE DIRECTORY
            |--------------------------------------------------------------------------
            */

            if (! file_exists(dirname($fullPath))) {

                mkdir(
                    dirname($fullPath),
                    0777,
                    true
                );
            }

            /*
            |--------------------------------------------------------------------------
            | LOG REQUEST
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OPENAI TTS REQUEST',
                [
                    'topic_id' => $topicId,
                    'language' => $languageCode,
                    'file_name' => $fileName,
                    'text_length' => strlen($text),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | USE EXISTING OPENAI SERVICE
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Do NOT use OpenAI Laravel Facade here.
            |
            | This keeps the existing AI architecture intact.
            |
            */

            $audioBinary = app(OpenAIService::class)->speech(
                $text,
                $languageCode
            );

            /*
            |--------------------------------------------------------------------------
            | OPENAI FAILED
            |--------------------------------------------------------------------------
            */

            if (! $audioBinary) {

                throw new \RuntimeException(
                    'OpenAI TTS returned empty audio response.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE AUDIO
            |--------------------------------------------------------------------------
            */

            $written = file_put_contents(
                $fullPath,
                $audioBinary
            );

            if ($written === false) {

                throw new \RuntimeException(
                    'Unable to save generated audio file.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS LOG
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OPENAI TTS AUDIO SAVED',
                [
                    'topic_id' => $topicId,
                    'language' => $languageCode,
                    'path' => $path,
                    'size' => $written,
                ]
            );

            return $path;

        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'OpenAI TTS Generation Failed',
                [
                    'file_name' => $fileName,
                    'topic_id' => $topicId,
                    'language' => $languageCode,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );

            throw $e;
        }
    }
}

