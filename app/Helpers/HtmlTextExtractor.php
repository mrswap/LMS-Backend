<?php

namespace App\Helpers;

class HtmlTextExtractor
{
    public static function clean(?string $html): string
    {
        if (!$html) {
            return '';
        }

        $text = strip_tags($html);

        $text = html_entity_decode($text);

        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
