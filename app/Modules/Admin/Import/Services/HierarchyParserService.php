<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\Log;
use App\Modules\Admin\Import\Services\Support\HeadingDetector;

class HierarchyParserService {
    // Regex patterns for module/chapter/topic detection (case-insensitive, flexible formats)
    /*
        |--------------------------------------------------------------------------
        | Structure Detection Patterns (V4)
        |--------------------------------------------------------------------------
        |
        | Supports:
        | Module 1
        | Module 1:
        | Module No. 1
        | Chapter 1.1
        | Chapter 1.1:
        | Topic 1.1.1
        | Topic 1.1.1:
        |
        */

    protected const PATTERN_MODULE =
    '/^\s*Module\s*(?:No\.?)?\s*\d+\s*:?\s*/i';

    protected const PATTERN_CHAPTER =
    '/^\s*Chapter\s*(?:No\.?)?\s*\d+(?:\.\d+)*\s*:?\s*/i';

    protected const PATTERN_TOPIC =
    '/^\s*Topic\s*(?:No\.?)?\s*\d+(?:\.\d+)*\s*:?\s*/i';

    // Regex for detecting assessment start
    protected const PATTERN_ASSESSMENT_START = '/\b(assessment|quiz|mcq|self[-\s]assessment)\b/i';
    // Regex for detecting a question line (e.g. "Q1", "Question 1")
    protected const PATTERN_QUESTION = '/^\s*Q\d+/i';

    protected HeadingDetector $headingDetector;

    public function __construct(HeadingDetector $headingDetector) {
        $this->headingDetector = $headingDetector;
    }

    /**
     * Parse the given HTML and build a structured hierarchy of modules, chapters, topics, and contents.
     *
     * @param string $html Raw HTML content from the Word export.
     * @return array Structured array: ['modules' => [ ... ] ].
     * @throws \Exception If no modules are found in the document.
     */
    public function parse(string $html): array {
        // ------------------------------
        // 1. Normalize HTML (preserve structure)
        // ------------------------------

        // Convert to UTF-8 and suppress parsing errors
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        // Get the <body> element
        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            throw new \Exception('Invalid HTML: <body> tag not found.');
        }

        // ------------------------------
        // 2. Flatten DOM into a sequence of block nodes
        // ------------------------------

        $nodes = [];
        $this->flattenNodes($body, $nodes);

        // ------------------------------
        // 3. Initialize parser state
        // ------------------------------
        $modules = [];
        $currentModuleIndex  = null;
        $currentChapterIndex = null;
        $currentTopicIndex   = null;
        $currentContentIndex = null;
        $inAssessmentMode    = false;
        $topicDescriptionMode = false; // true if we are collecting topic description (before first heading)
        $currentHCHeading = null;
        $currentHCTopic = null;

        // ------------------------------
        // 4. Iterate through blocks
        // ------------------------------

        foreach ($nodes as $node) {
            // Convert node to HTML and text
            $rawHtml = trim($dom->saveHTML($node));
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($rawHtml)));


            $text = html_entity_decode($text);

            $text = preg_replace(
                '/^[\s•●·▪◦◆►▶\-*]+/u',
                '',
                $text
            );

            $text = trim($text);

            /*
            |--------------------------------------------------------------------------
            | Preserve Image Blocks
            |--------------------------------------------------------------------------
            |
            | Images are never parsed as headings.
            | They always remain inside the currently open TopicContent.
            |
            */

            if (
                stripos($rawHtml, '<img') !== false
                ||
                stripos($rawHtml, '<figure') !== false
            ) {

                if ($currentContentIndex !== null) {

                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                        .= $rawHtml;
                }

                continue;
            }


            // Skip empty or whitespace-only nodes
            if ($text === '' && $rawHtml === '') {
                continue;
            }

            // Remove common Word bullets from the text
            $text = preg_replace('/^[●•▪◦◆►]+\s*/u', '', $text);
            $text = trim($text);
            if ($text === '') {
                continue;
            }

            // Skip horizontal separators like "-----"
            if (preg_match('/^[_\-]{3,}$/', $text)) {
                continue;
            }

            // ------------------------------
            // 5. Module Detection
            // ------------------------------
            if (preg_match(self::PATTERN_MODULE, $text)) {
                $modules[] = [
                    'title'       => $text,
                    'description' => '',
                    'chapters'    => [],
                ];
                $currentModuleIndex = count($modules) - 1;
                $currentChapterIndex = null;
                $currentTopicIndex = null;
                $currentContentIndex = null;
                $inAssessmentMode = false;
                Log::info('Module detected: ' . $text);
                continue;
            }

            // ------------------------------
            // 6. Chapter Detection
            // ------------------------------
            if (preg_match(self::PATTERN_CHAPTER, $text)) {
                if ($currentModuleIndex === null) {
                    // Chapter before any module: skip
                    continue;
                }
                $modules[$currentModuleIndex]['chapters'][] = [
                    'title'       => $text,
                    'description' => '',
                    'topics'      => [],
                ];
                $currentChapterIndex = count($modules[$currentModuleIndex]['chapters']) - 1;
                $currentTopicIndex = null;
                $currentContentIndex = null;
                $inAssessmentMode = false;
                Log::info('Chapter detected: ' . $text);
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Legacy Topic Detection
            |--------------------------------------------------------------------------
            |
            | Handles:
            |
            | • Topic 5.1.2:
            | •Topic 5.1.2:
            | Topic 5.1.2:
            |
            */

            $normalizedTopicText = preg_replace(
                '/^[\s•●·▪◦◆►▶\-*]+/u',
                '',
                $text
            );

            if (
                preg_match(
                    self::PATTERN_TOPIC,
                    $normalizedTopicText
                )
            ) {

                $text = $normalizedTopicText;
            }

            // ------------------------------
            // 7. Topic Detection
            // ------------------------------
            if (
                preg_match(
                    self::PATTERN_TOPIC,
                    $normalizedTopicText
                )
            ) {
                if ($currentChapterIndex === null) {
                    // Topic before any chapter: skip
                    continue;
                }
                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][] = [
                    'title' => $normalizedTopicText,
                    'description' => '',
                    'contents'    => [],
                ];
                $currentTopicIndex = count(
                    $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics']
                ) - 1;
                $currentContentIndex = null;
                $topicDescriptionMode = true; // start collecting description
                $inAssessmentMode = false;
                Log::info('Topic detected: ' . $text);
                continue;
            }

            // ------------------------------
            // 8. Assessment Start Detection
            // ------------------------------
            if (preg_match(self::PATTERN_ASSESSMENT_START, $text)) {
                // Enter assessment mode: subsequent content is part of assessment, not normal sections
                $currentContentIndex = null;
                $inAssessmentMode = true;
                Log::info('Assessment section starts: ' . $text);
                continue;
            }
            // (Optional) Detect question patterns
            if (preg_match(self::PATTERN_QUESTION, $text)) {
                $currentContentIndex = null;
                $inAssessmentMode = true;
                Log::info('Question detected, entering assessment mode: ' . $text);
                continue;
            }
            // If already in assessment mode, skip normal content
            if ($inAssessmentMode) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | HC Heading Mode (Highest Priority)
            |--------------------------------------------------------------------------
            */

            if ($this->headingDetector->hasHCHeadingPattern($text)) {

                $hc = $this->headingDetector->extractHCHeading($text);

                $text = $hc['title'];

                /*
                |--------------------------------------------------------------
                | Same Heading (H1C2, H1C3...)
                |--------------------------------------------------------------
                */

                if (
                    $currentHCHeading === $hc['heading_no']
                    &&
                    $currentHCTopic === $hc['topic_code']
                ) {

                    if ($currentContentIndex !== null) {

                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                            .= '<p><strong>' . $text . '</strong></p>';
                    }

                    continue;
                }

                /*
                |--------------------------------------------------------------
                | New Heading
                |--------------------------------------------------------------
                */

                $currentHCHeading = $hc['heading_no'];

                $currentHCTopic = $hc['topic_code'];
            }
            // ------------------------------
            // 9. Section (Heading) Detection
            // ------------------------------
            if ($currentTopicIndex !== null && $this->headingDetector->isHeading($rawHtml, $text)) {


                if (
                    $currentContentIndex !== null
                    &&
                    trim(
                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                    ) === ''
                ) {
                    if (
                        $this->looksLikeIntroLine($text)
                    ) {
                        $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                            .= $rawHtml;
                        continue;
                    }
                }
                // New section heading within the current topic
                // Close topic description mode if it was on
                if ($topicDescriptionMode) {
                    $topicDescriptionMode = false;
                }
                // Create a new content block
                $section = [
                    'topic_code'   => null,    // No explicit topic code without H-marker
                    'heading_code' => null,    // No explicit heading code without H-marker
                    'heading_level' => null,    // No heading level code
                    'type'         => 'text',
                    'title'        => $text,
                    'content'      => '',
                ];
                // Append to topic's contents
                $contents = &$modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'];
                $contents[] = $section;
                $currentContentIndex = count($contents) - 1;
                Log::info('Section heading detected: ' . $text);
                continue;
            }

            // ------------------------------
            // 10. Description vs Content
            // ------------------------------
            if ($currentTopicIndex !== null) {
                // We have an open topic
                $topicData = &$modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex];
                if ($topicDescriptionMode) {
                    $topicData['description'] .= $rawHtml;
                    continue;
                }
                // After first section: ensure we have a content block to append to
                if ($currentContentIndex === null) {
                    // Create a fallback "Content" section if none exists
                    $section = [
                        'topic_code'   => null,
                        'heading_code' => null,
                        'heading_level' => null,
                        'type'         => 'text',
                        'title'        => 'Content',
                        'content'      => '',
                    ];
                    $contents = &$modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'];
                    $contents[] = $section;
                    $currentContentIndex = count($contents) - 1;
                    Log::info('Started fallback Content section.');
                }
                // Append HTML of this block to the current content
                $modules[$currentModuleIndex]['chapters'][$currentChapterIndex]['topics'][$currentTopicIndex]['contents'][$currentContentIndex]['content']
                    .= $rawHtml;
                continue;
            }

            // If no current topic, and none of the above conditions match, we ignore the content
            // (Or you could log it for diagnostics)
        }

        // ------------------------------
        // 11. Post-Processing & Validation
        // ------------------------------
        if (empty($modules)) {
            throw new \Exception('No modules detected in the document.');
        }

        return ['modules' => $modules];
    }

    /**
     * Recursively traverse the DOM to collect block-level elements.
     * Adds <p>, <table>, <ul>, <ol>, <figure>, <img> (and standalone text-in-div) to $nodes.
     *
     * @param \DOMNode $node
     * @param array    $nodes
     */
    protected function flattenNodes(\DOMNode $node, array &$nodes): void {
        foreach ($node->childNodes as $child) {
            $name = strtolower($child->nodeName);

            // Determine if child has any block-like descendants
            $hasBlockChild = false;
            if ($child->hasChildNodes()) {
                foreach ($child->childNodes as $grandChild) {
                    if (in_array(strtolower($grandChild->nodeName), [
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
                        'img'
                    ])) {
                        $hasBlockChild = true;
                        break;
                    }
                }
            }

            // If this node is a significant block element, add it
            // If this node is a significant block element

            if (in_array($name, [
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
                'img'
            ])) {

                $text = trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        strip_tags($child->textContent)
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | Ignore Word Header / Footer
                |--------------------------------------------------------------------------
                */

                if (
                    preg_match(
                        '/^Level\s+\d+\s*\|\s*Module\s+\d+\s*:/i',
                        $text
                    )
                ) {
                    continue;
                }

                $nodes[] = $child;
            }
            // If it's a <div> with no block children but with text, treat as a paragraph
            elseif ($name === 'div' && ! $hasBlockChild) {

                $text = trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        strip_tags($child->textContent)
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | Ignore Word Header / Footer
                |--------------------------------------------------------------------------
                */

                if (
                    preg_match(
                        '/^Level\s+\d+\s*\|\s*Module\s+\d+\s*:/i',
                        $text
                    )
                ) {
                    continue;
                }

                if ($text !== '') {
                    $nodes[] = $child;
                }
            }

            // Recurse into children
            if (in_array(
                $name,
                ['table', 'img', 'figure']
            )) {
                continue;
            }

            if ($child->hasChildNodes()) {
                $this->flattenNodes(
                    $child,
                    $nodes
                );
            }
        }
    }

    protected function looksLikeIntroLine(string $text): bool {
        $text = trim($text);

        return preg_match(

            '/^(After|By|Before|Upon|During|This|These|The|In Simple Terms|In medicine|Meaning|Overview|Objectives)/i',

            $text

        ) === 1;
    }
}
