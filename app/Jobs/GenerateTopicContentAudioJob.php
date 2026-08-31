<?php
namespace App\Jobs;

use App\Models\TopicContent;
use App\Services\AI\TextToSpeechService;
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

    public string $languageCode;

    public ?int $translationId;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        int $contentId,
        string $languageCode = 'en',
        ?int $translationId = null
    ) {
        $this->contentId = $contentId;

        $this->languageCode = strtolower(
            trim($languageCode)
        );

        $this->translationId = $translationId;
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(
        TextToSpeechService $ttsService
    ): void {

        Log::channel('ai')->info(
            'TTS JOB STARTED',
            [
                'content_id' =>
                    $this->contentId,

                'language' =>
                    $this->languageCode,

                'translation_id' =>
                    $this->translationId,
            ]
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | LOAD CONTENT
            |--------------------------------------------------------------------------
            */

            $content = TopicContent::find(
                $this->contentId
            );

            if (! $content) {

                Log::channel('ai')->error(
                    'TTS CONTENT NOT FOUND',
                    [
                        'content_id' =>
                            $this->contentId,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | RESOLVE TEXT
            |--------------------------------------------------------------------------
            */

            $plainText = null;

            /*
            |--------------------------------------------------------------------------
            | ENGLISH
            |--------------------------------------------------------------------------
            |
            | English content lives directly on TopicContent.
            |
            */

            if ($this->languageCode === 'en') {

                if (
                    $content->type !== 'text'
                    || empty($content->content)
                ) {

                    Log::channel('ai')->warning(
                        'TTS ENGLISH CONTENT EMPTY OR INVALID',
                        [
                            'content_id' =>
                                $content->id,

                            'type' =>
                                $content->type,
                        ]
                    );

                    return;
                }

                $plainText = trim(
                    strip_tags(
                        $content->content
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | TRANSLATED LANGUAGE
            |--------------------------------------------------------------------------
            |
            | Hindi / Punjabi etc. content lives in
            | TopicContentTranslation.
            |
            */

            else {

                $translation = null;

                if ($this->translationId) {

                    $translation =
                        $content->translations()
                            ->where(
                                'id',
                                $this->translationId
                            )
                            ->where(
                                'language_code',
                                $this->languageCode
                            )
                            ->first();

                }

                /*
                |------------------------------------------------------------------
                | FALLBACK: FIND BY LANGUAGE
                |------------------------------------------------------------------
                */

                if (! $translation) {

                    $translation =
                        $content->translations()
                            ->where(
                                'language_code',
                                $this->languageCode
                            )
                            ->first();
                }

                if (! $translation) {

                    Log::channel('ai')->warning(
                        'TTS TRANSLATION NOT FOUND',
                        [
                            'content_id' =>
                                $content->id,

                            'language' =>
                                $this->languageCode,

                            'translation_id' =>
                                $this->translationId,
                        ]
                    );

                    return;
                }

                if (
                    $content->type !== 'text'
                    || empty($translation->content)
                ) {

                    Log::channel('ai')->warning(
                        'TTS TRANSLATION CONTENT EMPTY OR INVALID',
                        [
                            'content_id' =>
                                $content->id,

                            'language' =>
                                $this->languageCode,

                            'translation_id' =>
                                $translation->id,

                            'type' =>
                                $content->type,
                        ]
                    );

                    return;
                }

                $plainText = trim(
                    strip_tags(
                        $translation->content
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | EMPTY TEXT CHECK
            |--------------------------------------------------------------------------
            */

            if (! $plainText) {

                Log::channel('ai')->warning(
                    'TTS TEXT EMPTY',
                    [
                        'content_id' =>
                            $content->id,

                        'language' =>
                            $this->languageCode,

                        'translation_id' =>
                            $this->translationId,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | TEXT READY
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'TTS TEXT READY',
                [
                    'content_id' =>
                        $content->id,

                    'language' =>
                        $this->languageCode,

                    'translation_id' =>
                        $this->translationId,

                    'length' =>
                        strlen($plainText),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | FILE NAME
            |--------------------------------------------------------------------------
            */

            $fileName =
                'content_' .
                $content->id .
                '_' .
                $this->languageCode .
                '_' .
                uniqid();

            /*
            |--------------------------------------------------------------------------
            | GENERATE AUDIO
            |--------------------------------------------------------------------------
            */

            $audioPath =
                $ttsService->generate(
                    $plainText,
                    $fileName,
                    $content->topic_id,
                    $this->languageCode
                );

            /*
            |--------------------------------------------------------------------------
            | SAVE AUDIO
            |--------------------------------------------------------------------------
            */

            /*
            |----------------------------------------------------------------------
            | ENGLISH
            |----------------------------------------------------------------------
            */

            if ($this->languageCode === 'en') {

                $content->update([
                    'audio_path' =>
                        $audioPath,

                    'audio_generated_at' =>
                        now(),

                    'audio_provider' =>
                        'openai',
                ]);

                Log::channel('ai')->info(
                    'ENGLISH TTS DATABASE UPDATED',
                    [
                        'content_id' =>
                            $content->id,

                        'audio_path' =>
                            $audioPath,
                    ]
                );
            }

            /*
            |----------------------------------------------------------------------
            | TRANSLATED LANGUAGE
            |----------------------------------------------------------------------
            */

            else {

                $translation = null;

                if ($this->translationId) {

                    $translation =
                        $content->translations()
                            ->where(
                                'id',
                                $this->translationId
                            )
                            ->where(
                                'language_code',
                                $this->languageCode
                            )
                            ->first();
                }

                if (! $translation) {

                    $translation =
                        $content->translations()
                            ->where(
                                'language_code',
                                $this->languageCode
                            )
                            ->first();
                }

                if (! $translation) {

                    Log::channel('ai')->error(
                        'TTS TRANSLATION DISAPPEARED BEFORE SAVE',
                        [
                            'content_id' =>
                                $content->id,

                            'language' =>
                                $this->languageCode,

                            'translation_id' =>
                                $this->translationId,
                        ]
                    );

                    return;
                }

                $translation->update([
                    'audio_path' =>
                        $audioPath,

                    'audio_generated_at' =>
                        now(),

                    'audio_provider' =>
                        'openai',
                ]);

                Log::channel('ai')->info(
                    'TRANSLATION TTS DATABASE UPDATED',
                    [
                        'content_id' =>
                            $content->id,

                        'translation_id' =>
                            $translation->id,

                        'language' =>
                            $this->languageCode,

                        'audio_path' =>
                            $audioPath,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | COMPLETE
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'TTS JOB COMPLETED',
                [
                    'content_id' =>
                        $content->id,

                    'language' =>
                        $this->languageCode,

                    'translation_id' =>
                        $this->translationId,

                    'audio_path' =>
                        $audioPath,
                ]
            );

        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'TTS JOB FAILED',
                [
                    'content_id' =>
                        $this->contentId,

                    'language' =>
                        $this->languageCode,

                    'translation_id' =>
                        $this->translationId,

                    'message' =>
                        $e->getMessage(),

                    'line' =>
                        $e->getLine(),

                    'file' =>
                        $e->getFile(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }
}

