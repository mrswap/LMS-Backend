<?php

namespace App\Jobs;

use App\Models\TopicContent;
use App\Services\AI\TextToSpeechService;
use App\Jobs\TranslateTopicContentJob;
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

        /*
        |--------------------------------------------------------------------------
        | TTS FEATURE CHECK
        |--------------------------------------------------------------------------
        */

        if (! env('OPENAI_TTS_ENABLED', true)) {

            Log::channel('ai')->info(
                'TTS DISABLED - JOB SKIPPED',
                [
                    'content_id' =>
                        $this->contentId,

                    'language' =>
                        $this->languageCode,

                    'translation_id' =>
                        $this->translationId,
                ]
            );

            return;
        }

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

            $translation = null;

            /*
            |--------------------------------------------------------------------------
            | ENGLISH
            |--------------------------------------------------------------------------
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
            */

            else {

                /*
                |--------------------------------------------------------------------------
                | FIND TRANSLATION
                |--------------------------------------------------------------------------
                */

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
                |--------------------------------------------------------------------------
                | FALLBACK
                |--------------------------------------------------------------------------
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

                /*
                |--------------------------------------------------------------------------
                | VALIDATE
                |--------------------------------------------------------------------------
                */

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
            | EMPTY TEXT
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
                $this->languageCode;

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

                /*
                |--------------------------------------------------------------------------
                | AFTER ENGLISH TTS
                |--------------------------------------------------------------------------
                |
                | English complete.
                |
                | Now automatically start Hindi translation.
                |
                */

                if (
                    env(
                        'OPENAI_TRANSLATION_ENABLED',
                        true
                    )
                ) {

                    TranslateTopicContentJob::dispatch(
                        $content->id,
                        'hi'
                    )->afterCommit();

                    Log::channel('ai')->info(
                        'HINDI TRANSLATION JOB DISPATCHED',
                        [
                            'content_id' =>
                                $content->id,

                            'language' =>
                                'hi',
                        ]
                    );
                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | TRANSLATED AUDIO
                |--------------------------------------------------------------------------
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

                /*
                |--------------------------------------------------------------------------
                | AFTER HINDI TTS
                |--------------------------------------------------------------------------
                */

                if (
                    $this->languageCode === 'hi'
                    && env(
                        'OPENAI_TRANSLATION_ENABLED',
                        true
                    )
                ) {

                    TranslateTopicContentJob::dispatch(
                        $content->id,
                        'pa'
                    )->afterCommit();

                    Log::channel('ai')->info(
                        'PUNJABI TRANSLATION JOB DISPATCHED',
                        [
                            'content_id' =>
                                $content->id,

                            'language' =>
                                'pa',
                        ]
                    );
                }
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