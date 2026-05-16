<?php

namespace App\Modules\Admin\Import\Services;

class HierarchyParserService
{
    /*
    |--------------------------------------------------------------------------
    | Parse HTML Hierarchy
    |--------------------------------------------------------------------------
    */

    public function parse(string $html): array
    {
        /*
        |--------------------------------------------------------------------------
        | Preserve Structure Breaks
        |--------------------------------------------------------------------------
        */

        $html = preg_replace(
            '/<\/p>/i',
            "</p>\n",
            $html
        );

        $html = preg_replace(
            '/<\/div>/i',
            "</div>\n",
            $html
        );

        $html = preg_replace(
            '/<\/table>/i',
            "</table>\n",
            $html
        );

        $html = preg_replace(
            '/<br\s*\/?>/i',
            "\n",
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | DOM LOAD
        |--------------------------------------------------------------------------
        */

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        $dom->loadHTML(
            mb_convert_encoding(
                $html,
                'HTML-ENTITIES',
                'UTF-8'
            )
        );

        libxml_clear_errors();

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        $body = $dom->getElementsByTagName('body')->item(0);

        if (!$body) {

            throw new \Exception(
                'Invalid HTML body.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STORAGE
        |--------------------------------------------------------------------------
        */

        $modules = [];

        $currentModuleIndex = null;

        $currentChapterIndex = null;

        $currentTopicIndex = null;

        $currentContentIndex = null;

        /*
        |--------------------------------------------------------------------------
        | LOOP NODES
        |--------------------------------------------------------------------------
        */

        foreach ($body->childNodes as $node) {

            /*
            |--------------------------------------------------------------------------
            | RAW HTML
            |--------------------------------------------------------------------------
            */

            $rawHtml = trim(
                $dom->saveHTML($node)
            );

            /*
            |--------------------------------------------------------------------------
            | TEXT
            |--------------------------------------------------------------------------
            */

            $text = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags($rawHtml)
                )
            );

            if (empty($text) && empty($rawHtml)) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Ignore separators
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^[_\-]{3,}$/',
                    $text
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MODULE
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^Module\s+\d+\s*:/i',
                    $text
                )
            ) {

                $modules[] = [

                    'title' => $text,

                    'chapters' => [],
                ];

                $currentModuleIndex =
                    count($modules) - 1;

                $currentChapterIndex = null;

                $currentTopicIndex = null;

                $currentContentIndex = null;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | CHAPTER
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^Chapter\s+\d+\.\d+\s*:/i',
                    $text
                )
            ) {

                if (
                    $currentModuleIndex === null
                ) {
                    continue;
                }

                $modules
                [$currentModuleIndex]
                ['chapters'][] = [

                    'title' => $text,

                    'topics' => [],
                ];

                $currentChapterIndex =
                    count(
                        $modules
                        [$currentModuleIndex]
                        ['chapters']
                    ) - 1;

                $currentTopicIndex = null;

                $currentContentIndex = null;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TOPIC
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    '/^Topic\s+\d+\.\d+\.\d+\s*:/i',
                    $text
                )
            ) {

                if (
                    $currentChapterIndex === null
                ) {
                    continue;
                }

                $modules
                [$currentModuleIndex]
                ['chapters']
                [$currentChapterIndex]
                ['topics'][] = [

                    'title' => $text,

                    'contents' => [],
                ];

                $currentTopicIndex =
                    count(
                        $modules
                        [$currentModuleIndex]
                        ['chapters']
                        [$currentChapterIndex]
                        ['topics']
                    ) - 1;

                $currentContentIndex = null;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | HEADING
            |--------------------------------------------------------------------------
            |
            | 1.1.1.H1 Heading
            |
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(H\d+)\s+(.*)$/i',
                    $text,
                    $matches
                )
            ) {

                if (
                    $currentTopicIndex === null
                ) {
                    continue;
                }

                $topicCode = trim($matches[1]);

                $headingCode = strtoupper(
                    trim($matches[2])
                );

                $title = trim($matches[3]);

                $modules
                [$currentModuleIndex]
                ['chapters']
                [$currentChapterIndex]
                ['topics']
                [$currentTopicIndex]
                ['contents'][] = [

                    'topic_code' => $topicCode,

                    'heading_code' => $headingCode,

                    'heading_level' => strtolower($headingCode),

                    'type' => 'text',

                    'title' => $title,

                    'content' => '',
                ];

                $currentContentIndex =
                    count(
                        $modules
                        [$currentModuleIndex]
                        ['chapters']
                        [$currentChapterIndex]
                        ['topics']
                        [$currentTopicIndex]
                        ['contents']
                    ) - 1;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | CONTENT START
            |--------------------------------------------------------------------------
            |
            | 1.1.1.C1
            |
            */

            if (
                preg_match(
                    '/^(\d+\.\d+\.\d+)\.(C\d+)/i',
                    $text
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | Remove Cx Marker ONLY
                |--------------------------------------------------------------------------
                */

                $cleanHtml = preg_replace(
                    '/^\s*<[^>]+>\s*\d+\.\d+\.\d+\.(C\d+)\s*/i',
                    '',
                    $rawHtml
                );

                $cleanHtml = preg_replace(
                    '/^\s*\d+\.\d+\.\d+\.(C\d+)\s*/i',
                    '',
                    $cleanHtml
                );

                if (
                    $currentContentIndex !== null
                ) {

                    $modules
                    [$currentModuleIndex]
                    ['chapters']
                    [$currentChapterIndex]
                    ['topics']
                    [$currentTopicIndex]
                    ['contents']
                    [$currentContentIndex]
                    ['content']
                    .= $cleanHtml;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL CONTENT
            |--------------------------------------------------------------------------
            |
            | Everything belongs to current heading
            | until:
            | - new Hx
            | - new Topic
            | - new Chapter
            | - new Module
            |
            */

            if (
                $currentContentIndex !== null
            ) {

                $modules
                [$currentModuleIndex]
                ['chapters']
                [$currentChapterIndex]
                ['topics']
                [$currentTopicIndex]
                ['contents']
                [$currentContentIndex]
                ['content']
                .= $rawHtml;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (empty($modules)) {

            throw new \Exception(
                'No modules detected.'
            );
        }

        return [
            'modules' => $modules,
        ];
    }
}