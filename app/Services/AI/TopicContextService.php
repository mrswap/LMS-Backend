<?php

namespace App\Services\AI;

use App\Models\Topic;
use App\Models\TopicAiContext;
use Illuminate\Support\Facades\Log;

class TopicContextService
{
    /*
    |------------------------------------------------------------------
    | BUILD CONTEXT
    |------------------------------------------------------------------
    */

    public function build(
        Topic $topic
    ): string {

        Log::channel('ai')->info(
            'Building Topic Context',
            [
                'topic_id' => $topic->id,
            ]
        );

        /*
        |------------------------------------------------------------------
        | LOAD RELATIONS
        |------------------------------------------------------------------
        */

        $topic->loadMissing([

            'program',
            'level',
            'module',
            'chapter',
            'contents',
        ]);

        /*
        |------------------------------------------------------------------
        | HTML CLEANER
        |------------------------------------------------------------------
        */

        $htmlCleaner = app(
            HtmlToTextService::class
        );

        /*
        |------------------------------------------------------------------
        | CONTEXT ARRAY
        |------------------------------------------------------------------
        */

        $context = [];

        /*
        |------------------------------------------------------------------
        | HIERARCHY
        |------------------------------------------------------------------
        */

        $context[] =
            "Program: "
            . ($topic->program?->title ?? '');

        $context[] =
            "Level: "
            . ($topic->level?->title ?? '');

        $context[] =
            "Module: "
            . ($topic->module?->title ?? '');

        $context[] =
            "Chapter: "
            . ($topic->chapter?->title ?? '');

        $context[] =
            "Topic: "
            . ($topic->title ?? '');

        /*
        |------------------------------------------------------------------
        | TOPIC DESCRIPTION
        |------------------------------------------------------------------
        */

        if ($topic->description) {

            $context[] =
                "Topic Description: "
                . $htmlCleaner->clean(
                    $topic->description
                );
        }

        /*
        |------------------------------------------------------------------
        | TOPIC CONTENTS
        |------------------------------------------------------------------
        */

        foreach ($topic->contents as $content) {

            /*
            |--------------------------------------------------------------
            | ONLY PUBLISHED CONTENT
            |--------------------------------------------------------------
            */

            if (
                method_exists(
                    $content,
                    'isPublished'
                )
                && ! $content->isPublished()
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------
            | CLEAN CONTENT
            |--------------------------------------------------------------
            */

            $cleanedContent =
                $htmlCleaner->clean(
                    $content->content
                );

            /*
            |--------------------------------------------------------------
            | SKIP EMPTY
            |--------------------------------------------------------------
            */

            if (! $cleanedContent) {
                continue;
            }

            /*
            |--------------------------------------------------------------
            | CONTENT TITLE
            |--------------------------------------------------------------
            */

            if ($content->title) {

                $context[] =
                    "Heading: "
                    . $content->title;
            }

            /*
            |--------------------------------------------------------------
            | CONTENT BODY
            |--------------------------------------------------------------
            */

            $context[] = $cleanedContent;
        }

        /*
        |------------------------------------------------------------------
        | FINAL CONTEXT
        |------------------------------------------------------------------
        */

        $finalContext = implode(
            "\n\n",
            $context
        );

        /*
        |------------------------------------------------------------------
        | LIMIT CONTEXT SIZE
        |------------------------------------------------------------------
        */

        $finalContext = substr(

            $finalContext,

            0,

            config(
                'ai.max_context_chars'
            )
        );

        /*
        |------------------------------------------------------------------
        | LOG SIZE
        |------------------------------------------------------------------
        */

        Log::channel('ai')->info(
            'Topic Context Built',
            [
                'topic_id' => $topic->id,
                'length' => strlen($finalContext),
            ]
        );

        return trim($finalContext);
    }

    /*
    |------------------------------------------------------------------
    | CACHE CONTEXT
    |------------------------------------------------------------------
    */

    public function cache(
        Topic $topic
    ): TopicAiContext {

        $context = $this->build($topic);

        Log::channel('ai')->info(
            'Caching Topic Context',
            [
                'topic_id' => $topic->id,
                'length' => strlen($context),
            ]
        );

        return TopicAiContext::updateOrCreate(

            [
                'topic_id' => $topic->id,
            ],

            [
                'context' => $context,

                'context_length' =>
                strlen($context),

                'generated_at' =>
                now(),
            ]
        );
    }
}
