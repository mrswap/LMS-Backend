<?php

namespace App\Modules\Admin\Import\Services;

use DOMDocument;
use DOMNode;
use Illuminate\Support\Facades\Log;

class HierarchyParserService {
    /*
    |--------------------------------------------------------------------------
    | Structure Detection Patterns
    |--------------------------------------------------------------------------
    */

    protected const PATTERN_MODULE =
    '/^\s*Module\s*(?:No\.?)?\s*\d+\s*:?\s*/i';

    protected const PATTERN_CHAPTER =
    '/^\s*Chapter\s*(?:No\.?)?\s*\d+(?:\.\d+)*\s*:?\s*/i';

    protected const PATTERN_TOPIC =
    '/^\s*Topic\s*(?:No\.?)?\s*(\d+(?:\.\d+)*)\s*:?\s*(.+)?$/i';

    protected TopicBufferCollectorService $topicBuffer;

    public function __construct(
        TopicBufferCollectorService $topicBuffer
    ) {
        $this->topicBuffer = $topicBuffer;
    }

    /**
     * Parse HTML into hierarchy.
     */
    public function parse(string $html): array {


        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->loadHTML(
            mb_convert_encoding(
                $html,
                'HTML-ENTITIES',
                'UTF-8'
            )
        );

        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);

        if (!$body) {
            throw new \Exception('Invalid HTML.');
        }

        $nodes = [];

        $this->flattenNodes(
            $body,
            $nodes
        );

        $modules = [];


        $currentModuleIndex = null;

        $currentChapterIndex = null;

        $currentTopicIndex = null;

        foreach ($nodes as $node) {

            $rawHtml = trim(
                $dom->saveHTML($node)
            );

            $text = trim(
                html_entity_decode(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        strip_tags($rawHtml)
                    )
                )
            );

            $text = preg_replace(
                '/^[\s•●·▪◦◆►▶\-*]+/u',
                '',
                $text
            );

            $text = trim($text);

            if ($text === '' && $rawHtml === '') {
                continue;
            }

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
            | Module Detection
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    self::PATTERN_MODULE,
                    $text
                )
            ) {

                $this->finalizeCurrentTopic(
                    $modules,
                    $currentModuleIndex,
                    $currentChapterIndex,
                    $currentTopicIndex
                );

                $modules[] = [

                    'title' => $text,

                    'description' => '',

                    'chapters' => [],

                ];

                $currentModuleIndex = count($modules) - 1;

                $currentChapterIndex = null;

                $currentTopicIndex = null;

                Log::info(
                    'Module detected',
                    [
                        'title' => $text
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Chapter Detection
            |--------------------------------------------------------------------------
            */

            if (
                preg_match(
                    self::PATTERN_CHAPTER,
                    $text
                )
            ) {

                if (
                    $currentModuleIndex === null
                ) {
                    continue;
                }

                $this->finalizeCurrentTopic(
                    $modules,
                    $currentModuleIndex,
                    $currentChapterIndex,
                    $currentTopicIndex
                );

                $modules[$currentModuleIndex]['chapters'][] = [

                    'title' => $text,

                    'description' => '',

                    'topics' => [],

                ];

                $currentChapterIndex = count(
                    $modules[$currentModuleIndex]['chapters']
                ) - 1;

                $currentTopicIndex = null;

                Log::info(
                    'Chapter detected',
                    [
                        'title' => $text
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Topic Detection
            |--------------------------------------------------------------------------
            */

            $normalizedTopicText = preg_replace(
                '/^[\s•●·▪◦◆►▶\-*]+/u',
                '',
                $text
            );

            $topicTitle = null;

            if (
                preg_match(
                    self::PATTERN_TOPIC,
                    $normalizedTopicText,
                    $matches
                )
            ) {

                $topicTitle = trim(
                    $matches[2] ?? ''
                );

                $this->finalizeCurrentTopic(
                    $modules,
                    $currentModuleIndex,
                    $currentChapterIndex,
                    $currentTopicIndex
                );

                if (
                    $currentChapterIndex === null
                ) {
                    continue;
                }

                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][] = [

                    'title' => $topicTitle !== ''
                        ? $topicTitle
                        : $normalizedTopicText,

                    'blocks' => [],

                    'raw_html' => '',
                ];

                $currentTopicIndex = count(
                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics']
                ) - 1;

                $this->topicBuffer->start();

                Log::info(
                    'Topic detected',
                    [
                        'title' => $topicTitle
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Ignore Everything Until First Topic
            |--------------------------------------------------------------------------
            */

            if (
                $currentTopicIndex === null
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Append Everything To Current Topic
            |--------------------------------------------------------------------------
            |
            | Every block belongs to the current Topic.
            |
            | Paragraphs
            | Headings
            | Images
            | Tables
            | Lists
            | Notes
            | Warnings
            | Everything.
            |
            */

            $this->topicBuffer->append(
                $rawHtml
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Save Last Topic
        |--------------------------------------------------------------------------
        */

        $this->finalizeCurrentTopic(
            $modules,
            $currentModuleIndex,
            $currentChapterIndex,
            $currentTopicIndex
        );

        if (empty($modules)) {
            throw new \Exception(
                'No modules detected in document.'
            );
        }

        return [

            'modules' => $modules

        ];
    }

    /**
     * Save current topic buffer.
     */
    private function finalizeCurrentTopic(
        array &$modules,
        ?int $moduleIndex,
        ?int $chapterIndex,
        ?int $topicIndex
    ): void {

        if (

            $moduleIndex === null ||

            $chapterIndex === null ||

            $topicIndex === null

        ) {

            return;
        }

        $blocks = $this->topicBuffer->getBlocks();

        $html = $this->topicBuffer->getHtml();

        $modules[$moduleIndex]['chapters'][$chapterIndex]['topics'][$topicIndex]['blocks']
            = $blocks;

        $modules[$moduleIndex]['chapters'][$chapterIndex]['topics'][$topicIndex]['raw_html']
            = $html;

        Log::info(
            '[Parser] Topic Completed',
            [

                'topic' => $modules[$moduleIndex]['chapters'][$chapterIndex]['topics'][$topicIndex]['title'],

                'blocks' => count($blocks),

                'html_length' => strlen($html),

            ]
        );

        $this->topicBuffer->reset();
    }

    /**
     * Flatten DOM into sequential block elements.
     */
    /**
     * Flatten DOM into sequential block elements.
     *
     * Only leaf block elements are collected.
     * This prevents duplicate collection such as:
     *
     * <div>
     *     <h2>...</h2>
     *     <p>...</p>
     * </div>
     *
     * where previously DIV + H2 + P all became blocks.
     */
    protected function flattenNodes(
        DOMNode $node,
        array &$nodes
    ): void {

        foreach ($node->childNodes as $child) {

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            /*
        |--------------------------------------------------------------------------
        | Supported Block Elements
        |--------------------------------------------------------------------------
        */

            $supported = [

                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',

                'p',

                'div',

                'table',

                'ul',
                'ol',

                'figure',

                'img',

            ];

            if (!in_array($tag, $supported)) {

                if ($child->hasChildNodes()) {

                    $this->flattenNodes(
                        $child,
                        $nodes
                    );
                }

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | Ignore Word Header / Footer
        |--------------------------------------------------------------------------
        */

            $text = trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    strip_tags(
                        $child->textContent
                    )
                )
            );

            if (
                preg_match(
                    '/^Level\s+\d+\s*\|\s*Module\s+\d+\s*:/i',
                    $text
                )
            ) {
                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | DIV Handling
        |--------------------------------------------------------------------------
        |
        | Only keep DIV if it does NOT contain
        | another supported block element.
        |
        */

            if ($tag === 'div') {

                $hasNestedBlocks = false;

                foreach ($child->childNodes as $grandChild) {

                    if (
                        $grandChild->nodeType === XML_ELEMENT_NODE
                    ) {

                        $grandTag = strtolower(
                            $grandChild->nodeName
                        );

                        if (
                            in_array(
                                $grandTag,
                                [
                                    'h1',
                                    'h2',
                                    'h3',
                                    'h4',
                                    'h5',
                                    'h6',
                                    'p',
                                    'table',
                                    'ul',
                                    'ol',
                                    'figure',
                                    'img',
                                    'div',
                                ]
                            )
                        ) {

                            $hasNestedBlocks = true;

                            break;
                        }
                    }
                }

                /*
            |--------------------------------------------------------------------------
            | Nested Blocks
            |--------------------------------------------------------------------------
            */

                if ($hasNestedBlocks) {

                    $this->flattenNodes(
                        $child,
                        $nodes
                    );

                    continue;
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Store Block
        |--------------------------------------------------------------------------
        */

            $nodes[] = $child;

            Log::debug(
                '[Flatten] Block Added',
                [

                    'tag' => $tag,

                    'text' => mb_substr(
                        $text,
                        0,
                        80
                    ),

                ]
            );

            /*
        |--------------------------------------------------------------------------
        | Tables / Images are terminal nodes.
        |--------------------------------------------------------------------------
        */

            if (
                in_array(
                    $tag,
                    [
                        'table',
                        'img',
                        'figure',
                    ]
                )
            ) {
                continue;
            }
        }

        Log::info(
            '[Flatten] Completed',
            [
                'blocks' => count($nodes)
            ]
        );
    }
}
