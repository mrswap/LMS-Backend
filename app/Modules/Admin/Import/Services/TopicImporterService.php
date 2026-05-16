<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Chapter;
use App\Models\Module;
use App\Models\Topic;
use App\Models\TopicContent;

class TopicImporterService
{
    /*
    |--------------------------------------------------------------------------
    | Import
    |--------------------------------------------------------------------------
    */

    public function import(
        array $parsedData,
        int $programId,
        int $levelId,
        int $createdBy
    ): void {

        foreach ($parsedData['modules'] as $moduleIndex => $moduleData) {

            /*
            |--------------------------------------------------------------------------
            | MODULE
            |--------------------------------------------------------------------------
            */

            $module = Module::create([

                'program_id' => $programId,

                'level_id' => $levelId,

                'title' => $moduleData['title'],

                'status' => true,

                'publish_status' => 'published',

                'created_by' => $createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CHAPTERS
            |--------------------------------------------------------------------------
            */

            foreach ($moduleData['chapters'] as $chapterIndex => $chapterData) {

                $chapter = Chapter::create([

                    'program_id' => $programId,

                    'level_id' => $levelId,

                    'module_id' => $module->id,

                    'title' => $chapterData['title'],

                    'status' => true,

                    'publish_status' => 'published',

                    'created_by' => $createdBy,
                ]);

                /*
                |--------------------------------------------------------------------------
                | TOPICS
                |--------------------------------------------------------------------------
                */

                foreach ($chapterData['topics'] as $topicIndex => $topicData) {

                    $topic = Topic::create([

                        'program_id' => $programId,

                        'level_id' => $levelId,

                        'module_id' => $module->id,

                        'chapter_id' => $chapter->id,

                        'title' => $topicData['title'],

                        'status' => true,

                        'publish_status' => 'published',

                        'created_by' => $createdBy,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | TOPIC CONTENTS
                    |--------------------------------------------------------------------------
                    */

                    $order = 1;

                    foreach ($topicData['contents'] as $content) {

                        TopicContent::create([

                            'topic_id' => $topic->id,

                            /*
                            |--------------------------------------------------------------------------
                            | IMPORTANT
                            |--------------------------------------------------------------------------
                            |
                            | DB type = text
                            |
                            */

                            'type' => 'text',

                            'title' => $content['title'] ?? null,

                            'content' => $content['content'] ?? null,

                            'meta' => [

                                'topic_code' =>
                                    $content['topic_code'] ?? null,

                                'heading_code' =>
                                    $content['heading_code'] ?? null,

                                'heading_level' =>
                                    $content['heading_level'] ?? null,
                            ],

                            'order' => $order++,

                            'status' => true,

                            'publish_status' => 'published',

                            'created_by' => $createdBy,
                        ]);
                    }
                }
            }
        }
    }
}