<?php

namespace App\Modules\Admin\Import\Services\Support;

class HtmlNodeHelper
{
    public function getText(
        string $html
    ): string {

        $text = trim(
            preg_replace(
                '/\s+/',
                ' ',
                strip_tags($html)
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Word Bullets
        |--------------------------------------------------------------------------
        */

        $text = preg_replace(
            '/^[●•▪◦◆►]+\s*/u',
            '',
            $text
        );

        return trim($text);
    }

    public function isModule(
        string $text
    ): bool {

        return preg_match(
            '/Module\s+\d+\s*:/i',
            $text
        ) === 1;
    }

    public function isChapter(
        string $text
    ): bool {

        return preg_match(
            '/Chapter\s+\d+(\.\d+)?\s*:/i',
            $text
        ) === 1;
    }

    public function isTopic(
        string $text
    ): bool {

        return preg_match(
            '/Topic\s+\d+\.\d+\.\d+/i',
            $text
        ) === 1;
    }

    public function isHeaderFooter(
        string $text
    ): bool {

        return preg_match(
            '/Level\s+\d+\s*\|\s*Module\s+\d+/i',
            $text
        ) === 1;
    }

    public function stripIdentifiers(
        string $text
    ): string {

        return trim(
            preg_replace(
                '/\d+\.\d+\.\d+\.(H\d+|C\d+)\s*/i',
                '',
                $text
            )
        );
    }
}
