<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\DB;
use App\Jobs\GenerateTopicContentAudioJob;
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
        // पूरे import को transaction में चलाएं
        DB::transaction(function () use ($parsedData, $programId, $levelId, $createdBy) {

            foreach ($parsedData['modules'] as $moduleData) {

                // Module बनाना या अपडेट करना
                $module = Module::updateOrCreate(
                    [
                        'program_id' => $programId,
                        'level_id'   => $levelId,
                        'title'      => $moduleData['title'],
                    ],
                    [
                        'status'         => true,
                        'publish_status' => 'published',
                        'description'    => $moduleData['description'] ?? null,
                        'created_by'     => $createdBy,
                    ]
                );

                foreach ($moduleData['chapters'] as $chapterData) {

                    // Chapter बनाना या अपडेट करना
                    $chapter = Chapter::updateOrCreate(
                        [
                            'program_id' => $programId,
                            'level_id'   => $levelId,
                            'module_id'  => $module->id,
                            'title'      => $chapterData['title'],
                        ],
                        [
                            'status'         => true,
                            'description'    => $chapterData['description'] ?? null,
                            'publish_status' => 'published',
                            'created_by'     => $createdBy,
                        ]
                    );

                    foreach ($chapterData['topics'] as $topicData) {

                        // Topic बनाना या अपडेट करना
                        $topic = Topic::updateOrCreate(
                            [
                                'program_id' => $programId,
                                'level_id'   => $levelId,
                                'module_id'  => $module->id,
                                'chapter_id' => $chapter->id,
                                'title'      => $topicData['title'],
                            ],
                            [
                                'status'         => true,
                                'description'    => $topicData['description'] ?? null,
                                'publish_status' => 'published',
                                'created_by'     => $createdBy,
                            ]
                        );

                        // पुराने TopicContent हटा दें (re-import safety)
                        TopicContent::where('topic_id', $topic->id)->delete();

                        // नए contents insert करना शुरू करें
                        $order = 1;
                        foreach ($topicData['contents'] ?? [] as $content) {

                            $topicContent = TopicContent::create([
                                'topic_id'     => $topic->id,
                                'type'         => $content['type'] ?? 'text',
                                'title'        => $content['title'] ?? null,
                                'content'      => $content['content'] ?? null,
                                'meta'         => [],
                                'order'        => $order++,
                                'status'       => true,
                                'publish_status'=> 'published',
                                'created_by'   => $createdBy,

                                // Optional codes (nullable) - यदि उपलब्ध हों तो store करें
                                'topic_code'   => $content['topic_code'] ?? null,
                                'heading_code' => $content['heading_code'] ?? null,
                                'heading_level'=> $content['heading_level'] ?? null,
                            ]);

                            // अगर लंबा टेक्स्ट है तो audio generate करें
                            $plainText = trim(strip_tags($topicContent->content));
                            if (strlen($plainText) > 100) {
                                GenerateTopicContentAudioJob::dispatch(
                                    $topicContent->id
                                )->onQueue('audio');
                            }
                        }
                    }
                }
            }

        }); // end transaction
    }
}
