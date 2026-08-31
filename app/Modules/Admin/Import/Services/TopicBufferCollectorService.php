<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\Log;

class TopicBufferCollectorService {
    /**
     * Ordered topic blocks.
     *
     * Each block represents one original HTML element
     * exactly in the order it appeared inside the DOCX.
     */
    protected array $blocks = [];

    /**
     * Start a new topic.
     */
    public function start(): void {
        $this->blocks = [];

        Log::info(
            '[TopicBuffer] Topic Started'
        );
    }

    /**
     * Add one HTML block.
     */
    public function append(string $html): void {
        $html = trim($html);

        if ($html === '') {
            return;
        }

        $type = $this->detectType($html);

        $text = $this->extractText($html);
        
        if (

            $text === '' &&

            !in_array(
                $type,
                [
                    'image',
                    'table'
                ]
            )

        ) {

            Log::warning(
                '[TopicBuffer] Empty Block Ignored',
                [
                    'type' => $type
                ]
            );

            return;
        }

        $this->blocks[] = [

            'type' => $type,

            'html' => $html,

            'text' => $text,

        ];

        Log::debug(
            '[TopicBuffer] Block Added',
            [

                'type' => $type,

                'text' => mb_substr(
                    $text,
                    0,
                    120
                ),

            ]
        );
    }


    /**
     * Extract readable text.
     */
    protected function extractText(
        string $html
    ): string {

        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5
        );

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        );

        return trim($text);
    }

    /**
     * Get ordered blocks.
     */
    public function getBlocks(): array {
        return $this->blocks;
    }

    /**
     * Get complete HTML.
     */
    public function getHtml(): string {
        return implode(
            PHP_EOL,
            array_column($this->blocks, 'html')
        );
    }

    /**
     * Check whether buffer contains data.
     */
    public function hasContent(): bool {
        return !empty($this->blocks);
    }

    /**
     * Reset collector.
     */
    public function reset(): void {
        Log::info(
            '[TopicBuffer] Topic Completed',
            [
                'total_blocks' => count($this->blocks),

                'headings' => $this->countType('heading'),

                'paragraphs' => $this->countType('paragraph'),

                'lists' => $this->countType('list'),

                'tables' => $this->countType('table'),

                'images' => $this->countType('image'),

                'notes' => $this->countType('note'),

                'warnings' => $this->countType('warning'),
            ]
        );

        $this->blocks = [];
    }


    protected function countType(
        string $type
    ): int {

        return count(

            array_filter(

                $this->blocks,

                fn($block) => $block['type'] === $type

            )

        );
    }

    /**
     * Detect block type.
     *
     * DOCX headings may come as:
     *
     * <h1>Heading</h1>
     * <h2>Heading</h2>
     * <p><strong>Heading</strong></p>
     * <p><b>Heading</b></p>
     * <p><span style="font-weight:bold">Heading</span></p>
     */
    protected function detectType(
        string $html
    ): string {

        $lower = strtolower($html);

        /*
    |--------------------------------------------------------------------------
    | Heading
    |--------------------------------------------------------------------------
    */

        if ($this->looksLikeHeading($html)) {

            return 'heading';
        }

        /*
    |--------------------------------------------------------------------------
    | Image
    |--------------------------------------------------------------------------
    */

        if (

            strpos($lower, '<img') !== false ||

            strpos($lower, '<figure') !== false

        ) {

            return 'image';
        }

        /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

        if (

            strpos($lower, '<table') !== false

        ) {

            return 'table';
        }

        /*
    |--------------------------------------------------------------------------
    | Ordered / Unordered List
    |--------------------------------------------------------------------------
    */

        if (

            strpos($lower, '<ul') !== false ||

            strpos($lower, '<ol') !== false

        ) {

            return 'list';
        }

        /*
    |--------------------------------------------------------------------------
    | Warning
    |--------------------------------------------------------------------------
    */

        if (

            preg_match(
                '/\b(warning|caution|important)\b/i',
                strip_tags($html)
            )

        ) {

            return 'warning';
        }

        /*
    |--------------------------------------------------------------------------
    | Note
    |--------------------------------------------------------------------------
    */

        if (

            preg_match(
                '/^\s*(note|notes)\b/i',
                strip_tags($html)
            )

        ) {

            return 'note';
        }

        /*
    |--------------------------------------------------------------------------
    | Paragraph
    |--------------------------------------------------------------------------
    */

        if (

            strpos($lower, '<p') !== false

        ) {

            return 'paragraph';
        }

        /*
    |--------------------------------------------------------------------------
    | Unknown HTML
    |--------------------------------------------------------------------------
    */

        return 'html';
    }

    /**
     * Detect heading-like paragraphs generated by Word.
     */
    protected function looksLikeHeading(
        string $html
    ): bool {

        $text = trim(
            html_entity_decode(
                strip_tags($html),
                ENT_QUOTES | ENT_HTML5
            )
        );

        if ($text === '') {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Very long paragraphs are never headings.
    |--------------------------------------------------------------------------
    */

        if (mb_strlen($text) > 120) {

            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Multiple sentences usually indicate paragraph.
    |--------------------------------------------------------------------------
    */

        if (preg_match('/[.!?].+[.!?]/u', $text)) {

            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | Word exported headings
    |--------------------------------------------------------------------------
    */

        if (

            preg_match('/<strong\b/i', $html) ||

            preg_match('/<b\b/i', $html) ||

            preg_match(
                '/font-weight\s*:\s*(bold|700|800|900)/i',
                $html
            )

        ) {

            return true;
        }

        return false;
    }
}
