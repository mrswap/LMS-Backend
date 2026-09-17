<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TextToSpeechService
{
    /**
     * OpenAI TTS text limit safety threshold.
     */
    protected int $maxChunkCharacters = 3500;

    /**
     * Maximum API retry attempts.
     */
    protected int $maxTtsAttempts = 3;

    /**
     * Delay between retry attempts in seconds.
     */
    protected int $retryDelaySeconds = 3;

    /**
     * FFmpeg absolute path on AlmaLinux server.
     */
    protected string $ffmpegPath = '/usr/bin/ffmpeg';

    /**
     * Generate exactly ONE final audio file.
     *
     * Temporary chunk files may be created internally for long text,
     * but only the final merged audio path is returned.
     */
    public function generate(
        string $text,
        string $language,
        ?int $topicId = null,
        ?int $contentId = null
    ): string {
        $text = trim($text);
        $language = strtolower(trim($language));

        if ($text === '') {
            throw new RuntimeException('TTS text cannot be empty.');
        }

        Log::channel('ai')->info('TTS SERVICE ACTUAL INPUT', [
            'language' => $language,
            'topic_id' => $topicId,
            'content_id' => $contentId,
            'mb_length' => mb_strlen($text),
            'byte_length' => strlen($text),
            'preview_start' => mb_substr($text, 0, 150),
            'preview_end' => mb_substr($text, -150),
        ]);

        if (mb_strlen($text) > $this->maxChunkCharacters) {
            return $this->generateLongAudio(
                $text,
                $language,
                $topicId,
                $contentId
            );
        }

        return $this->generateSingleAudio(
            $text,
            $language,
            $topicId,
            $contentId
        );
    }

    /**
     * Generate English audio using complete content hierarchy.
     *
     * English path remains:
     *
     * public/uploads/content-management/audio/
     * {level}/{module}/{chapter}/{topic}/{content}/en/audio.mp3
     */
    public function generateEnglish(
        string $text,
        int $levelId,
        int $moduleId,
        int $chapterId,
        int $topicId,
        int $contentId
    ): string {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException(
                'English TTS text cannot be empty.'
            );
        }

        Log::channel('ai')->info(
            'ENGLISH CONTENT AUDIO GENERATION STARTED',
            [
                'level_id' => $levelId,
                'module_id' => $moduleId,
                'chapter_id' => $chapterId,
                'topic_id' => $topicId,
                'content_id' => $contentId,
                'text_length' => mb_strlen($text),
            ]
        );

        if (mb_strlen($text) > $this->maxChunkCharacters) {
            return $this->generateEnglishLongAudio(
                $text,
                $levelId,
                $moduleId,
                $chapterId,
                $topicId,
                $contentId
            );
        }

        return $this->generateEnglishSingleAudio(
            $text,
            $levelId,
            $moduleId,
            $chapterId,
            $topicId,
            $contentId
        );
    }

    /**
     * Generate one short multilingual audio file.
     */
    protected function generateSingleAudio(
        string $text,
        string $language,
        ?int $topicId = null,
        ?int $contentId = null
    ): string {
        $directory = $this->getAudioDirectory(
            $topicId,
            $contentId,
            $language
        );

        $fileName = $this->generateFinalFileName(
            $topicId,
            $contentId,
            $language
        );

        $absolutePath = $directory .
            DIRECTORY_SEPARATOR .
            $fileName;

        File::ensureDirectoryExists($directory);

        Log::channel('ai')->info(
            'SINGLE TTS GENERATION STARTED',
            [
                'language' => $language,
                'text_length' => mb_strlen($text),
                'output' => $absolutePath,
            ]
        );

        $audioBinary = $this->requestTtsAudioWithRetry(
            text: $text,
            language: $language,
            context: [
                'type' => 'multilingual_single',
                'topic_id' => $topicId,
                'content_id' => $contentId,
            ]
        );

        $written = file_put_contents(
            $absolutePath,
            $audioBinary
        );

        if (
            $written === false ||
            !file_exists($absolutePath) ||
            filesize($absolutePath) < 100
        ) {
            throw new RuntimeException(
                'Unable to save generated audio file.'
            );
        }

        Log::channel('ai')->info(
            'SINGLE TTS GENERATION COMPLETED',
            [
                'language' => $language,
                'file' => $absolutePath,
                'bytes' => filesize($absolutePath),
            ]
        );

        return $this->getRelativeAudioPath($absolutePath);
    }

    /**
     * Generate long multilingual text as temporary chunks
     * and merge them into exactly ONE final MP3 file.
     */
    protected function generateLongAudio(
        string $text,
        string $language,
        ?int $topicId = null,
        ?int $contentId = null
    ): string {
        $chunks = $this->splitTextIntoChunks(
            $text,
            $this->maxChunkCharacters
        );

        if (empty($chunks)) {
            throw new RuntimeException(
                'Unable to split text into TTS chunks.'
            );
        }

        $directory = $this->getAudioDirectory(
            $topicId,
            $contentId,
            $language
        );

        File::ensureDirectoryExists($directory);

        $finalFileName = $this->generateFinalFileName(
            $topicId,
            $contentId,
            $language
        );

        $finalAbsolutePath = $directory .
            DIRECTORY_SEPARATOR .
            $finalFileName;

        $temporaryFiles = [];

        Log::channel('ai')->info(
            'LONG TTS CHUNKING STARTED',
            [
                'language' => $language,
                'total_characters' => mb_strlen($text),
                'total_chunks' => count($chunks),
                'final_file' => $finalAbsolutePath,
            ]
        );

        try {
            foreach ($chunks as $index => $chunk) {
                $partNumber = $index + 1;

                $temporaryFile = $directory .
                    DIRECTORY_SEPARATOR .
                    $this->generateTemporaryPartFileName(
                        $topicId,
                        $contentId,
                        $language,
                        $partNumber
                    );

                Log::channel('ai')->info(
                    'TTS CHUNK GENERATION STARTED',
                    [
                        'language' => $language,
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                        'chunk_length' => mb_strlen($chunk),
                        'file' => $temporaryFile,
                    ]
                );

                $audioBinary = $this->requestTtsAudioWithRetry(
                    text: $chunk,
                    language: $language,
                    context: [
                        'type' => 'multilingual_chunk',
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                    ]
                );

                $written = file_put_contents(
                    $temporaryFile,
                    $audioBinary
                );

                if (
                    $written === false ||
                    !file_exists($temporaryFile) ||
                    filesize($temporaryFile) < 100
                ) {
                    throw new RuntimeException(
                        "Failed to save TTS chunk {$partNumber}."
                    );
                }

                $temporaryFiles[] = $temporaryFile;

                Log::channel('ai')->info(
                    'TTS CHUNK GENERATION COMPLETED',
                    [
                        'language' => $language,
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                        'bytes' => filesize($temporaryFile),
                        'file' => $temporaryFile,
                    ]
                );
            }

            /**
             * Merge all temporary files into one final MP3.
             */
            $this->mergeAudioFiles(
                $temporaryFiles,
                $finalAbsolutePath
            );

            if (
                !file_exists($finalAbsolutePath) ||
                filesize($finalAbsolutePath) < 100
            ) {
                throw new RuntimeException(
                    'Final merged audio file is missing or invalid.'
                );
            }

            Log::channel('ai')->info(
                'LONG TTS FINAL AUDIO CREATED',
                [
                    'language' => $language,
                    'topic_id' => $topicId,
                    'content_id' => $contentId,
                    'final_file' => $finalAbsolutePath,
                    'bytes' => filesize($finalAbsolutePath),
                    'temporary_parts' => count($temporaryFiles),
                ]
            );

            /**
             * Delete temporary chunks only after successful merge.
             */
            $deletedParts = 0;

            foreach ($temporaryFiles as $temporaryFile) {
                if (
                    file_exists($temporaryFile) &&
                    @unlink($temporaryFile)
                ) {
                    $deletedParts++;
                }
            }

            Log::channel('ai')->info(
                'TTS TEMPORARY CHUNKS DELETED',
                [
                    'language' => $language,
                    'deleted_parts' => $deletedParts,
                    'total_parts' => count($temporaryFiles),
                    'final_file' => $finalAbsolutePath,
                ]
            );

            /**
             * Only one final path is returned to caller/database/player.
             */
            return $this->getRelativeAudioPath(
                $finalAbsolutePath
            );
        } catch (Throwable $e) {
            Log::channel('ai')->error(
                'LONG TTS GENERATION FAILED',
                [
                    'language' => $language,
                    'topic_id' => $topicId,
                    'content_id' => $contentId,
                    'error' => $e->getMessage(),
                    'final_file' => $finalAbsolutePath,
                    'temporary_files' => $temporaryFiles,
                ]
            );

            /**
             * Cleanup temporary files on failure also.
             */
            foreach ($temporaryFiles as $temporaryFile) {
                if (file_exists($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }

            /**
             * Do not return part files.
             * Do not save partial paths in database.
             */
            throw $e;
        }
    }

    /**
     * Generate short English audio using content-management hierarchy.
     */
    protected function generateEnglishSingleAudio(
        string $text,
        int $levelId,
        int $moduleId,
        int $chapterId,
        int $topicId,
        int $contentId
    ): string {
        $directory = $this->getEnglishAudioDirectory(
            $levelId,
            $moduleId,
            $chapterId,
            $topicId,
            $contentId
        );

        File::ensureDirectoryExists($directory);

        $absolutePath = $directory .
            DIRECTORY_SEPARATOR .
            'audio.mp3';

        Log::channel('ai')->info(
            'ENGLISH SINGLE TTS GENERATION STARTED',
            [
                'level_id' => $levelId,
                'module_id' => $moduleId,
                'chapter_id' => $chapterId,
                'topic_id' => $topicId,
                'content_id' => $contentId,
                'output' => $absolutePath,
                'text_length' => mb_strlen($text),
            ]
        );

        $audioBinary = $this->requestTtsAudioWithRetry(
            text: $text,
            language: 'en',
            context: [
                'type' => 'english_single',
                'level_id' => $levelId,
                'module_id' => $moduleId,
                'chapter_id' => $chapterId,
                'topic_id' => $topicId,
                'content_id' => $contentId,
            ]
        );

        $written = file_put_contents(
            $absolutePath,
            $audioBinary
        );

        if (
            $written === false ||
            !file_exists($absolutePath) ||
            filesize($absolutePath) < 100
        ) {
            throw new RuntimeException(
                'Unable to save English audio file.'
            );
        }

        Log::channel('ai')->info(
            'ENGLISH SINGLE TTS GENERATION COMPLETED',
            [
                'content_id' => $contentId,
                'file' => $absolutePath,
                'bytes' => filesize($absolutePath),
            ]
        );

        return $this->getRelativeAudioPath(
            $absolutePath
        );
    }

    /**
     * Generate long English text into chunks and merge
     * into one audio.mp3.
     */
    protected function generateEnglishLongAudio(
        string $text,
        int $levelId,
        int $moduleId,
        int $chapterId,
        int $topicId,
        int $contentId
    ): string {
        $chunks = $this->splitTextIntoChunks(
            $text,
            $this->maxChunkCharacters
        );

        if (empty($chunks)) {
            throw new RuntimeException(
                'Unable to split English text into TTS chunks.'
            );
        }

        $directory = $this->getEnglishAudioDirectory(
            $levelId,
            $moduleId,
            $chapterId,
            $topicId,
            $contentId
        );

        File::ensureDirectoryExists($directory);

        $finalAbsolutePath = $directory .
            DIRECTORY_SEPARATOR .
            'audio.mp3';

        $temporaryFiles = [];

        Log::channel('ai')->info(
            'ENGLISH LONG TTS CHUNKING STARTED',
            [
                'level_id' => $levelId,
                'module_id' => $moduleId,
                'chapter_id' => $chapterId,
                'topic_id' => $topicId,
                'content_id' => $contentId,
                'total_characters' => mb_strlen($text),
                'total_chunks' => count($chunks),
                'final_file' => $finalAbsolutePath,
            ]
        );

        try {
            foreach ($chunks as $index => $chunk) {
                $partNumber = $index + 1;

                $temporaryFile = $directory .
                    DIRECTORY_SEPARATOR .
                    'audio_part_' .
                    $partNumber .
                    '_' .
                    uniqid() .
                    '.mp3';

                Log::channel('ai')->info(
                    'ENGLISH TTS CHUNK GENERATION STARTED',
                    [
                        'level_id' => $levelId,
                        'module_id' => $moduleId,
                        'chapter_id' => $chapterId,
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                        'chunk_length' => mb_strlen($chunk),
                        'file' => $temporaryFile,
                    ]
                );

                $audioBinary = $this->requestTtsAudioWithRetry(
                    text: $chunk,
                    language: 'en',
                    context: [
                        'type' => 'english_chunk',
                        'level_id' => $levelId,
                        'module_id' => $moduleId,
                        'chapter_id' => $chapterId,
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                    ]
                );

                $written = file_put_contents(
                    $temporaryFile,
                    $audioBinary
                );

                if (
                    $written === false ||
                    !file_exists($temporaryFile) ||
                    filesize($temporaryFile) < 100
                ) {
                    throw new RuntimeException(
                        "Failed to save English TTS chunk {$partNumber}."
                    );
                }

                $temporaryFiles[] = $temporaryFile;

                Log::channel('ai')->info(
                    'ENGLISH TTS CHUNK GENERATION COMPLETED',
                    [
                        'level_id' => $levelId,
                        'module_id' => $moduleId,
                        'chapter_id' => $chapterId,
                        'topic_id' => $topicId,
                        'content_id' => $contentId,
                        'part' => $partNumber,
                        'total_parts' => count($chunks),
                        'bytes' => filesize($temporaryFile),
                        'file' => $temporaryFile,
                    ]
                );
            }

            /**
             * Merge all English temporary files into final audio.mp3.
             */
            $this->mergeAudioFiles(
                $temporaryFiles,
                $finalAbsolutePath
            );

            if (
                !file_exists($finalAbsolutePath) ||
                filesize($finalAbsolutePath) < 100
            ) {
                throw new RuntimeException(
                    'Final merged English audio file is missing or invalid.'
                );
            }

            Log::channel('ai')->info(
                'ENGLISH LONG TTS FINAL AUDIO CREATED',
                [
                    'level_id' => $levelId,
                    'module_id' => $moduleId,
                    'chapter_id' => $chapterId,
                    'topic_id' => $topicId,
                    'content_id' => $contentId,
                    'final_file' => $finalAbsolutePath,
                    'bytes' => filesize($finalAbsolutePath),
                    'temporary_parts' => count($temporaryFiles),
                ]
            );

            /**
             * Delete temporary English chunks only after successful merge.
             */
            $deletedParts = 0;

            foreach ($temporaryFiles as $temporaryFile) {
                if (
                    file_exists($temporaryFile) &&
                    @unlink($temporaryFile)
                ) {
                    $deletedParts++;
                }
            }

            Log::channel('ai')->info(
                'ENGLISH TTS TEMPORARY CHUNKS DELETED',
                [
                    'content_id' => $contentId,
                    'deleted_parts' => $deletedParts,
                    'total_parts' => count($temporaryFiles),
                    'final_file' => $finalAbsolutePath,
                ]
            );

            return $this->getRelativeAudioPath(
                $finalAbsolutePath
            );
        } catch (Throwable $e) {
            Log::channel('ai')->error(
                'ENGLISH LONG TTS GENERATION FAILED',
                [
                    'level_id' => $levelId,
                    'module_id' => $moduleId,
                    'chapter_id' => $chapterId,
                    'topic_id' => $topicId,
                    'content_id' => $contentId,
                    'error' => $e->getMessage(),
                    'final_file' => $finalAbsolutePath,
                    'temporary_files' => $temporaryFiles,
                ]
            );

            /**
             * Cleanup temporary files on failure.
             */
            foreach ($temporaryFiles as $temporaryFile) {
                if (file_exists($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }

            /**
             * Do not return partial chunk paths.
             */
            throw $e;
        }
    }

    /**
     * Request TTS audio with retry mechanism.
     *
     * This helper is used by:
     *
     * - Multilingual short audio
     * - Multilingual long chunks
     * - English short audio
     * - English long chunks
     *
     * It always returns valid audio binary or throws an exception.
     */
    protected function requestTtsAudioWithRetry(
        string $text,
        string $language,
        array $context = []
    ): string {
        $languageCode = $this->resolveLanguageCode($language);
        $lastException = null;

        for (
            $attempt = 1;
            $attempt <= $this->maxTtsAttempts;
            $attempt++
        ) {
            try {
                Log::channel('ai')->info(
                    'TTS API ATTEMPT',
                    array_merge(
                        $context,
                        [
                            'language' => $language,
                            'language_code' => $languageCode,
                            'attempt' => $attempt,
                            'max_attempts' => $this->maxTtsAttempts,
                            'text_length' => mb_strlen($text),
                        ]
                    )
                );

                $response = app(OpenAIService::class)->speech(
                    $text,
                    $languageCode
                );

                if (
                    is_string($response) &&
                    strlen($response) >= 100
                ) {
                    Log::channel('ai')->info(
                        'TTS API ATTEMPT SUCCESS',
                        array_merge(
                            $context,
                            [
                                'language' => $language,
                                'language_code' => $languageCode,
                                'attempt' => $attempt,
                                'bytes' => strlen($response),
                            ]
                        )
                    );

                    return $response;
                }

                throw new RuntimeException(
                    'Empty or invalid audio response.'
                );
            } catch (Throwable $e) {
                $lastException = $e;

                Log::channel('ai')->warning(
                    'TTS API ATTEMPT FAILED',
                    array_merge(
                        $context,
                        [
                            'language' => $language,
                            'language_code' => $languageCode,
                            'attempt' => $attempt,
                            'max_attempts' => $this->maxTtsAttempts,
                            'error' => $e->getMessage(),
                        ]
                    )
                );

                if (
                    $attempt < $this->maxTtsAttempts
                ) {
                    sleep($this->retryDelaySeconds);
                }
            }
        }

        throw new RuntimeException(
            "TTS generation failed after {$this->maxTtsAttempts} attempts for language {$language}.",
            0,
            $lastException
        );
    }

    /**
     * Merge temporary MP3 files into one final MP3.
     */
    protected function mergeAudioFiles(
        array $audioFiles,
        string $finalFile
    ): void {
        if (empty($audioFiles)) {
            throw new RuntimeException(
                'No audio files available for merging.'
            );
        }

        if (
            !is_file($this->ffmpegPath) ||
            !is_executable($this->ffmpegPath)
        ) {
            throw new RuntimeException(
                "FFmpeg not found or not executable at {$this->ffmpegPath}"
            );
        }

        $concatFile = tempnam(
            sys_get_temp_dir(),
            'tts_concat_'
        );

        if ($concatFile === false) {
            throw new RuntimeException(
                'Unable to create FFmpeg concat file.'
            );
        }

        try {
            $concatContent = '';

            foreach ($audioFiles as $audioFile) {
                if (
                    !file_exists($audioFile) ||
                    filesize($audioFile) < 100
                ) {
                    throw new RuntimeException(
                        "Audio chunk missing or invalid: {$audioFile}"
                    );
                }

                /**
                 * FFmpeg concat format.
                 */
                $safePath = str_replace(
                    "'",
                    "'\\''",
                    $audioFile
                );

                $concatContent .= "file '{$safePath}'\n";
            }

            $concatWritten = file_put_contents(
                $concatFile,
                $concatContent
            );

            if (
                $concatWritten === false ||
                !file_exists($concatFile)
            ) {
                throw new RuntimeException(
                    'Unable to write FFmpeg concat file.'
                );
            }

            /**
             * Remove old final file before creating fresh output.
             */
            if (file_exists($finalFile)) {
                @unlink($finalFile);
            }

            $command = sprintf(
                '%s -y -f concat -safe 0 -i %s -c copy %s 2>&1',
                escapeshellcmd($this->ffmpegPath),
                escapeshellarg($concatFile),
                escapeshellarg($finalFile)
            );

            Log::channel('ai')->info(
                'FFMPEG MERGE STARTED',
                [
                    'command' => $command,
                    'input_files' => $audioFiles,
                    'output_file' => $finalFile,
                ]
            );

            $output = [];
            $returnCode = 0;

            exec(
                $command,
                $output,
                $returnCode
            );

            if (
                $returnCode !== 0 ||
                !file_exists($finalFile) ||
                filesize($finalFile) < 100
            ) {
                Log::channel('ai')->error(
                    'FFMPEG MERGE FAILED',
                    [
                        'return_code' => $returnCode,
                        'output' => implode("\n", $output),
                        'output_file' => $finalFile,
                    ]
                );

                throw new RuntimeException(
                    'FFmpeg failed to merge audio files.'
                );
            }

            Log::channel('ai')->info(
                'FFMPEG MERGE SUCCESSFUL',
                [
                    'return_code' => $returnCode,
                    'output_file' => $finalFile,
                    'bytes' => filesize($finalFile),
                ]
            );
        } finally {
            if (file_exists($concatFile)) {
                @unlink($concatFile);
            }
        }
    }

    /**
     * Split text safely by paragraph, sentence and word.
     */
    protected function splitTextIntoChunks(
        string $text,
        int $maxCharacters
    ): array {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split(
            "/\R{2,}/u",
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $candidate = $currentChunk === ''
                ? $paragraph
                : $currentChunk . "\n\n" . $paragraph;

            if (
                mb_strlen($candidate) <= $maxCharacters
            ) {
                $currentChunk = $candidate;
                continue;
            }

            if ($currentChunk !== '') {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }

            /**
             * Paragraph itself larger than limit.
             * Split by sentences.
             */
            if (
                mb_strlen($paragraph) > $maxCharacters
            ) {
                $sentences = preg_split(
                    '/(?<=[.!?।॥])\s+/u',
                    $paragraph,
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );

                foreach ($sentences as $sentence) {
                    $sentence = trim($sentence);

                    if ($sentence === '') {
                        continue;
                    }

                    $candidate = $currentChunk === ''
                        ? $sentence
                        : $currentChunk . ' ' . $sentence;

                    if (
                        mb_strlen($candidate) <= $maxCharacters
                    ) {
                        $currentChunk = $candidate;
                        continue;
                    }

                    if ($currentChunk !== '') {
                        $chunks[] = trim($currentChunk);
                    }

                    /**
                     * Single sentence larger than limit:
                     * split by words.
                     */
                    if (
                        mb_strlen($sentence) > $maxCharacters
                    ) {
                        $words = preg_split(
                            '/\s+/u',
                            $sentence,
                            -1,
                            PREG_SPLIT_NO_EMPTY
                        );

                        $wordChunk = '';

                        foreach ($words as $word) {
                            $wordCandidate = $wordChunk === ''
                                ? $word
                                : $wordChunk . ' ' . $word;

                            if (
                                mb_strlen($wordCandidate)
                                <= $maxCharacters
                            ) {
                                $wordChunk = $wordCandidate;
                            } else {
                                if ($wordChunk !== '') {
                                    $chunks[] = trim($wordChunk);
                                }

                                $wordChunk = $word;
                            }
                        }

                        $currentChunk = $wordChunk;
                    } else {
                        $currentChunk = $sentence;
                    }
                }
            } else {
                $currentChunk = $paragraph;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = trim($currentChunk);
        }

        return array_values(
            array_filter(
                $chunks,
                fn($chunk) => trim($chunk) !== ''
            )
        );
    }

    /**
     * Resolve language to OpenAI-compatible language code.
     */
    protected function resolveLanguageCode(
        string $language
    ): string {
        return match (
            strtolower(trim($language))
        ) {
            'english', 'en' => 'en',
            'hindi', 'hi' => 'hi',
            'punjabi', 'pa' => 'pa',
            'marathi', 'mr' => 'mr',
            'gujarati', 'gu' => 'gu',
            'bengali', 'bn' => 'bn',
            'tamil', 'ta' => 'ta',
            'telugu', 'te' => 'te',
            'kannada', 'kn' => 'kn',
            'malayalam', 'ml' => 'ml',
            'odia', 'or' => 'or',
            'urdu', 'ur' => 'ur',
            default => $language,
        };
    }

    /**
     * Multilingual audio directory.
     *
     * Path:
     * public/uploads/curriculum/programs/topic-audio/{topic_id}/{language}
     */
    protected function getAudioDirectory(
        ?int $topicId,
        ?int $contentId,
        string $language
    ): string {
        $baseDirectory = public_path(
            'uploads/curriculum/programs/topic-audio'
        );

        /**
         * Audio folder topic ID ke according hoga.
         * Agar topic ID missing ho to content ID fallback hoga.
         */
        $identifier = $topicId ?: $contentId ?: 'general';

        return $baseDirectory .
            DIRECTORY_SEPARATOR .
            $identifier .
            DIRECTORY_SEPARATOR .
            strtolower($language);
    }

    /**
     * Multilingual final filename.
     *
     * Example:
     * topic_675_hi.mp3
     * topic_675_pa.mp3
     */
    protected function generateFinalFileName(
        ?int $topicId,
        ?int $contentId,
        string $language
    ): string {
        /**
         * Filename always content ID se unique hoga.
         */
        $identifier = $contentId ?: $topicId ?: 'general';

        return 'topic_' .
            $identifier .
            '_' .
            strtolower($language) .
            '.mp3';
    }

    /**
     * Temporary multilingual chunk filename.
     *
     * Example:
     * topic_675_hi_part_1.mp3
     */
    protected function generateTemporaryPartFileName(
        ?int $topicId,
        ?int $contentId,
        string $language,
        int $partNumber
    ): string {
        /**
         * Temporary filename bhi content ID based hoga.
         */
        $identifier = $contentId ?: $topicId ?: 'general';

        return 'topic_' .
            $identifier .
            '_' .
            strtolower($language) .
            '_part_' .
            $partNumber .
            '.mp3';
    }

    /**
     * Convert absolute public path to relative path for DB/player.
     */
    protected function getRelativeAudioPath(
        string $absolutePath
    ): string {
        $publicPath = public_path();

        return ltrim(
            str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                str_replace(
                    $publicPath,
                    '',
                    $absolutePath
                )
            ),
            '/'
        );
    }

    /**
     * English audio directory using complete content hierarchy.
     *
     * Path:
     * public/uploads/content-management/audio/
     * {level}/{module}/{chapter}/{topic}/{content}/en
     */
    protected function getEnglishAudioDirectory(
        int $levelId,
        int $moduleId,
        int $chapterId,
        int $topicId,
        int $contentId
    ): string {
        return public_path(
            'uploads/content-management/audio/' .
                $levelId . '/' .
                $moduleId . '/' .
                $chapterId . '/' .
                $topicId . '/' .
                $contentId . '/en'
        );
    }
}