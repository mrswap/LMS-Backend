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

class GenerateTopicContentAudioJob implements ShouldQueue {
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
        $this->languageCode = strtolower($languageCode);
        $this->translationId = $translationId;
    }

    public function handle(TextToSpeechService $ttsService): void {
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
            ->find($this->contentId);

        if (!$content) {
            Log::channel('ai')->warning('TTS CONTENT NOT FOUND', [
                'content_id' => $this->contentId,
                'language' => $this->languageCode,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Text
        |--------------------------------------------------------------------------
        */

        $plainText = null;
        $translation = null;

        /*
        |--------------------------------------------------------------------------
        | English Content
        |--------------------------------------------------------------------------
        */

        if ($this->languageCode === 'en') {

            if ($content->type !== 'text') {
                Log::channel('ai')->info('TTS SKIPPED - NON TEXT CONTENT', [
                    'content_id' => $content->id,
                    'type' => $content->type,
                ]);

                return;
            }

            if (empty(trim(strip_tags((string) $content->content)))) {
                Log::channel('ai')->info('TTS SKIPPED - EMPTY ENGLISH CONTENT', [
                    'content_id' => $content->id,
                ]);

                return;
            }

            $plainText = trim(strip_tags((string) $content->content));
        }

        /*
        |--------------------------------------------------------------------------
        | Non-English Translation Content
        |--------------------------------------------------------------------------
        */ else {

            if ($this->translationId) {
                $translation = $content->translations
                    ->firstWhere('id', $this->translationId);
            }

            if (!$translation) {
                $translation = $content->translations
                    ->firstWhere('language_code', $this->languageCode);
            }

            if (!$translation) {
                Log::channel('ai')->warning('TTS TRANSLATION NOT FOUND', [
                    'content_id' => $content->id,
                    'language' => $this->languageCode,
                    'translation_id' => $this->translationId,
                ]);

                return;
            }

            if (empty(trim(strip_tags((string) $translation->content)))) {
                Log::channel('ai')->info('TTS SKIPPED - EMPTY TRANSLATION CONTENT', [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                ]);

                return;
            }

            $plainText = trim(strip_tags((string) $translation->content));
        }

        if (empty($plainText)) {
            Log::channel('ai')->info('TTS SKIPPED - EMPTY TEXT', [
                'content_id' => $content->id,
                'language' => $this->languageCode,
            ]);

            return;
        }

        Log::channel('ai')->info('TTS JOB STARTED', [
            'content_id' => $content->id,
            'language' => $this->languageCode,
            'translation_id' => $this->translationId,
            'text_length' => strlen($plainText),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Audio
        |--------------------------------------------------------------------------
        */

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

            Log::channel('ai')->info('ENGLISH TTS DATABASE UPDATED', [
                'content_id' => $content->id,
                'audio_path' => $audioPath,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Content Translation Enabled
            |--------------------------------------------------------------------------
            |
            | English -> Hindi translation job
            |
            */

            if (config('ai.content_translation_enabled', false)) {

                TranslateTopicContentJob::dispatch(
                    $content->id,
                    'hi'
                )->afterCommit();

                Log::channel('ai')->info('HINDI TRANSLATION JOB DISPATCHED', [
                    'content_id' => $content->id,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Translation Disabled But Multilanguage Audio Enabled
            |--------------------------------------------------------------------------
            |
            | Process already-existing Hindi/Punjabi translations.
            |
            */ elseif (config('ai.multilanguage_audio_enabled', true)) {

                foreach (['hi', 'pa'] as $languageCode) {

                    /*
                    |--------------------------------------------------------------------------
                    | Create Translation Row If Missing
                    |--------------------------------------------------------------------------
                    */

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
                    | Dispatch Multilanguage Audio Job
                    |--------------------------------------------------------------------------
                    */

                    GenerateMultilanguageAudioJob::dispatch(
                        $content->id,
                        $languageCode,
                        $translation->id
                    )->afterCommit();

                    Log::channel('ai')->info(
                        'MULTILANGUAGE AUDIO JOB DISPATCHED',
                        [
                            'content_id' => $content->id,
                            'translation_id' => $translation->id,
                            'language' => $languageCode,
                            'content_available' => !empty(trim((string) $translation->content)),
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

            Log::channel('ai')->info('TRANSLATION TTS DATABASE UPDATED', [
                'content_id' => $content->id,
                'translation_id' => $translation->id,
                'language' => $this->languageCode,
                'audio_path' => $audioPath,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Punjabi Translation Chain
        |--------------------------------------------------------------------------
        |
        | Only run when automatic content translation is enabled.
        |
        */

        if (
            $this->languageCode === 'hi'
            && config('ai.content_translation_enabled', false)
        ) {

            TranslateTopicContentJob::dispatch(
                $content->id,
                'pa'
            )->afterCommit();

            Log::channel('ai')->info('PUNJABI TRANSLATION JOB DISPATCHED', [
                'content_id' => $content->id,
            ]);
        }

        Log::channel('ai')->info('TTS JOB COMPLETED', [
            'content_id' => $content->id,
            'language' => $this->languageCode,
        ]);
    }
}
