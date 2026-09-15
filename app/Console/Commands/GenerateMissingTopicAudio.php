<?php

namespace App\Console\Commands;

use App\Models\TopicContent;
use App\Models\TopicContentTranslation;
use App\Jobs\GenerateMultilanguageAudioJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMissingTopicAudio extends Command
{
    protected $signature = 'audio:generate-missing
                            {--sync : Generate immediately instead of queue}
                            {--limit= : Limit number of topic contents}
                            {--retry=3 : Number of job retries}';

    protected $description =
        'Generate missing Hindi and Punjabi audio only where English audio exists';

    public function handle(): int
    {
        $this->info('==========================================');
        $this->info('Starting missing Hindi/Punjabi audio generation...');
        $this->info('==========================================');

        $sync = (bool) $this->option('sync');

        $limit = $this->option('limit') !== null
            ? (int) $this->option('limit')
            : null;

        $generatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $englishMissingCount = 0;

        /*
        |--------------------------------------------------------------------------
        | Fetch contents
        |--------------------------------------------------------------------------
        |
        | English audio must already exist.
        | Fetch all matching records first, then sort by hierarchy.
        | Limit is applied AFTER hierarchy sorting.
        |--------------------------------------------------------------------------
        */

        $contents = TopicContent::query()
            ->with([
                'topic.chapter.module.level',
            ])
            ->where('status', 1)
            ->where('publish_status', 'published')
            ->whereNull('deleted_at')
            ->where('type', 'text')
            ->whereNotNull('content')
            ->whereRaw("TRIM(content) != ''")
            ->whereNotNull('audio_path')
            ->whereRaw("TRIM(audio_path) != ''")
            ->get()
            ->sort(function ($a, $b) {
                /*
                |--------------------------------------------------------------------------
                | Level order
                |--------------------------------------------------------------------------
                */

                $aLevel = $a->topic?->chapter?->module?->level?->id
                    ?? PHP_INT_MAX;

                $bLevel = $b->topic?->chapter?->module?->level?->id
                    ?? PHP_INT_MAX;

                if ($aLevel !== $bLevel) {
                    return $aLevel <=> $bLevel;
                }

                /*
                |--------------------------------------------------------------------------
                | Module order
                |--------------------------------------------------------------------------
                */

                $aModule = $a->topic?->chapter?->module?->id
                    ?? PHP_INT_MAX;

                $bModule = $b->topic?->chapter?->module?->id
                    ?? PHP_INT_MAX;

                if ($aModule !== $bModule) {
                    return $aModule <=> $bModule;
                }

                /*
                |--------------------------------------------------------------------------
                | Chapter order
                |--------------------------------------------------------------------------
                */

                $aChapter = $a->topic?->chapter?->id
                    ?? PHP_INT_MAX;

                $bChapter = $b->topic?->chapter?->id
                    ?? PHP_INT_MAX;

                if ($aChapter !== $bChapter) {
                    return $aChapter <=> $bChapter;
                }

                /*
                |--------------------------------------------------------------------------
                | Topic order
                |--------------------------------------------------------------------------
                |
                | If topic has an "order" column, use it.
                | Otherwise fallback to topic ID.
                |--------------------------------------------------------------------------
                */

                $aTopicOrder = $a->topic?->order
                    ?? PHP_INT_MAX;

                $bTopicOrder = $b->topic?->order
                    ?? PHP_INT_MAX;

                if ($aTopicOrder !== $bTopicOrder) {
                    return $aTopicOrder <=> $bTopicOrder;
                }

                $aTopic = $a->topic?->id
                    ?? PHP_INT_MAX;

                $bTopic = $b->topic?->id
                    ?? PHP_INT_MAX;

                if ($aTopic !== $bTopic) {
                    return $aTopic <=> $bTopic;
                }

                /*
                |--------------------------------------------------------------------------
                | Content order
                |--------------------------------------------------------------------------
                */

                $aContentOrder = $a->order
                    ?? PHP_INT_MAX;

                $bContentOrder = $b->order
                    ?? PHP_INT_MAX;

                if ($aContentOrder !== $bContentOrder) {
                    return $aContentOrder <=> $bContentOrder;
                }

                /*
                |--------------------------------------------------------------------------
                | Final fallback
                |--------------------------------------------------------------------------
                */

                return $a->id <=> $b->id;
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Apply limit AFTER sorting
        |--------------------------------------------------------------------------
        */

        if ($limit !== null && $limit > 0) {
            $contents = $contents
                ->take($limit)
                ->values();
        }

        $this->info(
            "English-audio-available topic contents found: {$contents->count()}"
        );

        if ($contents->isEmpty()) {
            $this->warn(
                'No topic content found where English audio is available.'
            );

            return self::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | Process contents
        |--------------------------------------------------------------------------
        */

        foreach ($contents as $content) {
            $contentId = $content->id;

            $this->line(
                "Processing Content ID {$contentId}"
            );

            foreach (['hi', 'pa'] as $languageCode) {
                try {
                    /*
                    |--------------------------------------------------------------------------
                    | Refresh English audio safety check
                    |--------------------------------------------------------------------------
                    */

                    $content->refresh();

                    if (
                        empty($content->audio_path) ||
                        trim((string) $content->audio_path) === ''
                    ) {
                        $englishMissingCount++;

                        $this->warn(
                            "Skipped {$languageCode} Content ID {$contentId}: English audio missing."
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Find translation
                    |--------------------------------------------------------------------------
                    */

                    $translation = TopicContentTranslation::query()
                        ->where('topic_content_id', $contentId)
                        ->where('language_code', $languageCode)
                        ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | Create translation if missing
                    |--------------------------------------------------------------------------
                    */

                    if (!$translation) {
                        $translation = TopicContentTranslation::create([
                            'topic_content_id' => $contentId,
                            'language_code' => $languageCode,
                            'title' => null,
                            'content' => null,
                        ]);

                        $this->line(
                            "Created {$languageCode} translation row for Content ID {$contentId}"
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Skip existing language audio
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($translation->audio_path) &&
                        trim((string) $translation->audio_path) !== ''
                    ) {
                        $skippedCount++;

                        $this->line(
                            "Skipped {$languageCode} Content ID {$contentId}: audio already exists."
                        );

                        Log::channel('ai')->info(
                            'COMMAND MULTILANGUAGE AUDIO SKIPPED',
                            [
                                'content_id' => $contentId,
                                'translation_id' => $translation->id,
                                'language' => $languageCode,
                                'english_audio_path' => $content->audio_path,
                                'audio_path' => $translation->audio_path,
                            ]
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Generate or queue missing audio
                    |--------------------------------------------------------------------------
                    */

                    $this->line(
                        "Generating missing {$languageCode} audio for Content ID {$contentId}"
                    );

                    if ($sync) {
                        GenerateMultilanguageAudioJob::dispatchSync(
                            $contentId,
                            $languageCode,
                            $translation->id,
                            false
                        );

                        $this->info(
                            "Generated {$languageCode} audio for Content ID {$contentId}"
                        );
                    } else {
                        GenerateMultilanguageAudioJob::dispatch(
                            $contentId,
                            $languageCode,
                            $translation->id,
                            false
                        );

                        $this->info(
                            "Queued {$languageCode} audio for Content ID {$contentId}"
                        );
                    }

                    $generatedCount++;

                    Log::channel('ai')->info(
                        'COMMAND MULTILANGUAGE AUDIO DISPATCHED',
                        [
                            'content_id' => $contentId,
                            'translation_id' => $translation->id,
                            'language' => $languageCode,
                            'english_audio_path' => $content->audio_path,
                            'sync' => $sync,
                        ]
                    );
                } catch (Throwable $e) {
                    $failedCount++;

                    Log::channel('ai')->error(
                        'COMMAND MULTILANGUAGE AUDIO FAILED',
                        [
                            'content_id' => $contentId,
                            'language' => $languageCode,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]
                    );

                    $this->error(
                        "Failed {$languageCode} Content ID {$contentId}: " .
                        $e->getMessage()
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info('==========================================');
        $this->info('Missing Audio Generation Summary');
        $this->info('==========================================');

        $this->info(
            "Hindi/Punjabi jobs dispatched: {$generatedCount}"
        );

        $this->warn(
            "Already existing language audio skipped: {$skippedCount}"
        );

        $this->warn(
            "English audio missing during re-check: {$englishMissingCount}"
        );

        $this->error(
            "Failed jobs: {$failedCount}"
        );

        if (!$sync && $generatedCount > 0) {
            $this->newLine();

            $this->warn('Jobs have been sent to the queue.');

            $this->line(
                'php artisan queue:work --tries=3 --timeout=600'
            );
        }

        $this->newLine();

        $this->info(
            'Missing Hindi/Punjabi audio generation completed.'
        );

        return $failedCount > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}