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

class GenerateTopicContentAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $contentId;
    public string $languageCode;
    public ?int $translationId;

    public function __construct(
        int $contentId,
        string $languageCode = 'en',
        ?int $translationId = null
    ) {
        $this->contentId = $contentId;
        $this->languageCode = strtolower(trim($languageCode));
        $this->translationId = $translationId;
    }

    public function handle(TextToSpeechService $ttsService): void
    {
        /*
        |--------------------------------------------------------------------------
        | Global TTS Check
        |--------------------------------------------------------------------------
        */

        if (!config('ai.tts_enabled', true)) {

            Log::channel('ai')->info('TTS DISABLED - JOB SKIPPED', [
                'content_id' => $this->contentId,
                'language' => $this->languageCode,
            ]);

            return;
        }

        $content = TopicContent::with('translations')
            ->whereNull('deleted_at')
            ->find($this->contentId);

        if (!$content) {

            Log::channel('ai')->warning('TTS CONTENT NOT FOUND', [
                'content_id' => $this->contentId,
                'language' => $this->languageCode,
            ]);

            return;
        }

        $plainText = null;
        $translation = null;

        /*
        |--------------------------------------------------------------------------
        | English Content
        |--------------------------------------------------------------------------
        */

        if ($this->languageCode === 'en') {

            if ($content->type !== 'text') {

                Log::channel('ai')->info(
                    'TTS SKIPPED - NON TEXT CONTENT',
                    [
                        'content_id' => $content->id,
                        'type' => $content->type,
                    ]
                );

                return;
            }

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
        }

        /*
        |--------------------------------------------------------------------------
        | Non-English Translation Content
        |--------------------------------------------------------------------------
        */

        else {

            if (!in_array($this->languageCode, ['hi', 'pa'], true)) {

                Log::channel('ai')->warning(
                    'TTS INVALID NON ENGLISH LANGUAGE',
                    [
                        'content_id' => $content->id,
                        'language' => $this->languageCode,
                    ]
                );

                return;
            }

            if ($this->translationId) {

                $translation = $content->translations
                    ->firstWhere('id', $this->translationId);
            }

            if (!$translation) {

                $translation = $content->translations
                    ->firstWhere('language_code', $this->languageCode);
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
        | Generate Audio
        |--------------------------------------------------------------------------
        */

        Log::channel('ai')->info('TTS JOB STARTED', [
            'content_id' => $content->id,
            'language' => $this->languageCode,
            'translation_id' => $this->translationId,
            'text_length' => strlen($plainText),
        ]);

        $fileName = "content_{$content->id}_{$this->languageCode}";

        $audioPath = $ttsService->generate(
            $plainText,
            $fileName,
            $content->topic_id,
            $this->languageCode
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
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Automatic Translation Enabled
            |--------------------------------------------------------------------------
            */

            if (config('ai.content_translation_enabled', false)) {

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
            | Generate Audio For Existing/Created HI and PA Rows
            |--------------------------------------------------------------------------
            */

            elseif (config('ai.multilanguage_audio_enabled', true)) {

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

                    /*
                    |--------------------------------------------------------------------------
                    | force=true
                    |--------------------------------------------------------------------------
                    |
                    | Manual admin update should regenerate HI/PA audio.
                    |
                    */

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
            config('ai.content_translation_enabled', false)
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

        Log::channel('ai')->info(
            'TTS JOB COMPLETED',
            [
                'content_id' => $content->id,
                'language' => $this->languageCode,
            ]
        );
    }
}
