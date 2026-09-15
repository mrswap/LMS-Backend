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

    protected $description = 'Generate missing English audio for published active topic contents';

    public function handle(
        TextToSpeechService $ttsService
    ): int {
        $this->info('==========================================');
        $this->info('Starting missing English audio generation...');
        $this->info('==========================================');

        $limit = $this->option('limit');

        $query = TopicContent::query()
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
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $contents = $query->get();

        $this->info(
            'Missing English audio contents found: ' .
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
            $contentId = $content->id;

            $this->info(
                "Generating English audio for Content ID {$contentId}"
            );

            try {
                /**
                 * Re-check in case another process generated
                 * the audio after initial query.
                 */
                $content->refresh();

                if (
                    !empty($content->audio_path) &&
                    trim($content->audio_path) !== ''
                ) {
                    $this->warn(
                        "Skipped Content ID {$contentId}: audio already exists."
                    );

                    $skipped++;
                    continue;
                }

                $englishText = trim((string) $content->content);

                if ($englishText === '') {
                    $this->warn(
                        "Skipped Content ID {$contentId}: English content is empty."
                    );

                    $skipped++;
                    continue;
                }

                /**
                 * File name used by the existing TTS system.
                 *
                 * Existing service generates the actual final path
                 * based on topic_id/content_id.
                 */
                $fileName = 'content_' . $contentId . '_en';

                /**
                 * Dedicated English method.
                 *
                 * Existing multilingual generate() remains untouched.
                 */
                $audioPath = $ttsService->generateEnglish(
                    $englishText,
                    $content->topic_id,
                    $contentId
                );

                if (
                    !is_string($audioPath) ||
                    trim($audioPath) === ''
                ) {
                    throw new \RuntimeException(
                        'TTS service returned an empty audio path.'
                    );
                }

                /**
                 * Save only the final merged audio path.
                 *
                 * Temporary chunk paths are never stored.
                 */
                $content->audio_path = $audioPath;
                $content->save();

                $this->info(
                    "English audio generated successfully for Content ID {$contentId}"
                );

                $this->line("Audio Path: {$audioPath}");

                Log::channel('ai')->info(
                    'MISSING ENGLISH TOPIC AUDIO GENERATED',
                    [
                        'content_id' => $contentId,
                        'topic_id' => $content->topic_id,
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
                        'content_id' => $contentId,
                        'topic_id' => $content->topic_id ?? null,
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