<?php

namespace App\Modules\Admin\Import\Exceptions;

use Exception;
use Throwable;

class AssessmentExtractionException extends Exception
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        string $message = 'Assessment extraction failed.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Static Constructors
    |--------------------------------------------------------------------------
    */

    public static function emptyResponse(): self
    {
        return new self(
            'OpenAI returned an empty response.'
        );
    }

    public static function invalidJson(
        string $error
    ): self {

        return new self(
            'Invalid AI JSON response: ' . $error
        );
    }

    public static function missingStructure(
        string $key
    ): self {

        return new self(
            "AI response missing required key: {$key}"
        );
    }

    public static function extractionFailed(
        string $reason
    ): self {

        return new self(
            'Assessment extraction failed: ' . $reason
        );
    }

    public static function openAiFailed(): self
    {
        return new self(
            'Unable to communicate with OpenAI.'
        );
    }

    public static function invalidAssessment(): self
    {
        return new self(
            'AI returned an invalid assessment structure.'
        );
    }
}