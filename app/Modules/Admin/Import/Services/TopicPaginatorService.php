<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\Log;

class TopicPaginatorService {
    /**
     * Paginate analyzed topic.
     */
    public function paginate(array $analysis): array {
        $blocks = $analysis['blocks'];

        $requiredPages = $analysis['required_pages'];

        $targetPerPage = $analysis['target_per_page'];

        $pages = [];

        $currentBlocks = [];

        $currentWords = 0;

        $pageNumber = 1;

        $blockCount = count($blocks);

        for ($i = 0; $i < $blockCount; $i++) {

            $block = $blocks[$i];

            $currentBlocks[] = $block;

            $currentWords += ($block['words'] ?? 0);

            /*
            |--------------------------------------------------------------------------
            | Target not reached
            |--------------------------------------------------------------------------
            */

            if ($currentWords < $targetPerPage) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Last block
            |--------------------------------------------------------------------------
            */

            if ($i === $blockCount - 1) {

                $pages[] = $this->buildPage(
                    $pageNumber,
                    $currentBlocks,
                    false
                );

                $currentBlocks = [];

                break;
            }

            /*
            |--------------------------------------------------------------------------
            | Wait for next heading
            |--------------------------------------------------------------------------
            */
            $nextBlock = $blocks[$i + 1] ?? null;

            if (
                !$nextBlock ||
                empty($nextBlock['is_heading'])
            ) {
                continue;
            }

            if (!$nextBlock['is_heading']) {
                continue;
            }

            $pages[] = $this->buildPage(
                $pageNumber,
                $currentBlocks,
                true
            );

            $pageNumber++;

            $currentBlocks = [];

            $currentWords = 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Remaining Blocks
        |--------------------------------------------------------------------------
        */

        if (!empty($currentBlocks)) {

            $pages[] = $this->buildPage(
                $pageNumber,
                $currentBlocks,
                false
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure last page has no footer
        |--------------------------------------------------------------------------
        */

        $last = count($pages) - 1;

        if ($last >= 0) {

            $pages[$last]['content'] =
                $this->removeFooter(
                    $pages[$last]['content']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure minimum pages
        |--------------------------------------------------------------------------
        */

        while (
            count($pages) < $requiredPages
        ) {

            $pages[] = [

                'page_number' => count($pages) + 1,

                'title' => 'Page ' . (count($pages) + 1),

                'content' => '',

                'order' => count($pages) + 1,

            ];
        }

        return $pages;
    }

    /**
     * Build page.
     */
    protected function buildPage(
        int $pageNumber,
        array $blocks,
        bool $appendFooter
    ): array {

        return [

            'page_number' => $pageNumber,

            'title' => $this->buildPageTitle(
                $pageNumber,
                $blocks
            ),

            'content' => $this->buildContent(
                $blocks,
                $appendFooter
            ),

            'order' => $pageNumber,

        ];
    }

    /**
     * Generate page title.
     */
    protected function buildPageTitle(
        int $pageNumber,
        array $blocks
    ): string {

        foreach ($blocks as $block) {

            if (!empty($block['is_heading'])) {

                return sprintf(
                    'Page %d : %s',
                    $pageNumber,
                    trim($block['text'])
                );
            }
        }

        return sprintf(
            'Page %d',
            $pageNumber
        );
    }

    /**
     * Generate page HTML.
     */
    protected function buildContent(
        array $blocks,
        bool $appendFooter
    ): string {

        $html = '';

        foreach ($blocks as $block) {

            $html .= $block['html'] . PHP_EOL;
        }

        if ($appendFooter) {

            $html .= PHP_EOL;

            $html .= config(
                'import.topic_pagination.page_footer'
            );
        }

        return trim($html);
    }

    /**
     * Remove footer.
     */
    protected function removeFooter(
        string $html
    ): string {

        $footer = config(
            'import.topic_pagination.page_footer'
        );

        return trim(
            str_replace(
                $footer,
                '',
                $html
            )
        );
    }

    /**
     * Get first heading text.
     */
    protected function firstHeading(
        array $blocks
    ): ?string {

        foreach ($blocks as $block) {

            if (!empty($block['is_heading'])) {

                return $block['text'];
            }
        }

        return null;
    }
}
