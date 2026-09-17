<?php

namespace App\Console\Commands;

use App\Models\TopicContent;
use App\Services\AI\TextToSpeechService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMissingEnglishTopicAudio extends Command {
    protected $signature = 'audio:generate-missing-english
                            {--limit= : Number of contents to process}';

    protected $description = 'Generate missing English audio in actual curriculum hierarchy order';

    public function handle(
        TextToSpeechService $ttsService
    ): int {
        $this->info('==========================================');
        $this->info('Starting missing English audio generation...');
        $this->info('==========================================');

        $limitOption = $this->option('limit');

        $limit = null;

        if (
            $limitOption !== null &&
            is_numeric($limitOption) &&
            (int) $limitOption > 0
        ) {
            $limit = (int) $limitOption;
        }

        /*
        |--------------------------------------------------------------------------
        | Fetch ALL eligible contents first
        |--------------------------------------------------------------------------
        |
        | Do NOT apply SQL limit before hierarchy sorting.
        | Otherwise Content ID ordering can override curriculum ordering.
        |
        */

        $contents = TopicContent::query()
            ->with([
                'topic.chapter.module.level',
            ])
            ->where('type', 'text')
            ->where('publish_status', 'published')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('content')
            ->whereRaw("TRIM(content) != ''")
            ->where(function ($query) {
                $query
                    ->whereNull('audio_path')
                    ->orWhereRaw("TRIM(audio_path) = ''");
            })
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Sort by actual hierarchy
        |--------------------------------------------------------------------------
        |
        | Level → Module → Chapter → Topic → TopicContent
        |
        | Content ID is only the final fallback.
        |
        */

        $contents = $contents
            ->sort(function ($a, $b) {
                $aTopic = $a->topic;
                $bTopic = $b->topic;

                $aChapter = $aTopic?->chapter;
                $bChapter = $bTopic?->chapter;

                $aModule = $aChapter?->module;
                $bModule = $bChapter?->module;

                $aLevel = $aModule?->level;
                $bLevel = $bModule?->level;

                $aOrder = [
                    $aLevel?->order ?? $aLevel?->id ?? 0,
                    $aModule?->order ?? $aModule?->id ?? 0,
                    $aChapter?->order ?? $aChapter?->id ?? 0,
                    $aTopic?->order ?? $aTopic?->id ?? 0,
                    $a->order ?? $a->id ?? 0,
                    $a->id ?? 0,
                ];

                $bOrder = [
                    $bLevel?->order ?? $bLevel?->id ?? 0,
                    $bModule?->order ?? $bModule?->id ?? 0,
                    $bChapter?->order ?? $bChapter?->id ?? 0,
                    $bTopic?->order ?? $bTopic?->id ?? 0,
                    $b->order ?? $b->id ?? 0,
                    $b->id ?? 0,
                ];

                return $aOrder <=> $bOrder;
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Apply limit AFTER hierarchy sorting
        |--------------------------------------------------------------------------
        */

        if ($limit !== null) {
            $contents = $contents
                ->take($limit)
                ->values();
        }

        $this->info(
            'Missing English audio contents selected: ' .
                $contents->count()
        );

        if ($contents->isEmpty()) {
            $this->info('No missing English audio found.');

            return self::SUCCESS;
        }

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($contents as $content) {
            $contentId = (int) $content->id;

            $topic = $content->topic;
            $chapter = $topic?->chapter;
            $module = $chapter?->module;
            $level = $module?->level;

            $this->newLine();

            $this->info(
                "Processing Content ID: {$contentId}"
            );

            $this->line(
                'Hierarchy: ' .
                    'Level=' . ($level?->id ?? 'N/A') .
                    ' → Module=' . ($module?->id ?? 'N/A') .
                    ' → Chapter=' . ($chapter?->id ?? 'N/A') .
                    ' → Topic=' . ($topic?->id ?? 'N/A') .
                    ' → Content=' . $contentId
            );

            try {
                /*
                |--------------------------------------------------------------------------
                | Validate hierarchy
                |--------------------------------------------------------------------------
                */

                if (
                    !$topic ||
                    !$chapter ||
                    !$module ||
                    !$level
                ) {
                    throw new \RuntimeException(
                        "Hierarchy relation missing for Content ID {$contentId}"
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Re-check latest database record
                |--------------------------------------------------------------------------
                |
                | Do not refresh the original model because it can disturb
                | already-loaded hierarchy relations.
                |
                */

                $latestContent = TopicContent::query()
                    ->whereKey($contentId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$latestContent) {
                    $this->warn(
                        "Skipped Content ID {$contentId}: content no longer exists."
                    );

                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Never overwrite existing audio
                |--------------------------------------------------------------------------
                */

                if (
                    $latestContent->audio_path !== null &&
                    trim((string) $latestContent->audio_path) !== ''
                ) {
                    $this->warn(
                        "Skipped Content ID {$contentId}: audio already exists."
                    );

                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Validate latest English content
                |--------------------------------------------------------------------------
                */

                /*
|--------------------------------------------------------------------------
| Prepare clean English text for TTS
|--------------------------------------------------------------------------
| Remove embedded base64 images and HTML before sending content
| to OpenAI TTS. Base64 image data can be extremely large.
|--------------------------------------------------------------------------
*/

                $originalEnglishContent = (string) $latestContent->content;

                $rawEnglishContent = $originalEnglishContent;

                /*
|--------------------------------------------------------------------------
| Remove embedded base64 image tags
|--------------------------------------------------------------------------
*/

                $rawEnglishContent = preg_replace(
                    '/<img[^>]+src=["\']data:image\/[^"\']+["\'][^>]*>/i',
                    '',
                    $rawEnglishContent
                );

                /*
|--------------------------------------------------------------------------
| Convert HTML into readable plain text
|--------------------------------------------------------------------------
*/

                $englishText = strip_tags(
                    (string) $rawEnglishContent
                );

                /*
|--------------------------------------------------------------------------
| Decode HTML entities
|--------------------------------------------------------------------------
*/

                $englishText = html_entity_decode(
                    $englishText,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );

                /*
                |--------------------------------------------------------------------------
                | Normalize whitespace
                |--------------------------------------------------------------------------
                */

                $englishText = preg_replace(
                    '/[ \t]+/u',
                    ' ',
                    $englishText
                );

                $englishText = preg_replace(
                    "/\n{3,}/u",
                    "\n\n",
                    $englishText
                );

                $englishText = trim(
                    (string) $englishText
                );

                if ($englishText === '') {
                    $this->warn(
                        "Skipped Content ID {$contentId}: English content is empty after HTML cleanup."
                    );

                    $skipped++;

                    continue;
                }

                Log::channel('ai')->info(
                    'MISSING ENGLISH TEXT CLEANED FOR TTS',
                    [
                        'content_id' => $contentId,
                        'raw_length' => mb_strlen($originalEnglishContent),
                        'clean_text_length' => mb_strlen($englishText),
                    ]
                );
                /*
                |--------------------------------------------------------------------------
                | Generate dedicated English audio
                |--------------------------------------------------------------------------
                |
                | Existing generateEnglish() method will handle:
                |
                | - Long text chunking
                | - OpenAI TTS generation
                | - Temporary chunk files
                | - FFmpeg merging
                | - Final audio path
                |
                */

                $audioPath = $ttsService->generateEnglish(
                    text: $englishText,
                    levelId: (int) $level->id,
                    moduleId: (int) $module->id,
                    chapterId: (int) $chapter->id,
                    topicId: (int) $topic->id,
                    contentId: $contentId
                );

                if (
                    !is_string($audioPath) ||
                    trim($audioPath) === ''
                ) {
                    throw new \RuntimeException(
                        'TTS service returned an empty English audio path.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validate returned path belongs to this content
                |--------------------------------------------------------------------------
                */

                $expectedPathPart =
                    "uploads/content-management/audio/" .
                    $level->id . "/" .
                    $module->id . "/" .
                    $chapter->id . "/" .
                    $topic->id . "/" .
                    $contentId . "/en/audio.mp3";

                if (
                    trim($audioPath, '/') !== $expectedPathPart
                ) {
                    throw new \RuntimeException(
                        "Unexpected English audio path returned: {$audioPath}. " .
                            "Expected: {$expectedPathPart}"
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Final concurrency safety check
                |--------------------------------------------------------------------------
                |
                | Another process may have generated audio while TTS was running.
                | Never overwrite that audio.
                |
                */

                $latestContentAfterGeneration = TopicContent::query()
                    ->whereKey($contentId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$latestContentAfterGeneration) {
                    $this->warn(
                        "Skipped DB update for Content ID {$contentId}: content no longer exists."
                    );

                    $skipped++;

                    continue;
                }

                if (
                    $latestContentAfterGeneration->audio_path !== null &&
                    trim(
                        (string) $latestContentAfterGeneration->audio_path
                    ) !== ''
                ) {
                    $this->warn(
                        "Skipped DB update for Content ID {$contentId}: audio was generated by another process."
                    );

                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Save only final generated path
                |--------------------------------------------------------------------------
                */

                $latestContentAfterGeneration->audio_path = $audioPath;
                $latestContentAfterGeneration->audio_generated_at = now();
                $latestContentAfterGeneration->audio_provider = 'openai';

                $latestContentAfterGeneration->saveQuietly();

                $this->info(
                    "English audio generated successfully for Content ID {$contentId}"
                );

                $this->line(
                    "Audio Path: {$audioPath}"
                );

                Log::channel('ai')->info(
                    'MISSING ENGLISH TOPIC AUDIO GENERATED',
                    [
                        'level_id' => $level->id,
                        'module_id' => $module->id,
                        'chapter_id' => $chapter->id,
                        'topic_id' => $topic->id,
                        'content_id' => $contentId,
                        'audio_path' => $audioPath,
                        'text_length' => mb_strlen($englishText),
                    ]
                );

                $generated++;
            } catch (Throwable $e) {
                $failed++;

                $this->error(
                    "Failed Content ID {$contentId}: " .
                        $e->getMessage()
                );

                Log::channel('ai')->error(
                    'MISSING ENGLISH TOPIC AUDIO GENERATION FAILED',
                    [
                        'level_id' => $level?->id,
                        'module_id' => $module?->id,
                        'chapter_id' => $chapter?->id,
                        'topic_id' => $topic?->id,
                        'content_id' => $contentId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
            }
        }

        $this->newLine();

        $this->info('==========================================');
        $this->info('English Audio Generation Summary');
        $this->info('==========================================');

        $this->info("English audio generated: {$generated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Failed: {$failed}");

        $this->newLine();
        $this->info('Missing English audio generation completed.');

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
