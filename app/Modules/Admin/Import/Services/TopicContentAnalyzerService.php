<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\Log;

class TopicContentAnalyzerService {
    /**
     * Analyze a topic.
     *
     * @param array $topic
     * @return array
     */
    public function analyze(array $topic): array {



        $blocks = $topic['blocks'] ?? [];

        Log::info(
            '[Analyzer] Started',
            [
                'blocks' => count($blocks)
            ]
        );

        $analysis = [

            // Original Topic
            'topic' => $topic,

            // Blocks
            'blocks' => $blocks,

            'total_blocks' => count($blocks),

            // Statistics
            'total_count' => 0,

            'word_count' => 0,

            'heading_count' => 0,

            'paragraph_count' => 0,

            'list_count' => 0,

            'image_count' => 0,

            'table_count' => 0,

            'html_count' => 0,

            // Heading indexes
            'heading_positions' => [],

            // Pagination
            'required_pages' => 0,

            'target_per_page' => 0,

        ];

        foreach ($blocks as $index => $block) {

            $type = $block['type'] ?? 'html';

            $html = $block['html'] ?? '';

            $words = 0;

            switch ($type) {

                case 'heading':

                    $words = $this->countWords($html);

                    $analysis['heading_count']++;

                    $analysis['heading_positions'][] = $index;

                    break;

                case 'paragraph':

                    $words = $this->countWords($html);

                    $analysis['paragraph_count']++;

                    break;

                case 'list':

                    $words = $this->countWords($html);

                    $analysis['list_count']++;

                    break;

                case 'image':

                    $words = 1;

                    $analysis['image_count']++;

                    break;

                case 'table':

                    $words = 1;

                    $analysis['table_count']++;

                    break;

                default:

                    $words = $this->countWords($html);

                    $analysis['html_count']++;

                    break;
            }

            $analysis['word_count'] += ($type == 'image' || $type == 'table') ? 0 : $words;

            $analysis['total_count'] += $words;

            /*
    |--------------------------------------------------------------------------
    | Enrich Block
    |--------------------------------------------------------------------------
    */

            $block['words'] = $words;

            $block['is_heading'] = ($type === 'heading');

            $block['is_paragraph'] = ($type === 'paragraph');

            $block['is_list'] = ($type === 'list');

            $block['is_image'] = ($type === 'image');

            $block['is_table'] = ($type === 'table');

            $blocks[$index] = $block;
        }

        $analysis['required_pages'] = $this->calculateRequiredPages(
            $analysis['total_count']
        );

        $analysis['target_per_page'] = $this->calculateTargetPerPage(
            $analysis['total_count'],
            $analysis['required_pages']
        );

        $analysis['average_per_page'] = $analysis['target_per_page'];

        $analysis['has_headings'] =
            $analysis['heading_count'] > 0;

        $analysis['has_images'] =
            $analysis['image_count'] > 0;

        $analysis['has_tables'] =
            $analysis['table_count'] > 0;

        Log::info(
            '[Analyzer] Completed',
            [

                'total_count' => $analysis['total_count'],

                'required_pages' => $analysis['required_pages'],

                'target_per_page' => $analysis['target_per_page'],

                'headings' => $analysis['heading_count'],

                'paragraphs' => $analysis['paragraph_count'],

                'images' => $analysis['image_count'],

                'tables' => $analysis['table_count'],

            ]
        );
        
        $analysis['blocks'] = $blocks;

        return $analysis;
    }

    /**
     * Count readable words.
     *
     * Unicode safe.
     *
     * Rules:
     * - Ignore HTML tags
     * - Ignore multiple spaces
     * - Keep hyphenated words together
     * - Count Image/Table separately (not here)
     */
    protected function countWords(string $html): int {
        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5
        );

        $text = preg_replace('/\s+/u', ' ', $text);

        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        /*
    |--------------------------------------------------------------------------
    | Unicode Safe Word Matching
    |--------------------------------------------------------------------------
    |
    | Supports:
    | English
    | Unicode
    | Medical Terms
    | COVID-19
    | IL-6
    | ECG-derived
    |
    */

        preg_match_all(
            '/[\p{L}\p{N}]+(?:[-\/][\p{L}\p{N}]+)*/u',
            $text,
            $matches
        );

        $count = count($matches[0]);

        Log::debug(
            '[Analyzer] Word Count',
            [

                'count' => $count,

                'sample' => mb_substr(
                    $text,
                    0,
                    80
                )

            ]
        );

        return $count;
    }

    /**
     * Calculate required pages.
     */
    protected function calculateRequiredPages(
        int $totalCount
    ): int {

        $targetWords = config(
            'import.topic_pagination.target_words_per_page',
            350
        );

        $minimumPages = config(
            'import.topic_pagination.minimum_pages',
            5
        );

        if ($totalCount <= 0) {
            return $minimumPages;
        }

        $pages = (int) ceil(
            $totalCount / $targetWords
        );

        return max(
            $minimumPages,
            $pages
        );
    }

    /**
     * Calculate ideal target count per page.
     */
    protected function calculateTargetPerPage(
        int $totalCount,
        int $pages
    ): int {

        if ($pages <= 0) {
            return $totalCount;
        }
        $target = (int) ceil(
            $totalCount / $pages
        );

        Log::debug(
            '[Analyzer] Target Per Page',
            [
                'target' => $target,
            ]
        );

        return $target;
    }

    /**
     * Check whether block is heading.
     */
    public function isHeading(array $block): bool {
        return ($block['type'] ?? '') === 'heading';
    }

    /**
     * Check whether block is image.
     */
    public function isImage(array $block): bool {
        return ($block['type'] ?? '') === 'image';
    }

    /**
     * Check whether block is table.
     */
    public function isTable(array $block): bool {
        return ($block['type'] ?? '') === 'table';
    }

    /**
     * Check whether block is paragraph.
     */
    public function isParagraph(array $block): bool {
        return ($block['type'] ?? '') === 'paragraph';
    }

    /**
     * Check whether block is list.
     */
    public function isList(array $block): bool {
        return ($block['type'] ?? '') === 'list';
    }
}
