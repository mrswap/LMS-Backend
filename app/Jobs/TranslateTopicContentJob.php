<?php

namespace App\Jobs;

use App\Models\TopicContent;
use App\Services\AI\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateTopicContentJob implements ShouldQueue
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

    public string $targetLanguage;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        int $contentId,
        string $targetLanguage
    ) {
        $this->contentId = $contentId;

        $this->targetLanguage = strtolower(
            trim($targetLanguage)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(
        OpenAIService $openAIService
    ): void {

        /*
        |--------------------------------------------------------------------------
        | TRANSLATION FEATURE CHECK
        |--------------------------------------------------------------------------
        */

        if (
            ! config(
                'ai.translation_enabled',
                env(
                    'OPENAI_TRANSLATION_ENABLED',
                    true
                )
            )
        ) {

            Log::channel('ai')->info(
                'TRANSLATION DISABLED - JOB SKIPPED',
                [
                    'content_id' =>
                        $this->contentId,

                    'target_language' =>
                        $this->targetLanguage,
                ]
            );

            return;
        }

        Log::channel('ai')->info(
            'TRANSLATION JOB STARTED',
            [
                'content_id' =>
                    $this->contentId,

                'target_language' =>
                    $this->targetLanguage,
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
                    'TRANSLATION CONTENT NOT FOUND',
                    [
                        'content_id' =>
                            $this->contentId,

                        'target_language' =>
                            $this->targetLanguage,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | ONLY TEXT CONTENT
            |--------------------------------------------------------------------------
            */

            if ($content->type !== 'text') {

                Log::channel('ai')->info(
                    'TRANSLATION SKIPPED - NON TEXT CONTENT',
                    [
                        'content_id' =>
                            $content->id,

                        'type' =>
                            $content->type,

                        'target_language' =>
                            $this->targetLanguage,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | ENGLISH SOURCE VALIDATION
            |--------------------------------------------------------------------------
            */

            $englishTitle =
                trim(
                    (string) $content->title
                );

            $englishContent =
                trim(
                    (string) $content->content
                );

            if (
                $englishTitle === ''
                && $englishContent === ''
            ) {

                Log::channel('ai')->warning(
                    'TRANSLATION SOURCE EMPTY',
                    [
                        'content_id' =>
                            $content->id,

                        'target_language' =>
                            $this->targetLanguage,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | DO NOT TRANSLATE ENGLISH
            |--------------------------------------------------------------------------
            */

            if ($this->targetLanguage === 'en') {

                Log::channel('ai')->info(
                    'TRANSLATION SKIPPED - TARGET IS ENGLISH',
                    [
                        'content_id' =>
                            $content->id,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | TARGET LANGUAGE NAME
            |--------------------------------------------------------------------------
            */

            $languageName = match (
                $this->targetLanguage
            ) {

                'hi' => 'Hindi',

                'pa' => 'Punjabi',

                default =>
                    $this->targetLanguage,
            };

            /*
            |--------------------------------------------------------------------------
            | TRANSLATE TITLE
            |--------------------------------------------------------------------------
            */

            $translatedTitle = null;

            if ($englishTitle !== '') {

                $translatedTitle =
                    $openAIService->translate(
                        $englishTitle,
                        $this->targetLanguage
                    );

                if (! $translatedTitle) {

                    throw new \RuntimeException(
                        "Failed to translate title into {$languageName}."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | TRANSLATE CONTENT
            |--------------------------------------------------------------------------
            */

            $translatedContent = null;

            if ($englishContent !== '') {

                $translatedContent =
                    $openAIService->translate(
                        $englishContent,
                        $this->targetLanguage
                    );

                if (! $translatedContent) {

                    throw new \RuntimeException(
                        "Failed to translate content into {$languageName}."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE TRANSLATION
            |--------------------------------------------------------------------------
            */

            $translation =
                $content->translations()
                    ->updateOrCreate(
                        [
                            'language_code' =>
                                $this->targetLanguage,
                        ],
                        [
                            'title' =>
                                $translatedTitle,

                            'content' =>
                                $translatedContent,
                        ]
                    );

            Log::channel('ai')->info(
                'TRANSLATION SAVED',
                [
                    'content_id' =>
                        $content->id,

                    'translation_id' =>
                        $translation->id,

                    'target_language' =>
                        $this->targetLanguage,

                    'title_length' =>
                        strlen(
                            $translatedTitle ?? ''
                        ),

                    'content_length' =>
                        strlen(
                            $translatedContent ?? ''
                        ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | COMPLETE
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'TRANSLATION JOB COMPLETED',
                [
                    'content_id' =>
                        $content->id,

                    'translation_id' =>
                        $translation->id,

                    'target_language' =>
                        $this->targetLanguage,
                ]
            );

        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'TRANSLATION JOB FAILED',
                [
                    'content_id' =>
                        $this->contentId,

                    'target_language' =>
                        $this->targetLanguage,

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