<?php

namespace App\Console\Commands;

use App\Models\TopicContent;
use App\Services\AI\TextToSpeechService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMissingEnglishTopicAudio extends Command
{
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
                | Refresh latest database record
                |--------------------------------------------------------------------------
                */

                $content->refresh();

                /*
                |--------------------------------------------------------------------------
                | Never overwrite existing audio
                |--------------------------------------------------------------------------
                */

                if (
                    $content->audio_path !== null &&
                    trim((string) $content->audio_path) !== ''
                ) {
                    $this->warn(
                        "Skipped Content ID {$contentId}: audio already exists."
                    );

                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Validate English content
                |--------------------------------------------------------------------------
                */

                $englishText = trim((string) $content->content);

                if ($englishText === '') {
                    $this->warn(
                        "Skipped Content ID {$contentId}: English content is empty."
                    );

                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Generate dedicated English audio
                |--------------------------------------------------------------------------
                |
                | The service must generate:
                |
                | public/uploads/content-management/audio/
                | {level_id}/{module_id}/{chapter_id}/{topic_id}/{content_id}/en/audio.mp3
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
                | Final safety refresh before DB update
                |--------------------------------------------------------------------------
                */

                $content->refresh();

                if (
                    $content->audio_path !== null &&
                    trim((string) $content->audio_path) !== ''
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

                $content->audio_path = $audioPath;
                $content->audio_generated_at = now();
                $content->audio_provider = 'openai';
                $content->saveQuietly();

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