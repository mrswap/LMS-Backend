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

        $body = $dom
            ->getElementsByTagName('body')
            ->item(0);

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

        $currentDescriptionTarget = null;

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

            if (
                empty($text)
                &&
                empty($rawHtml)
            ) {
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
                    'description' => null,



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
                    '/^Chapter\s+\d+(\.\d+)?\s*:/i',
                    $text
                )
            ) {

                if (
                    $currentModuleIndex === null
                ) {
                    continue;
                }

                $modules[$currentModuleIndex]['chapters'][] = [

                    'title' => $text,

                    'topics' => [],

                    'description' => null,



                ];

                $currentChapterIndex =
                    count(
                        $modules[$currentModuleIndex]['chapters']
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

                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][] = [

                    'title' => $text,
                    'description' => null,

                    'contents' => [],
                ];

                $currentTopicIndex =
                    count(
                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics']
                    ) - 1;

                $currentContentIndex = null;

                continue;
            }
            /*
|--------------------------------------------------------------------------
| DESCRIPTION MARKERS
|--------------------------------------------------------------------------
|
| 1.D
| 1.1.D
| 1.1.1.D
|
*/

            if (
                preg_match(
                    '/^(\d+(?:\.\d+){0,2})\.D\s*(.*)$/i',
                    $text,
                    $matches
                )
            ) {

                $code = trim($matches[1]);

                $inlineDescription = trim(
                    $matches[2] ?? ''
                );

                /*
|--------------------------------------------------------------------------
| MODULE
|--------------------------------------------------------------------------
*/

                if (
                    preg_match('/^\d+$/', $code)
                ) {

                    $currentDescriptionTarget = [
                        'type' => 'module',
                        'module' => $currentModuleIndex,
                    ];

                    if ($inlineDescription !== '') {

                        $modules[$currentModuleIndex]['description']
                            = $inlineDescription;
                    }
                }

                /*
|--------------------------------------------------------------------------
| CHAPTER
|--------------------------------------------------------------------------
*/ elseif (
                    preg_match('/^\d+\.\d+$/', $code)
                ) {

                    $currentDescriptionTarget = [
                        'type' => 'chapter',
                        'module' => $currentModuleIndex,
                        'chapter' => $currentChapterIndex,
                    ];

                    if ($inlineDescription !== '') {

                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['description']
                            = $inlineDescription;
                    }
                }

                /*
|--------------------------------------------------------------------------
| TOPIC
|--------------------------------------------------------------------------
*/ elseif (
                    preg_match('/^\d+\.\d+\.\d+$/', $code)
                ) {

                    $currentDescriptionTarget = [
                        'type' => 'topic',
                        'module' => $currentModuleIndex,
                        'chapter' => $currentChapterIndex,
                        'topic' => $currentTopicIndex,
                    ];

                    if ($inlineDescription !== '') {

                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['description']
                            = $inlineDescription;
                    }
                }

                continue;
            }

            /*
|--------------------------------------------------------------------------
| DESCRIPTION CONTENT
|--------------------------------------------------------------------------
*/

            if ($currentDescriptionTarget !== null) {

                /*
|--------------------------------------------------------------------------
| Stop Description On New Structure
|--------------------------------------------------------------------------
*/

                if (

                    preg_match('/^Module\s+\d+\s*:/i', $text) ||

                    preg_match('/^Chapter\s+\d+(\.\d+)?\s*:/i', $text) ||

                    preg_match('/^Topic\s+\d+\.\d+\.\d+\s*:/i', $text) ||

                    preg_match('/^\d+\.\d+\.\d+\.(H\d+)/i', $text) ||

                    preg_match('/^\d+\.\d+\.\d+\.(C\d+)/i', $text)

                ) {

                    $currentDescriptionTarget = null;
                } else {

                    switch ($currentDescriptionTarget['type']) {

                        case 'module':

                            $existing =
                                $modules[$currentDescriptionTarget['module']]['description'] ?? '';

                            $modules[$currentDescriptionTarget['module']]['description']
                                = trim(
                                    $existing .
                                        "\n" .
                                        $text
                                );

                            break;

                        case 'chapter':

                            $existing =
                                $modules[$currentDescriptionTarget['module']]['chapters'][$currentDescriptionTarget['chapter']]['description'] ?? '';

                            $modules[$currentDescriptionTarget['module']]['chapters'][$currentDescriptionTarget['chapter']]['description']
                                = trim(
                                    $existing .
                                        "\n" .
                                        $text
                                );

                            break;

                        case 'topic':

                            $existing =
                                $modules[$currentDescriptionTarget['module']]['chapters'][$currentDescriptionTarget['chapter']]['topics'][$currentDescriptionTarget['topic']]['description']
                                ?? '';

                            $modules[$currentDescriptionTarget['module']]['chapters'][$currentDescriptionTarget['chapter']]['topics'][$currentDescriptionTarget['topic']]['description']
                                = trim(
                                    $existing .
                                        "\n" .
                                        $text
                                );

                            break;
                    }

                    continue;
                }
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

                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][] = [

                    'topic_code' => $topicCode,

                    'heading_code' => $headingCode,

                    'heading_level' => strtolower($headingCode),

                    'type' => 'text',

                    'title' => $title,

                    'content' => '',
                ];

                $currentContentIndex =
                    count(
                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents']
                    ) - 1;

                continue;
            }

            /*
|--------------------------------------------------------------------------
| STOP CONTENT WHEN ASSESSMENT STARTS
|--------------------------------------------------------------------------
*/

            if (
                preg_match(
                    '/^\d+\.\d+\.\d+\.Q\d+/i',
                    $text
                )
            ) {

                $currentContentIndex = null;

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

                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                        .= $cleanHtml;
                }

                continue;
            }
            /*
    |--------------------------------------------------------------------------
    | IGNORE ASSESSMENT OPTIONS & ANSWERS
    |--------------------------------------------------------------------------
    */

            if (
                preg_match(
                    '/^\d+\.\d+\.\d+\.Q\d+\.(O\d+|A)/i',
                    $text
                )
            ) {

                continue;
            }

            /*
    |--------------------------------------------------------------------------
    | IGNORE ASSESSMENT TITLES
    |--------------------------------------------------------------------------
    */

            if (
                preg_match(
                    '/assessment|mcqs?/i',
                    $text
                )
            ) {

                $currentContentIndex = null;

                continue;
            }
            /*
    |--------------------------------------------------------------------------
    | NORMAL CONTENT
    |--------------------------------------------------------------------------
    */

            if (
                $currentContentIndex !== null
            ) {

                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                    .= $rawHtml;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | VALIDATION
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
