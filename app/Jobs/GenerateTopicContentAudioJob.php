<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTopicContentAudioJob implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | JOB SETTINGS
    |--------------------------------------------------------------------------
    */

    public $tries = 3;

    public $timeout = 300;

    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */

    public int $contentId;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(int $contentId)
    {
        $this->contentId = $contentId;

        /*
        |--------------------------------------------------------------------------
        | OPTIONAL QUEUE NAME
        |--------------------------------------------------------------------------
        */

        //$this->onQueue('audio');
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        Log::info('AUDIO JOB STARTED', [
            'content_id' => $this->contentId
        ]);

        try {

            $content = \App\Models\TopicContent::find($this->contentId);

            if (!$content) {

                Log::error('CONTENT NOT FOUND');

                return;
            }

            Log::info('CONTENT FOUND', [
                'id' => $content->id,
                'title' => $content->title,
            ]);

            $plainText = trim(strip_tags($content->content));

            if (empty($plainText)) {

                Log::error('EMPTY CONTENT');

                return;
            }

            Log::info('TEXT EXTRACTED', [
                'length' => strlen($plainText)
            ]);

            $client = \OpenAI::client(
                env('OPENAI_API_KEY')
            );

            Log::info('OPENAI CLIENT CREATED');

            $response = $client->audio()->speech([
                'model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
                'voice' => env('OPENAI_TTS_VOICE', 'alloy'),
                'input' => substr($plainText, 0, 4000),
            ]);

            Log::info('OPENAI RESPONSE RECEIVED');

            /*
        |--------------------------------------------------------------------------
        | RESPONSE PARSE
        |--------------------------------------------------------------------------
        */

            $audioBinary = null;

            if (is_string($response)) {

                $audioBinary = $response;

                Log::info('RESPONSE TYPE STRING');
            } elseif (method_exists($response, 'getBody')) {

                $audioBinary = $response
                    ->getBody()
                    ->getContents();

                Log::info('RESPONSE TYPE STREAM');
            } elseif (method_exists($response, 'toString')) {

                $audioBinary = $response->toString();

                Log::info('RESPONSE TYPE TO STRING');
            }

            if (!$audioBinary) {

                Log::error('AUDIO BINARY EMPTY');

                return;
            }

            $directory = public_path(
                'uploads/content-management/media'
            );

            if (!file_exists($directory)) {

                mkdir($directory, 0777, true);
            }

            $filename = uniqid() . '.mp3';

            $relativePath =
                'uploads/content-management/media/' . $filename;

            $fullPath = public_path($relativePath);

            file_put_contents($fullPath, $audioBinary);

            Log::info('AUDIO FILE SAVED', [
                'path' => $fullPath
            ]);

            $content->update([
                'audio_path' => $relativePath,
                'audio_generated_at' => now(),
                'audio_provider' => 'openai',
            ]);

            Log::info('DATABASE UPDATED');
        } catch (\Throwable $e) {

            Log::error('TTS GENERATION FAILED', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
