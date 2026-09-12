<?php

namespace App\Jobs;

use App\Models\TopicContent;
use App\Services\AI\OpenAIService;
use App\Services\AI\TextToSpeechService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMultilanguageAudioJob implements ShouldQueue {
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

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
        string $languageCode,
        ?int $translationId = null
    ) {
        $this->contentId = $contentId;
        $this->languageCode = strtolower(trim($languageCode));
        $this->translationId = $translationId;
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(
        TextToSpeechService $ttsService,
        OpenAIService $openAIService
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Global TTS Check
        |--------------------------------------------------------------------------
        */

        if (!config('ai.tts_enabled', true)) {

            Log::channel('ai')->info(
                'MULTILANGUAGE TTS DISABLED - JOB SKIPPED',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Multilanguage Audio Check
        |--------------------------------------------------------------------------
        */

        if (!config('ai.multilanguage_audio_enabled', true)) {

            Log::channel('ai')->info(
                'MULTILANGUAGE AUDIO DISABLED - JOB SKIPPED',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Language
        |--------------------------------------------------------------------------
        */

        if (!in_array($this->languageCode, ['hi', 'pa'], true)) {

            Log::channel('ai')->warning(
                'MULTILANGUAGE TTS INVALID LANGUAGE',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Load English Content
        |--------------------------------------------------------------------------
        */

        $content = TopicContent::find($this->contentId);

        if (!$content) {

            Log::channel('ai')->warning(
                'MULTILANGUAGE TTS CONTENT NOT FOUND',
                [
                    'content_id' => $this->contentId,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        if ($content->type !== 'text') {

            Log::channel('ai')->info(
                'MULTILANGUAGE TTS SKIPPED - NON TEXT CONTENT',
                [
                    'content_id' => $content->id,
                    'type' => $content->type,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        $englishText = trim(
            strip_tags((string) $content->content)
        );

        if ($englishText === '') {

            Log::channel('ai')->warning(
                'MULTILANGUAGE TTS SKIPPED - EMPTY ENGLISH CONTENT',
                [
                    'content_id' => $content->id,
                    'language' => $this->languageCode,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Find Or Create Translation Row
        |--------------------------------------------------------------------------
        */

        $translation = null;

        if ($this->translationId) {

            $translation = $content->translations()
                ->where('id', $this->translationId)
                ->first();
        }

        if (!$translation) {

            $translation = $content->translations()
                ->where('language_code', $this->languageCode)
                ->first();
        }

        if (!$translation) {

            $translation = $content->translations()
                ->create([
                    'language_code' => $this->languageCode,
                    'title' => null,
                    'content' => null,
                ]);

            Log::channel('ai')->info(
                'MULTILANGUAGE TRANSLATION ROW CREATED',
                [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Text For Audio
        |--------------------------------------------------------------------------
        |
        | Priority:
        | 1. Existing Google/manual translation
        | 2. Temporary OpenAI translation
        |
        */

        $translatedText = trim(
            strip_tags((string) $translation->content)
        );

        if ($translatedText !== '') {

            Log::channel('ai')->info(
                'MULTILANGUAGE AUDIO USING EXISTING TRANSLATION',
                [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                    'source' => 'existing_translation',
                    'text_length' => strlen($translatedText),
                ]
            );
        } else {

            /*
            |--------------------------------------------------------------------------
            | Temporary Translation For Audio Only
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'MULTILANGUAGE AUDIO TRANSLATION CONTENT EMPTY',
                [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                    'action' => 'temporary_openai_translation',
                ]
            );

            $translatedText = $openAIService->translate(
                $englishText,
                $this->languageCode
            );

            $translatedText = trim(
                strip_tags((string) $translatedText)
            );

            if ($translatedText === '') {

                Log::channel('ai')->error(
                    'MULTILANGUAGE AUDIO TRANSLATION FAILED',
                    [
                        'content_id' => $content->id,
                        'translation_id' => $translation->id,
                        'language' => $this->languageCode,
                    ]
                );

                throw new \RuntimeException(
                    "Unable to generate temporary {$this->languageCode} translation."
                );
            }

            Log::channel('ai')->info(
                'MULTILANGUAGE AUDIO TEMPORARY TRANSLATION GENERATED',
                [
                    'content_id' => $content->id,
                    'translation_id' => $translation->id,
                    'language' => $this->languageCode,
                    'text_length' => strlen($translatedText),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Audio
        |--------------------------------------------------------------------------
        */

        Log::channel('ai')->info(
            'MULTILANGUAGE AUDIO TTS STARTED',
            [
                'content_id' => $content->id,
                'translation_id' => $translation->id,
                'language' => $this->languageCode,
                'text_length' => strlen($translatedText),
            ]
        );

        $fileName = "content_{$content->id}_{$this->languageCode}";

        $audioPath = $ttsService->generate(
            $translatedText,
            $fileName,
            $content->topic_id,
            $this->languageCode
        );

        /*
        |--------------------------------------------------------------------------
        | Save Only Audio Fields
        |--------------------------------------------------------------------------
        |
        | Existing Google/manual translation text remains untouched.
        |
        */

        $translation->update([
            'audio_path' => $audioPath,
            'audio_generated_at' => now(),
            'audio_provider' => 'openai',
        ]);

        Log::channel('ai')->info(
            'MULTILANGUAGE AUDIO DATABASE UPDATED',
            [
                'content_id' => $content->id,
                'translation_id' => $translation->id,
                'language' => $this->languageCode,
                'audio_path' => $audioPath,
                'translation_content_saved' => false,
            ]
        );

        Log::channel('ai')->info(
            'MULTILANGUAGE AUDIO JOB COMPLETED',
            [
                'content_id' => $content->id,
                'translation_id' => $translation->id,
                'language' => $this->languageCode,
            ]
        );
    }
}
