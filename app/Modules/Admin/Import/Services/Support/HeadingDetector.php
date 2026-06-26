<?php

namespace App\Modules\Admin\Import\Services\Support;

class HeadingDetector
{
    protected const MAX_HEADING_LENGTH = 150;

    protected const KNOWN_HEADINGS = [

        'Topic Overview',
        'Overview',
        'Introduction',
        'Chapter Overview',
        'Section Overview',

        'Learning Objectives',

        'Definition',
        'Definitions',

        'Explanation',

        'Clinical Importance',

        'Clinical Pearl',
        'Clinical Pearls',

        'Memory Aid',

        'Visual Example',
        'Visual Examples',

        'Vocabulary',
        'Vocabulary & Key Definitions',

        'Summary',

        'Key Takeaways',
        'Key Points',
        'Important Points',

        'Battery Status',

        'Arrhythmia Terms',

        'How To Read Report',

        'Troubleshooting',

        'Follow Up Summary',

        'Alert Terms',

        'Lead Measurement Terms',
    ];

    protected array $headingMap = [];

    public function __construct()
    {
        $this->headingMap = array_flip(
            array_map(
                fn ($item) => mb_strtolower(trim($item)),
                self::KNOWN_HEADINGS
            )
        );
    }

    public function isHeading(
        string $rawHtml,
        string $text
    ): bool {

        $text = trim(strip_tags($text));

        if ($text === '') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Ignore Module / Chapter / Topic
        |--------------------------------------------------------------------------
        */

        if ($this->isStructureTitle($text)) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Explicit H Marker
        |--------------------------------------------------------------------------
        */

        if (
            preg_match('/^\d+(\.\d+)*\.H\d+\b/i', $text)
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Known Heading
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $this->headingMap[
                    mb_strtolower($text)
                ]
            )
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Styled Heading
        |--------------------------------------------------------------------------
        */

        if (
            preg_match('/<(strong|b)\b/i', $rawHtml)
            &&
            mb_strlen($text) <= self::MAX_HEADING_LENGTH
            &&
            !$this->looksLikeSentence($text)
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Standalone Short Heading
        |--------------------------------------------------------------------------
        */

        if (
            mb_strlen($text) <= 60
            &&
            !$this->looksLikeSentence($text)
            &&
            !$this->looksLikeList($text)
        ) {
            return true;
        }

        return false;
    }

    protected function isStructureTitle(string $text): bool
    {
        return
            preg_match('/^Module\s+\d+/i', $text)
            ||
            preg_match('/^Chapter\s+\d+/i', $text)
            ||
            preg_match('/^Topic\s+\d+/i', $text);
    }

    protected function looksLikeSentence(string $text): bool
    {
        if (preg_match('/[.!?]$/', $text)) {
            return true;
        }

        return str_word_count($text) > 18;
    }

    protected function looksLikeList(string $text): bool
    {
        return
            preg_match('/^\d+\./', $text)
            ||
            preg_match('/^[A-Z]\./', $text)
            ||
            preg_match('/^[•●▪◦]/u', $text)
            ||
            preg_match('/^-/', $text);
    }
}