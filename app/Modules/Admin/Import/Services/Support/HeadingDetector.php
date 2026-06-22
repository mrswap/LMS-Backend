<?php

namespace App\Modules\Admin\Import\Services\Support;

class HeadingDetector
{
    protected array $knownHeadings = [

        'Topic Overview',
        'Overview',
        'Introduction',

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

    public function isHeading(
        string $rawHtml,
        string $text
    ): bool {

        $text = trim($text);

        if ($text === '') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Ignore Structure
        |--------------------------------------------------------------------------
        */

        if (
            preg_match('/Module\s+\d+/i', $text)
            ||
            preg_match('/Chapter\s+\d+/i', $text)
            ||
            preg_match('/Topic\s+\d+/i', $text)
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | H Marker
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/\d+\.\d+\.\d+\.H\d+/i',
                $text
            )
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Known Headings
        |--------------------------------------------------------------------------
        */

        foreach ($this->knownHeadings as $heading) {

            if (
                strcasecmp(
                    trim($text),
                    $heading
                ) === 0
            ) {
                return true;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Styled Heading
        |--------------------------------------------------------------------------
        */

        if (
            preg_match('/<(strong|b)/i', $rawHtml)
            &&
            mb_strlen($text) < 150
        ) {
            return true;
        }

        return false;
    }
}
