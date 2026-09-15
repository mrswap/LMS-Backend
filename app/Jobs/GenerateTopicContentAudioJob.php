<?php

namespace App\Jobs;

use App\Models\TopicContent;
use App\Services\AI\TextToSpeechService;
use App\Jobs\TranslateTopicContentJob;
use App\Jobs\GenerateMultilanguageAudioJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTopicContentAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | JOB SETTINGS
    |--------------------------------------------------------------------------
    */

    public int $tries = 3;

    public int $timeout = 600;

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
        $this->contentId = (int) $contentId;
        $this->languageCode = strtolower(trim($languageCode));
        $this->translationId = $translationId !== null
            ? (int) $translationId
            : null;
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
        | Global TTS Check
        |--------------------------------------------------------------------------
        */

        if (!config('ai.tts_enabled', true)) {

            Log::channel('ai')->info(
                'TTS DISABLED - JOB SKIPPED',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Load Content
        |--------------------------------------------------------------------------
        */

        $content = TopicContent::with('translations')
            ->whereNull('deleted_at')
            ->find($this->contentId);

        if (!$content) {

            Log::channel('ai')->warning(
                'TTS CONTENT NOT FOUND',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Only Text Content
        |--------------------------------------------------------------------------
        */

        if ($content->type !== 'text') {

            Log::channel('ai')->info(
                'TTS SKIPPED - NON TEXT CONTENT',
                [
                    'content_id' => $content->id,
                    'type' => $content->type,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        $plainText = '';
        $translation = null;

        /*
        |--------------------------------------------------------------------------
        | Resolve Text
        |--------------------------------------------------------------------------
        */

        if ($this->languageCode === 'en') {

            $plainText = trim(
                strip_tags((string) $content->content)
            );

            if ($plainText === '') {

                Log::channel('ai')->info(
                    'TTS SKIPPED - EMPTY ENGLISH CONTENT',
                    [
                        'content_id' => $content->id,
                    ]
                );

                return;
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate Non-English Language
            |--------------------------------------------------------------------------
            */

            if (!in_array(
                $this->languageCode,
                ['hi', 'pa'],
                true
            )) {

                Log::channel('ai')->warning(
                    'TTS INVALID NON ENGLISH LANGUAGE',
                    [
                        'content_id' => $content->id,
                        'language' => $this->languageCode,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Find Translation
            |--------------------------------------------------------------------------
            */

            if ($this->translationId !== null) {

                $translation = $content->translations
                    ->firstWhere(
                        'id',
                        $this->translationId
                    );
            }

            if (!$translation) {

                $translation = $content->translations
                    ->firstWhere(
                        'language_code',
                        $this->languageCode
                    );
            }

            if (!$translation) {

                Log::channel('ai')->warning(
                    'TTS TRANSLATION NOT FOUND',
                    [
                        'content_id' => $content->id,
                        'language' => $this->languageCode,
                        'translation_id' => $this->translationId,
                    ]
                );

                return;
            }

            $plainText = trim(
                strip_tags((string) $translation->content)
            );

            if ($plainText === '') {

                Log::channel('ai')->info(
                    'TTS SKIPPED - EMPTY TRANSLATION CONTENT',
                    [
                        'content_id' => $content->id,
                        'translation_id' => $translation->id,
                        'language' => $this->languageCode,
                    ]
                );

                return;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TTS Job Started
        |--------------------------------------------------------------------------
        */

        Log::channel('ai')->info(
            'TTS JOB STARTED',
            [
                'content_id' => $content->id,
                'language' => $this->languageCode,
                'translation_id' => $translation?->id,
                'text_length' => mb_strlen($plainText),
                'topic_id' => $content->topic_id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Audio
        |--------------------------------------------------------------------------
        |
        | English:
        |   Uses generic generate() because this job currently loads only
        |   topic_id and does not load complete level/module/chapter hierarchy.
        |
        | Hindi/Punjabi:
        |   Uses generic multilingual generate().
        |
        */

        $audioPath = $ttsService->generate(
            text: (string) $plainText,
            language: (string) $this->languageCode,
            topicId: $content->topic_id !== null
                ? (int) $content->topic_id
                : null,
            contentId: $content->id !== null
                ? (int) $content->id
                : null
        );

        /*
        |--------------------------------------------------------------------------
        | Save English Audio
        |--------------------------------------------------------------------------
        */

        if ($this->languageCode === 'en') {

            $content->update([
                'audio_path' => $audioPath,
                'audio_generated_at' => now(),
                'audio_provider' => 'openai',
            ]);

            Log::channel('ai')->info(
                'ENGLISH TTS DATABASE UPDATED',
                [
                    'content_id' => $content->id,
                    'audio_path' => $audioPath,
                    'text_length' => mb_strlen($plainText),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Automatic Translation Enabled
            |--------------------------------------------------------------------------
            */

            if (
                config(
                    'ai.content_translation_enabled',
                    false
                )
            ) {

                TranslateTopicContentJob::dispatch(
                    $content->id,
                    'hi'
                )->afterCommit();

                Log::channel('ai')->info(
                    'HINDI TRANSLATION JOB DISPATCHED',
                    [
                        'content_id' => $content->id,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Translation Disabled:
            | Generate HI/PA Audio
            |--------------------------------------------------------------------------
            */

            elseif (
                config(
                    'ai.multilanguage_audio_enabled',
                    true
                )
            ) {

                foreach (['hi', 'pa'] as $languageCode) {

                    $translation = $content->translations()
                        ->firstOrCreate(
                            [
                                'language_code' => $languageCode,
                            ],
                            [
                                'title' => null,
                                'content' => null,
                            ]
                        );

                    GenerateMultilanguageAudioJob::dispatch(
                        $content->id,
                        $languageCode,
                        $translation->id,
                        true
                    )->afterCommit();

                    Log::channel('ai')->info(
                        'MANUAL MULTILANGUAGE AUDIO JOB DISPATCHED',
                        [
                            'content_id' => $content->id,
                            'translation_id' => $translation->id,
                            'language' => $languageCode,
                            'force' => true,
                        ]
                    );
                }
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Save Non-English Audio
        |--------------------------------------------------------------------------
        */

        if ($translation) {

            $translation->update([
                'audio_path' => $audioPath,
                'audio_generated_at' => now(),
                'audio_provider' => 'openai',
            ]);

            Log::channel('ai')->info(
                'TRANSLATION TTS DATABASE UPDATED',
                [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                    'audio_path' => $audioPath,
                    'text_length' => mb_strlen($plainText),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Punjabi Translation Chain
        |--------------------------------------------------------------------------
        */

        if (
            $this->languageCode === 'hi' &&
            config(
                'ai.content_translation_enabled',
                false
            )
        ) {

            TranslateTopicContentJob::dispatch(
                $content->id,
                'pa'
            )->afterCommit();

            Log::channel('ai')->info(
                'PUNJABI TRANSLATION JOB DISPATCHED',
                [
                    'content_id' => $content->id,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Completed
        |--------------------------------------------------------------------------
        */

        Log::channel('ai')->info(
            'TTS JOB COMPLETED',
            [
                'content_id' => $content->id,
                'language' => $this->languageCode,
                'audio_path' => $audioPath,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    */

    public function failed(Throwable $exception): void
    {
        Log::channel('ai')->error(
            'TTS JOB FAILED',
            [
                'content_id' => $this->contentId,
                'language' => $this->languageCode,
                'translation_id' => $this->translationId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}