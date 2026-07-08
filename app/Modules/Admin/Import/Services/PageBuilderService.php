<?php

namespace App\Modules\Admin\Import\Services;
use Illuminate\Support\Facades\Log;

class PageBuilderService
{
    /**
     * Build final page object.
     */
    public function build(
        int $pageNumber,
        array $blocks,
        bool $appendFooter = false
    ): array {

        return [

            'page_number' => $pageNumber,

            'title' => $this->buildTitle(
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
     * Build page title.
     */
    protected function buildTitle(
        int $pageNumber,
        array $blocks
    ): string {

        $heading = $this->firstHeading($blocks);

        if ($heading !== null) {

            return sprintf(
                'Page %d : %s',
                $pageNumber,
                $heading
            );

        }

        return sprintf(
            'Page %d',
            $pageNumber
        );
    }

    /**
     * Build HTML.
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
     * First heading.
     */
    protected function firstHeading(
        array $blocks
    ): ?string {

        foreach ($blocks as $block) {

            if (!empty($block['is_heading'])) {

                return trim(
                    $block['text']
                );

            }

        }

        return null;
    }

    /**
     * Remove footer from last page.
     */
    public function removeFooter(
        string $content
    ): string {

        $footer = config(
            'import.topic_pagination.page_footer'
        );

        return trim(
            str_replace(
                $footer,
                '',
                $content
            )
        );
    }

    /**
     * Update page title.
     */
    public function updateTitle(
        array $page,
        string $title
    ): array {

        $page['title'] = $title;

        return $page;
    }

    /**
     * Update page content.
     */
    public function updateContent(
        array $page,
        string $content
    ): array {

        $page['content'] = $content;

        return $page;
    }
}