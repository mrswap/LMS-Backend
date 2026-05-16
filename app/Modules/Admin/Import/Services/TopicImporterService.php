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

        foreach ($parsedData['modules'] as $moduleData) {

            /*
            |--------------------------------------------------------------------------
            | MODULE
            |--------------------------------------------------------------------------
            */

            $module = Module::firstOrCreate(

                [
                    'program_id' => $programId,
                    'level_id' => $levelId,
                    'title' => $moduleData['title'],
                ],

                [
                    'status' => true,
                    'publish_status' => 'published',
                    'created_by' => $createdBy,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | CHAPTERS
            |--------------------------------------------------------------------------
            */

            foreach ($moduleData['chapters'] as $chapterData) {

                $chapter = Chapter::firstOrCreate(

                    [
                        'program_id' => $programId,
                        'level_id' => $levelId,
                        'module_id' => $module->id,
                        'title' => $chapterData['title'],
                    ],

                    [
                        'status' => true,
                        'publish_status' => 'published',
                        'created_by' => $createdBy,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | TOPICS
                |--------------------------------------------------------------------------
                */

                foreach ($chapterData['topics'] as $topicData) {

                    $topic = Topic::firstOrCreate(

                        [
                            'program_id' => $programId,
                            'level_id' => $levelId,
                            'module_id' => $module->id,
                            'chapter_id' => $chapter->id,
                            'title' => $topicData['title'],
                        ],

                        [
                            'status' => true,
                            'publish_status' => 'published',
                            'created_by' => $createdBy,
                        ]
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | CONTENTS
                    |--------------------------------------------------------------------------
                    */

                    $order = 1;

                    foreach ($topicData['contents'] as $content) {

                        $exists =
                            TopicContent::query()

                            ->where(
                                'topic_id',
                                $topic->id
                            )

                            ->where(
                                'title',
                                $content['title'] ?? null
                            )

                            ->exists();

                        if ($exists) {

                            $order++;

                            continue;
                        }

                        TopicContent::create([

                            'topic_id' => $topic->id,

                            'type' => 'text',

                            'title' =>
                            $content['title'] ?? null,

                            'content' =>
                            $content['content'] ?? null,

                            'meta' => [

                                'topic_code' =>
                                $content['topic_code']
                                    ?? null,

                                'heading_code' =>
                                $content['heading_code']
                                    ?? null,

                                'heading_level' =>
                                $content['heading_level']
                                    ?? null,
                            ],

                            'order' => $order++,

                            'status' => true,

                            'publish_status' =>
                            'published',

                            'created_by' =>
                            $createdBy,
                        ]);
                    }
                }
            }
        }
    }
}
