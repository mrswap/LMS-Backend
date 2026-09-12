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
     * Generate one short audio file.
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

        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        File::ensureDirectoryExists($directory);

        Log::channel('ai')->info('SINGLE TTS GENERATION STARTED', [
            'language' => $language,
            'text_length' => mb_strlen($text),
            'output' => $absolutePath,
        ]);

        $languageCode = $this->resolveLanguageCode($language);

        $audioBinary = app(OpenAIService::class)->speech(
            $text,
            $languageCode
        );

        if (!is_string($audioBinary) || strlen($audioBinary) < 100) {
            throw new RuntimeException(
                'OpenAI TTS returned empty or invalid audio response.'
            );
        }

        $written = file_put_contents($absolutePath, $audioBinary);

        if ($written === false || !file_exists($absolutePath)) {
            throw new RuntimeException(
                'Unable to save generated audio file.'
            );
        }

        Log::channel('ai')->info('SINGLE TTS GENERATION COMPLETED', [
            'language' => $language,
            'file' => $absolutePath,
            'bytes' => filesize($absolutePath),
        ]);

        return $this->getRelativeAudioPath($absolutePath);
    }

    /**
     * Generate long text as temporary chunks and merge them
     * into exactly ONE final MP3 file.
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

        Log::channel('ai')->info('LONG TTS CHUNKING STARTED', [
            'language' => $language,
            'total_characters' => mb_strlen($text),
            'total_chunks' => count($chunks),
            'final_file' => $finalAbsolutePath,
        ]);

        try {
            $languageCode = $this->resolveLanguageCode($language);

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

                Log::channel('ai')->info('TTS CHUNK GENERATION STARTED', [
                    'part' => $partNumber,
                    'total_parts' => count($chunks),
                    'chunk_length' => mb_strlen($chunk),
                    'file' => $temporaryFile,
                ]);

                $audioBinary = app(OpenAIService::class)->speech(
                    $chunk,
                    $languageCode
                );

                if (
                    !is_string($audioBinary) ||
                    strlen($audioBinary) < 100
                ) {
                    throw new RuntimeException(
                        "Invalid audio response for TTS chunk {$partNumber}."
                    );
                }

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

                Log::channel('ai')->info('TTS CHUNK GENERATION COMPLETED', [
                    'part' => $partNumber,
                    'bytes' => filesize($temporaryFile),
                    'file' => $temporaryFile,
                ]);
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

            Log::channel('ai')->info('LONG TTS FINAL AUDIO CREATED', [
                'language' => $language,
                'final_file' => $finalAbsolutePath,
                'bytes' => filesize($finalAbsolutePath),
                'temporary_parts' => count($temporaryFiles),
            ]);

            /**
             * Delete temporary chunks ONLY after successful merge.
             */
            foreach ($temporaryFiles as $temporaryFile) {
                if (file_exists($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }

            Log::channel('ai')->info(
                'TTS TEMPORARY CHUNKS DELETED',
                [
                    'deleted_parts' => count($temporaryFiles),
                    'final_file' => $finalAbsolutePath,
                ]
            );

            /**
             * Only ONE final path is returned to caller/database/player.
             */
            return $this->getRelativeAudioPath($finalAbsolutePath);

        } catch (Throwable $e) {
            Log::channel('ai')->error('LONG TTS GENERATION FAILED', [
                'language' => $language,
                'error' => $e->getMessage(),
                'final_file' => $finalAbsolutePath,
                'temporary_files' => $temporaryFiles,
            ]);

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

            file_put_contents($concatFile, $concatContent);

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

            Log::channel('ai')->info('FFMPEG MERGE STARTED', [
                'command' => $command,
                'input_files' => $audioFiles,
                'output_file' => $finalFile,
            ]);

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
                Log::channel('ai')->error('FFMPEG MERGE FAILED', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                    'output_file' => $finalFile,
                ]);

                throw new RuntimeException(
                    'FFmpeg failed to merge audio files.'
                );
            }

            Log::channel('ai')->info('FFMPEG MERGE SUCCESSFUL', [
                'return_code' => $returnCode,
                'output_file' => $finalFile,
                'bytes' => filesize($finalFile),
            ]);

        } finally {
            if (file_exists($concatFile)) {
                @unlink($concatFile);
            }
        }
    }

    /**
     * Split text safely by paragraph/sentence/word.
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

            if (mb_strlen($candidate) <= $maxCharacters) {
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
            if (mb_strlen($paragraph) > $maxCharacters) {
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

                    if (mb_strlen($candidate) <= $maxCharacters) {
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
                    if (mb_strlen($sentence) > $maxCharacters) {
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
                fn ($chunk) => trim($chunk) !== ''
            )
        );
    }

    /**
     * Resolve language to OpenAI-compatible language code.
     */
    protected function resolveLanguageCode(string $language): string
    {
        return match (strtolower(trim($language))) {
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
     * Audio directory.
     */
    protected function getAudioDirectory(
        ?int $topicId,
        ?int $contentId,
        string $language
    ): string {
        $baseDirectory = public_path(
            'uploads/curriculum/programs/topic-audio'
        );

        $identifier = $topicId ?: $contentId ?: 'general';

        return $baseDirectory .
            DIRECTORY_SEPARATOR .
            $identifier .
            DIRECTORY_SEPARATOR .
            $language;
    }

    /**
     * Final single filename.
     */
    protected function generateFinalFileName(
        ?int $topicId,
        ?int $contentId,
        string $language
    ): string {
        $identifier = $topicId ?: $contentId ?: 'general';

        return 'topic_' .
            $identifier .
            '_' .
            $language .
            '.mp3';
    }

    /**
     * Temporary chunk filename.
     */
    protected function generateTemporaryPartFileName(
        ?int $topicId,
        ?int $contentId,
        string $language,
        int $partNumber
    ): string {
        $identifier = $topicId ?: $contentId ?: 'general';

        return 'topic_' .
            $identifier .
            '_' .
            $language .
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
}