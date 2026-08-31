<?php

namespace App\Modules\Admin\Import\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class SmartHierarchyParserService
{
    /*
    |--------------------------------------------------------------------------
    | Parse Smart Hierarchy
    |--------------------------------------------------------------------------
    */

    public function parse(string $html): array
    {
        /*
        |--------------------------------------------------------------------------
        | Preserve Breaks
        |--------------------------------------------------------------------------
        */

        $html = preg_replace('/<\/p>/i', "</p>\n", $html);

        $html = preg_replace('/<\/div>/i', "</div>\n", $html);

        $html = preg_replace('/<\/table>/i', "</table>\n", $html);

        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        /*
|--------------------------------------------------------------------------
| DOM
|--------------------------------------------------------------------------
*/

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
| Storage
|--------------------------------------------------------------------------
*/

        $modules = [];

        $currentModuleIndex = null;

        $currentChapterIndex = null;

        $currentTopicIndex = null;

        $currentContentIndex = null;

        $currentDescriptionTarget = null;

        $headingCounter = 0;

        /*
|--------------------------------------------------------------------------
| Loop Nodes
|--------------------------------------------------------------------------
*/

        foreach ($body->childNodes as $node) {

            $rawHtml = trim(
                $dom->saveHTML($node)
            );

            $text = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags($rawHtml)
                )
            );

            if (empty($text)) {
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

                if ($currentModuleIndex === null) {
                    continue;
                }

                $modules[$currentModuleIndex]['chapters'][] = [

                    'title' => $text,

                    'description' => null,

                    'topics' => [],
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

                if ($currentChapterIndex === null) {
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

                $headingCounter = 0;

                continue;
            }

            /*
|--------------------------------------------------------------------------
| DESCRIPTION
|--------------------------------------------------------------------------
*/

            if (
                preg_match(
                    '/^(\d+(?:\.\d+){0,2})\.D\s*(.*)$/i',
                    $text,
                    $matches
                )
            ) {

                $code = trim($matches[1]);

                $description =
                    trim($matches[2] ?? '');

                /*
|--------------------------------------------------------------------------
| MODULE DESCRIPTION
|--------------------------------------------------------------------------
*/

                if (
                    preg_match('/^\d+$/', $code)
                ) {

                    $modules[$currentModuleIndex]['description'] = $description;

                    continue;
                }

                /*
|--------------------------------------------------------------------------
| CHAPTER DESCRIPTION
|--------------------------------------------------------------------------
*/

                if (
                    preg_match('/^\d+\.\d+$/', $code)
                ) {

                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['description'] = $description;

                    continue;
                }

                /*
|--------------------------------------------------------------------------
| TOPIC DESCRIPTION
|--------------------------------------------------------------------------
*/

                if (
                    preg_match(
                        '/^\d+\.\d+\.\d+$/',
                        $code
                    )
                ) {

                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['description'] =
                        $description;

                    continue;
                }
            }

            /*
|--------------------------------------------------------------------------
| HEADING DETECTION
|--------------------------------------------------------------------------
*/

            if (
                $currentTopicIndex !== null
                &&
                $this->isHeading($node, $text)
            ) {

                $headingCounter++;

                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][] = [

                    'type' => 'text',

                    'title' => $text,

                    'heading_code' =>
                    'H' . $headingCounter,

                    'heading_level' => 'h2',

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

    /*
|--------------------------------------------------------------------------
| Detect Heading
|--------------------------------------------------------------------------
*/
    private function isHeading(
        DOMNode $node,
        string $text
    ): bool {

        /*
|--------------------------------------------------------------------------
| Ignore Structural Labels
|--------------------------------------------------------------------------
*/

        if (
            preg_match('/^Module\s+/i', $text)
            ||
            preg_match('/^Chapter\s+/i', $text)
            ||
            preg_match('/^Topic\s+/i', $text)
        ) {

            return false;
        }

        /*
|--------------------------------------------------------------------------
| Ignore Descriptions
|--------------------------------------------------------------------------
*/

        if (
            preg_match(
                '/^\d+(?:\.\d+){0,2}\.D/i',
                $text
            )
        ) {

            return false;
        }

        /*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

        if (empty(trim($text))) {

            return false;
        }

        /*
|--------------------------------------------------------------------------
| Native Heading Tags
|--------------------------------------------------------------------------
*/

        if ($node instanceof DOMElement) {

            $tag =
                strtolower(
                    $node->tagName
                );

            if (
                in_array(
                    $tag,
                    [
                        'h1',
                        'h2',
                        'h3',
                        'h4',
                        'h5',
                        'h6'
                    ]
                )
            ) {

                return true;
            }
        }

        /*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

        $html =
            trim(
                $node
                    ->ownerDocument
                    ->saveHTML($node)
            );

        /*
|--------------------------------------------------------------------------
| Bold-only Paragraph
|--------------------------------------------------------------------------
*/

        if (
            preg_match(
                '/^\s*<(p|div)>\s*(<strong>|<b>).*(<\ /strong>|<\ /b>)\s*<\ /(p|div)>\s*$/is',
                $html
            )
        ) {

            /*
                        |--------------------------------------------------------------------------
                        | Avoid Huge Bold Paragraphs
                        |--------------------------------------------------------------------------
                        */

            if (
                mb_strlen($text) <= 120
            ) {
                return true;
            }
        } /*
                            |-------------------------------------------------------------------------- | Standalone
                            Short Line |-------------------------------------------------------------------------- */
        if (
            mb_strlen($text) <= 60 && !preg_match('/[\.:\;\?\!]$/', $text) && str_word_count($text) <= 8
        ) {
            return true;
        }
        return false;
    }
}
