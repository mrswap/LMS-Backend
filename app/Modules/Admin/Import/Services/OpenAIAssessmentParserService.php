<?php

namespace App\Modules\Admin\Import\Services;

use App\Modules\Admin\Import\DTO\AssessmentImportDTO;
use App\Modules\Admin\Import\Exceptions\AssessmentExtractionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAIAssessmentParserService {
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    protected const MAX_HTML_LENGTH = 120000;

    protected const LOG_CHANNEL = 'stack';

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(

        protected \App\Services\AI\OpenAIService $openAI,

        protected AssessmentValidationService $validator

    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Parse Assessment HTML
    |--------------------------------------------------------------------------
    */

    public function parse(
        string $html,
        ?int $importId = null
    ): AssessmentImportDTO {

        $startedAt = microtime(true);

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Started',
            [

                'import_id' => $importId,

                'html_length' => strlen($html),

            ]
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | Prepare HTML
            |--------------------------------------------------------------------------
            */

            $preparedHtml = $this->prepareHtml(
                $html
            );

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] HTML Prepared',
                [

                    'import_id' => $importId,

                    'prepared_length' => strlen(
                        $preparedHtml
                    ),

                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Build Messages
            |--------------------------------------------------------------------------
            */

            $messages = $this->buildMessages(
                $preparedHtml
            );

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Prompt Prepared',
                [

                    'import_id' => $importId,

                    'messages' => count(
                        $messages
                    ),

                ]
            );

            /*
            |--------------------------------------------------------------------------
            | OpenAI Request
            |--------------------------------------------------------------------------
            */

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Sending Request',
                [

                    'import_id' => $importId,

                ]
            );

            $response = $this->openAI->chat(
                $messages
            );

            if (

                blank(
                    $response
                )

            ) {

                Log::channel(
                    self::LOG_CHANNEL
                )->error(
                    '[Assessment AI] Empty Response',
                    [

                        'import_id' => $importId,

                    ]
                );

                throw AssessmentExtractionException::emptyResponse();
            }

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Response Received',
                [

                    'import_id' => $importId,

                    'response_length' => strlen(
                        $response
                    ),

                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Cleanup Response
            |--------------------------------------------------------------------------
            */

            $response = $this->cleanResponse(
                $response
            );

            /*
            |--------------------------------------------------------------------------
            | Decode JSON
            |--------------------------------------------------------------------------
            */

            $data = $this->decodeJson(
                $response,
                $importId
            );

            /*
            |--------------------------------------------------------------------------
            | DTO
            |--------------------------------------------------------------------------
            */

            $dto = AssessmentImportDTO::fromArray(
                $data
            );

            /*
            |--------------------------------------------------------------------------
            | Validate DTO
            |--------------------------------------------------------------------------
            */

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Validation Started',
                [

                    'import_id' => $importId,

                ]
            );

            $dto = $this->validator->validate(
                $dto
            );

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Validation Completed',
                [

                    'import_id' => $importId,

                    'topic_assessments' => $dto->totalTopics(),

                    'total_questions' => $dto->totalQuestions(),

                    'module_questions' => $dto->totalModuleQuestions(),

                    'topic_questions' => $dto->totalTopicQuestions(),

                ]
            );

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Completed',
                [

                    'import_id' => $importId,

                    'execution_time' => round(
                        microtime(true) - $startedAt,
                        3
                    ),

                    'memory_usage_mb' => round(
                        memory_get_peak_usage(true)
                            / 1024
                            / 1024,
                        2
                    ),

                ]
            );

            return $dto;
        } catch (AssessmentExtractionException $e) {

            Log::channel(
                self::LOG_CHANNEL
            )->error(
                '[Assessment AI] Validation Failed',
                [

                    'import_id' => $importId,

                    'message' => $e->getMessage(),

                ]
            );

            throw $e;
        } catch (Throwable $e) {

            Log::channel(
                self::LOG_CHANNEL
            )->error(
                '[Assessment AI] Unexpected Exception',
                [

                    'import_id' => $importId,

                    'message' => $e->getMessage(),

                    'file' => $e->getFile(),

                    'line' => $e->getLine(),

                    'trace' => $e->getTraceAsString(),

                ]
            );

            throw AssessmentExtractionException::extractionFailed(
                $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare HTML
    |--------------------------------------------------------------------------
    */

    protected function prepareHtml(
        string $html
    ): string {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Preparing HTML'
        );

        $html = trim($html);

        /*
        |--------------------------------------------------------------------------
        | Remove BOM
        |--------------------------------------------------------------------------
        */

        $html = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | UTF8
        |--------------------------------------------------------------------------
        */

        $html = mb_convert_encoding(
            $html,
            'UTF-8',
            'UTF-8'
        );

        /*
        |--------------------------------------------------------------------------
        | Normalize Line Endings
        |--------------------------------------------------------------------------
        */

        $html = str_replace(

            ["\r\n", "\r"],

            "\n",

            $html

        );

        /*
        |--------------------------------------------------------------------------
        | Remove NULL Bytes
        |--------------------------------------------------------------------------
        */

        $html = str_replace(
            "\0",
            '',
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | Length Protection
        |--------------------------------------------------------------------------
       

        if (

            strlen($html)

            >

            self::MAX_HTML_LENGTH

        ) {

            Log::channel(
                self::LOG_CHANNEL
            )->warning(
                '[Assessment AI] HTML Truncated',
                [

                    'original_length' => strlen($html),

                    'max_length' => self::MAX_HTML_LENGTH,

                ]
            );

            $html = substr(

                $html,

                0,

                self::MAX_HTML_LENGTH

            );
        }

        return trim($html);

         */
    }

    /*
    |--------------------------------------------------------------------------
    | Build Chat Messages
    |--------------------------------------------------------------------------
    */

    protected function buildMessages(
        string $html
    ): array {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Building Messages'
        );

        $prompt = $this->loadPrompt();

        return [

            [

                'role' => 'system',

                'content' =>

                'You ONLY return RFC8259 valid JSON. Never use markdown. Never explain anything.'

            ],

            [

                'role' => 'user',

                'content' =>

                $prompt

                    . PHP_EOL

                    . PHP_EOL

                    . '============================='

                    . PHP_EOL

                    . 'ASSESSMENT HTML'

                    . PHP_EOL

                    . '============================='

                    . PHP_EOL

                    . $html

            ]

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Load Prompt
    |--------------------------------------------------------------------------
    */

    protected function loadPrompt(): string {
        $path = base_path(

            'app/Modules/Admin/Import/Prompts/assessment-import-v1.txt'

        );

        if (

            ! file_exists($path)

        ) {

            throw AssessmentExtractionException::extractionFailed(

                'Assessment prompt file not found.'

            );
        }

        $prompt = file_get_contents(
            $path
        );

        if (

            blank($prompt)

        ) {

            throw AssessmentExtractionException::extractionFailed(

                'Assessment prompt is empty.'

            );
        }

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Prompt Loaded',
            [

                'length' => strlen($prompt),

                'path' => $path,

            ]
        );

        return trim($prompt);
    }

    /*
    |--------------------------------------------------------------------------
    | Clean AI Response
    |--------------------------------------------------------------------------
    */

    protected function cleanResponse(
        string $response
    ): string {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Cleaning Response'
        );

        $response = trim($response);

        /*
        |--------------------------------------------------------------------------
        | Remove Markdown Code Blocks
        |--------------------------------------------------------------------------
        */

        $response = preg_replace(
            '/^```json/i',
            '',
            $response
        );

        $response = preg_replace(
            '/^```/i',
            '',
            $response
        );

        $response = preg_replace(
            '/```$/',
            '',
            $response
        );

        $response = trim($response);

        /*
        |--------------------------------------------------------------------------
        | Extract JSON Block
        |--------------------------------------------------------------------------
        */

        $response = $this->extractJson(
            $response
        );

        /*
        |--------------------------------------------------------------------------
        | UTF8 Normalize
        |--------------------------------------------------------------------------
        */

        $response = mb_convert_encoding(
            $response,
            'UTF-8',
            'UTF-8'
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Invalid Control Characters
        |--------------------------------------------------------------------------
        */

        $response = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $response
        );

        /*
        |--------------------------------------------------------------------------
        | Remove UTF8 BOM
        |--------------------------------------------------------------------------
        */

        $response = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $response
        );

        /*
        |--------------------------------------------------------------------------
        | Trim
        |--------------------------------------------------------------------------
        */

        return trim($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract JSON Only
    |--------------------------------------------------------------------------
    */

    protected function extractJson(
        string $response
    ): string {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Extracting JSON'
        );

        $start = strpos(
            $response,
            '{'
        );

        $end = strrpos(
            $response,
            '}'
        );

        if (

            $start === false

            ||

            $end === false

        ) {

            throw AssessmentExtractionException::invalidJson(
                'JSON object not found.'
            );
        }

        return substr(

            $response,

            $start,

            ($end - $start) + 1

        );
    }

    /*
    |--------------------------------------------------------------------------
    | Decode JSON
    |--------------------------------------------------------------------------
    */

    protected function decodeJson(

        string $json,

        ?int $importId = null

    ): array {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Decoding JSON',
            [

                'import_id' => $importId,

                'json_length' => strlen(
                    $json
                ),

            ]
        );

        $data = json_decode(

            $json,

            true

        );

        if (

            json_last_error()

            !==

            JSON_ERROR_NONE

        ) {

            Log::channel(
                self::LOG_CHANNEL
            )->error(
                '[Assessment AI] Invalid JSON',
                [

                    'import_id' => $importId,

                    'error' => json_last_error_msg(),

                    'preview' => mb_substr(
                        $json,
                        0,
                        5000
                    ),

                    'length' => strlen(
                        $json
                    ),

                ]
            );

            throw AssessmentExtractionException::invalidJson(

                json_last_error_msg()

            );
        }

        /*
        |--------------------------------------------------------------------------
        | Structure Validation
        |--------------------------------------------------------------------------
        */

        if (

            ! array_key_exists(

                'module_assessment',

                $data

            )

        ) {

            throw AssessmentExtractionException::missingStructure(

                'module_assessment'

            );
        }

        if (

            ! array_key_exists(

                'topic_assessments',

                $data

            )

        ) {

            throw AssessmentExtractionException::missingStructure(

                'topic_assessments'

            );
        }

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] JSON Decoded Successfully',
            [

                'import_id' => $importId,

                'topic_assessments' => count(

                    $data['topic_assessments']

                        ?? []

                ),

                'module_questions' => count(

                    $data['module_assessment']['questions']

                        ?? []

                ),

            ]
        );

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Retry AI Request
    |--------------------------------------------------------------------------
    */

    protected function retryChat(
        array $messages,
        int $maxAttempts = 2
    ): ?string {

        $attempt = 1;

        while ($attempt <= $maxAttempts) {

            Log::channel(
                self::LOG_CHANNEL
            )->info(
                '[Assessment AI] Chat Attempt',
                [

                    'attempt' => $attempt,

                    'max_attempts' => $maxAttempts,

                ]
            );

            $response = $this->openAI->chat(
                $messages
            );

            if (! blank($response)) {

                Log::channel(
                    self::LOG_CHANNEL
                )->info(
                    '[Assessment AI] Chat Success',
                    [

                        'attempt' => $attempt,

                        'response_length' => strlen($response),

                    ]
                );

                return $response;
            }

            Log::channel(
                self::LOG_CHANNEL
            )->warning(
                '[Assessment AI] Empty AI Response',
                [

                    'attempt' => $attempt,

                ]
            );

            $attempt++;

            if ($attempt <= $maxAttempts) {

                sleep(1);
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Log Statistics
    |--------------------------------------------------------------------------
    */

    protected function logStatistics(
        AssessmentImportDTO $dto,
        float $startedAt,
        ?int $importId = null
    ): void {

        Log::channel(
            self::LOG_CHANNEL
        )->info(
            '[Assessment AI] Import Statistics',
            [

                'import_id' => $importId,

                'topic_assessments' => $dto->totalTopics(),

                'module_questions' => $dto->totalModuleQuestions(),

                'topic_questions' => $dto->totalTopicQuestions(),

                'total_questions' => $dto->totalQuestions(),

                'execution_time' => round(
                    microtime(true) - $startedAt,
                    3
                ),

                'memory_usage_mb' => round(
                    memory_get_peak_usage(true)
                        / 1024
                        / 1024,
                    2
                ),

            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Response Fingerprint
    |--------------------------------------------------------------------------
    */

    protected function fingerprint(
        string $response
    ): string {

        return hash(
            'sha256',
            $response
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Estimate Tokens
    |--------------------------------------------------------------------------
    */

    protected function estimateTokens(
        string $text
    ): int {

        return (int) ceil(
            strlen($text) / 4
        );
    }
}
