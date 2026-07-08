<?php

namespace App\Modules\Admin\Import\Services;

class BlockEnricherService {
    /**
     * Enrich topic blocks.
     */
    public function enrich(array $blocks): array {
        $enriched = [];

        foreach ($blocks as $index => $block) {

            $type = $block['type'] ?? 'html';

            $html = trim($block['html'] ?? '');

            $text = $this->extractText($html);

            $words = match ($type) {

                'image' => 1,

                'table' => 1,

                default => $this->countWords($text),
            };

            $enriched[] = [

                'index' => $index,

                'type' => $type,

                'html' => $html,

                'text' => $text,

                'words' => $words,

                'is_heading' => $type === 'heading',

                'is_paragraph' => $type === 'paragraph',

                'is_list' => $type === 'list',

                'is_image' => $type === 'image',

                'is_table' => $type === 'table',

                'is_first' => $index === 0,

                'is_last' => false,

                'page_break_after' => false,

            ];
        }

        if (!empty($enriched)) {

            $enriched[count($enriched) - 1]['is_last'] = true;
        }
        logger()->info(
            '[BlockEnricher] Completed',
            [

                'blocks' => count($enriched),

                'headings' => count(
                    array_filter(
                        $enriched,
                        fn($b) => $b['is_heading']
                    )
                ),

                'images' => count(
                    array_filter(
                        $enriched,
                        fn($b) => $b['is_image']
                    )
                ),

                'tables' => count(
                    array_filter(
                        $enriched,
                        fn($b) => $b['is_table']
                    )
                ),

                'words' => array_sum(
                    array_column(
                        $enriched,
                        'words'
                    )
                ),

            ]
        );

        return $enriched;
    }

    /**
     * Extract readable text.
     */
    protected function extractText(string $html): string {
        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5
        );

        $text = str_replace(
            "\xC2\xA0",
            ' ',
            $text
        );

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        );

        return trim($text);
    }


    /**
     * Count readable words.
     */
    protected function countWords(string $text): int {
        if ($text === '') {
            return 0;
        }

        preg_match_all(
            '/[\p{L}\p{N}]+(?:[-\/][\p{L}\p{N}]+)*/u',
            $text,
            $matches
        );

        return count($matches[0]);
    }

    /**
     * Sum total readable count.
     */
    public function totalWords(array $blocks): int {
        return array_sum(
            array_column($blocks, 'words')
        );
    }

    /**
     * Find all heading positions.
     */
    public function headingPositions(array $blocks): array {
        $positions = [];

        foreach ($blocks as $block) {

            if ($block['is_heading']) {

                $positions[] = $block['index'];
            }
        }

        return $positions;
    }

    /**
     * Get first heading text.
     */
    public function firstHeading(array $blocks): ?string {
        foreach ($blocks as $block) {

            if ($block['is_heading']) {

                return $block['text'];
            }
        }

        return null;
    }

    /**
     * Check whether topic contains headings.
     */
    public function hasHeading(array $blocks): bool {
        foreach ($blocks as $block) {

            if ($block['is_heading']) {

                return true;
            }
        }

        return false;
    }
}
