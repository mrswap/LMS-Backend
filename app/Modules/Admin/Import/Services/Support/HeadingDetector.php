<?php

namespace App\Modules\Admin\Import\Services\Support;

use Illuminate\Support\Facades\Log;

class HeadingDetector {
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


    protected const HC_PATTERN =
    '/^(\d+(?:\.\d+)*)\.H(\d+)C(\d+)\s*/i';

    protected array $headingMap = [];

    public function __construct() {
        $this->headingMap = array_flip(
            array_map(
                fn($item) => mb_strtolower(trim($item)),
                self::KNOWN_HEADINGS
            )
        );
    }

    public function isHeading(
        string $rawHtml,
        string $text
    ): bool {
        Log::info('==== NEW HEADING DETECTOR EXECUTED ====');
        Log::info('RETURN TRUE => HC PATTERN', [
            'text' => $text
        ]);
        $text = trim(strip_tags($text));

        $text = $this->normalizeHeadingText($text);

        if ($text === '') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | HC Pattern = Always Heading
        |--------------------------------------------------------------------------
        */

        if ($this->hasHCHeadingPattern($text)) {
            Log::info('RETURN TRUE => HC PATTERN', [
                'text' => $text
            ]);
            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Ignore Module / Chapter / Topic Titles
        |--------------------------------------------------------------------------
        */

        if ($this->isStructureTitle($text)) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Bullet / List items are NEVER headings
        |--------------------------------------------------------------------------
        */

        if (
            $this->isBulletHtml($rawHtml)
            ||
            $this->looksLikeList($text)
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Explicit Heading Marker
        |--------------------------------------------------------------------------
        */

        if (
            preg_match('/^\d+(?:\.\d+)*\.H\d+\b/i', $text)
        ) {
            Log::info('RETURN TRUE => HC PATTERN', [
                'text' => $text
            ]);
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Known Headings
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $this->headingMap[mb_strtolower($text)]
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
            ! $this->looksLikeSentence($text)
            &&
            ! $this->looksLikeContent($text)
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
            ! $this->looksLikeSentence($text)
            &&
            ! $this->looksLikeContent($text)
        ) {
            return true;
        }

        return false;
    }



    /*
    |--------------------------------------------------------------------------
    | Normalize Heading Text
    |--------------------------------------------------------------------------
    */

    protected function normalizeHeadingText(string $text): string {
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $text = str_replace(
            ["\\", "\r", "\n", "\t"],
            " ",
            $text
        );

        $text = preg_replace('/\s+/u', ' ', $text);
        /*
        |--------------------------------------------------------------------------
        | Remove leading bullets / dots / symbols
        |--------------------------------------------------------------------------
        */

        $text = preg_replace(
            '/^[\s\p{Z}\x{2022}●▪◦◆►▶•·\-*]+/u',
            '',
            trim($text)
        );

        return trim($text);
    }


    /*
    |--------------------------------------------------------------------------
    | HC Heading Pattern
    |--------------------------------------------------------------------------
    */

    public function hasHCHeadingPattern(string $text): bool {
        $text = $this->normalizeHeadingText($text);

        return preg_match(
            self::HC_PATTERN,
            $text
        ) === 1;
    }

    public function stripHCHeadingPattern(string $text): string {
        $text = $this->normalizeHeadingText($text);

        return trim(
            preg_replace(
                self::HC_PATTERN,
                '',
                $text
            )
        );
    }

    public function extractHCHeading(string $text): ?array {

        $text = $this->normalizeHeadingText($text);
        if (
            !preg_match(
                self::HC_PATTERN,
                trim($text),
                $matches
            )
        ) {
            return null;
        }

        return [

            'topic_code'   => $matches[1],

            'heading_no'   => (int)$matches[2],

            'content_no'   => (int)$matches[3],

            'title' => $this->stripHCHeadingPattern($text),
        ];
    }



    protected function isStructureTitle(string $text): bool {
        return

            preg_match(
                '/^Module\s*(?:No\.?)?\s*\d+.*$/i',
                $text
            )

            ||

            preg_match(
                '/^Chapter\s*(?:No\.?)?\s*\d+(?:\.\d+)*.*$/i',
                $text
            )

            ||

            preg_match(
                '/^Topic\s*(?:No\.?)?\s*\d+(?:\.\d+)*.*$/i',
                $text
            );
    }

    public function looksLikeSentence(string $text): bool {
        $text = trim($text);

        if (preg_match('/[.!?:;]$/', $text)) {
            return true;
        }

        if (
            preg_match(
                '/^(after|before|by|under|during|once|when|where|this|these|those|the|in|on|at)\b/i',
                $text
            )
        ) {
            return true;
        }

        return str_word_count($text) > 12;
    }

    protected function looksLikeList(string $text): bool {
        $text = trim($text);

        return
            preg_match('/^\d+[\.\)]\s*/', $text)
            ||
            preg_match('/^[A-Z][\.\)]\s*/', $text)
            ||
            preg_match('/^[\x{2022}●▪◦◆►•]+\s*/u', $text)
            ||
            preg_match('/^[-–—]\s*/u', $text);
    }

    protected function isBulletHtml(string $html): bool {
        $text = trim(strip_tags($html));

        return preg_match(
            '/^[\x{2022}●▪◦◆►•]+\s*/u',
            $text
        ) === 1;
    }

    public  function looksLikeContent(string $text): bool {
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = str_replace(
            ["\\", "\r", "\n", "\t"],
            " ",
            $text
        );

        $text = preg_replace('/\s+/u', ' ', $text);

        $text = trim($text);


        // Medical values
        if (preg_match('/[<>≤≥=±%\/×]/u', $text)) {
            return true;
        }

        // Units
        // Medical Units / Formula

        if (
            preg_match(
                '/\b(bpm|mmhg|mmhg|mm|cm|kg|mg|ml|hr|hrs|min|sec|ms|mv|ma|hz)\b/i',
                $text
            )
        ) {
            return true;
        }
        // Multiple numbers
        preg_match_all('/\d+/', $text, $matches);

        if (count($matches[0]) >= 2) {
            return true;
        }

        // Mathematical / Medical Expression
        if (
            preg_match(
                '/(<|>|≤|≥|=|±|×|\/|\d+\s*(bpm|mmhg|kg|mg|ml))/i',
                $text
            )
        ) {
            return true;
        }
        // Mixed formula/value

        if (
            preg_match('/[A-Za-z]/', $text)
            &&
            preg_match('/\d/', $text)
            &&
            preg_match('/[<>=%×\/]/', $text)
        ) {
            return true;
        }
        return false;
    }
}
