<?php

namespace App\Modules\Admin\Import\Services;

class HtmlCleanerService {
    public function clean(string $html): string {
        if (blank($html)) {
            return '';
        }

        logger()->info(
            '[Cleaner] Started',
            [
                'length' => strlen($html),
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | Normalize Line Breaks
    |--------------------------------------------------------------------------
    */

        $html = str_replace(
            ["\r\n", "\r"],
            "\n",
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Remove UTF-8 BOM
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Remove Office Namespace Elements
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            '/<\/?(o|w|v):[^>]*>/i',
            '',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Remove Office XML Namespace Attributes
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            '/\s+xmlns(:\w+)?="[^"]*"/i',
            '',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Normalize <br>
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            '/<br\s*\/?>/i',
            '<br>',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Remove Completely Empty Paragraphs
    |--------------------------------------------------------------------------
    |
    | Remove only if they contain absolutely nothing.
    | Preserve formatting paragraphs containing &nbsp;,
    | spans, images or any visible formatting.
    |
    */

        $html = preg_replace(
            '/<p\b[^>]*>\s*<\/p>/i',
            '',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Remove Empty DIV
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            '/<div\b[^>]*>\s*<\/div>/i',
            '',
            $html
        );

        /*
    |--------------------------------------------------------------------------
    | Preserve Original HTML
    |--------------------------------------------------------------------------
    |
    | DO NOT:
    | - Remove styles
    | - Remove classes
    | - Remove ids
    | - Remove inline CSS
    | - Remove spans
    |
    */

        /*
    |--------------------------------------------------------------------------
    | Preserve Block Separation
    |--------------------------------------------------------------------------
    */

        foreach (
            [
                'p',
                'div',
                'table',
                'figure',
                'ul',
                'ol',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
            ] as $tag
        ) {

            $html = preg_replace(
                "/<\/{$tag}>/i",
                "</{$tag}>\n",
                $html
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Collapse Blank Lines
    |--------------------------------------------------------------------------
    */

        $html = preg_replace(
            "/\n{3,}/",
            "\n\n",
            $html
        );

        $html = trim($html);

        logger()->info(
            '[Cleaner] Completed',
            [
                'length' => strlen($html),
            ]
        );

        return $html;
    }
}
