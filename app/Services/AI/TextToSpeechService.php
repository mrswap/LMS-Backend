<?php

namespace App\Services\AI;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class TextToSpeechService
{
    public function generate(
        string $text,
        string $fileName,
        ?int $topicId = null
    ): string {

        try {

            /*
            |--------------------------------------------------------------
            | DIRECTORY
            |--------------------------------------------------------------
            */

            $directory =
                'uploads/curriculum/programs/topic-audio';

            /*
            |--------------------------------------------------------------
            | TOPIC WISE FOLDER
            |--------------------------------------------------------------
            */

            if ($topicId) {

                $directory .= '/' . $topicId;
            }

            /*
            |--------------------------------------------------------------
            | FINAL PATH
            |--------------------------------------------------------------
            */

            $path = $directory . '/' . $fileName . '.mp3';

            $fullPath = public_path($path);

            /*
            |--------------------------------------------------------------
            | ENSURE DIRECTORY EXISTS
            |--------------------------------------------------------------
            */

            if (!file_exists(dirname($fullPath))) {

                mkdir(dirname($fullPath), 0777, true);
            }

            /*
            |--------------------------------------------------------------
            | OPENAI TTS
            |--------------------------------------------------------------
            */

            $response = OpenAI::audio()->speech([
                'model' => env(
                    'OPENAI_TTS_MODEL',
                    'gpt-4o-mini-tts'
                ),

                'voice' => env(
                    'OPENAI_TTS_VOICE',
                    'alloy'
                ),

                'input' => $text,
            ]);

            /*
            |--------------------------------------------------------------
            | SAVE MP3 LOCALLY
            |--------------------------------------------------------------
            */

            $response->save($fullPath);

            return $path;
        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'OpenAI TTS Generation Failed',
                [
                    'file_name' => $fileName,
                    'topic_id' => $topicId,
                    'message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
}
