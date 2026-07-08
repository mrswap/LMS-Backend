<?php

namespace App\Modules\Admin\Import\Services;

use Illuminate\Support\Facades\DB;
use App\Jobs\GenerateTopicContentAudioJob;
use App\Models\Chapter;
use App\Models\Module;
use App\Models\Topic;
use App\Models\TopicContent;
use Illuminate\Support\Facades\Log;


class TopicImporterService {

    protected TopicContentAnalyzerService $topicAnalyzer;

    protected TopicPaginatorService $topicPaginator;

    public function __construct(
        TopicContentAnalyzerService $topicAnalyzer,
        TopicPaginatorService $topicPaginator
    ) {

        $this->topicAnalyzer = $topicAnalyzer;

        $this->topicPaginator = $topicPaginator;
    }
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

        DB::transaction(function () use (
            $parsedData,
            $programId,
            $levelId,
            $createdBy
        ) {

            foreach ($parsedData['modules'] as $moduleData) {

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

                    $chapter = Chapter::updateOrCreate(
                        [
                            'program_id' => $programId,
                            'level_id'   => $levelId,
                            'module_id'  => $module->id,
                            'title'      => $chapterData['title'],
                        ],
                        [
                            'status'         => true,
                            'publish_status' => 'published',
                            'description'    => $chapterData['description'] ?? null,
                            'created_by'     => $createdBy,
                        ]
                    );

                    foreach ($chapterData['topics'] as $topicData) {

                        /*
                    |--------------------------------------------------------------------------
                    | Create Topic
                    |--------------------------------------------------------------------------
                    */

                        $topic = Topic::updateOrCreate(
                            [
                                'program_id' => $programId,
                                'level_id'   => $levelId,
                                'module_id'  => $module->id,
                                'chapter_id' => $chapter->id,
                                'title'      => $topicData['title'],
                            ],
                            [
                                'status'          => true,
                                'publish_status'  => 'published',
                                'description'     => null,
                                'created_by'      => $createdBy,
                            ]
                        );

                        /*
                    |--------------------------------------------------------------------------
                    | Delete Old Pages
                    |--------------------------------------------------------------------------
                    */

                        TopicContent::where('topic_id', $topic->id)->delete();

                        /*
                    |--------------------------------------------------------------------------
                    | Analyze Topic
                    |--------------------------------------------------------------------------
                    */

                        Log::info(
                            '[Importer] Analyzing Topic',
                            [
                                'topic' => $topic->title,
                            ]
                        );

                        $analysis = $this->topicAnalyzer
                            ->analyze($topicData);

                        Log::info(
                            '[Importer] Topic Analysis Completed',
                            [
                                'required_pages' => $analysis['required_pages'],
                                'target_per_page' => $analysis['target_per_page'],
                                'total_count' => $analysis['total_count'],
                            ]
                        );

                        /*
                    |--------------------------------------------------------------------------
                    | Generate Pages
                    |--------------------------------------------------------------------------
                    */

                        Log::info(
                            '[Importer] Generating Pages',
                            [
                                'topic' => $topic->title,
                            ]
                        );

                        $pages = $this->topicPaginator
                            ->paginate($analysis);

                        Log::info(
                            '[Importer] Pages Generated',
                            [
                                'pages' => count($pages),
                            ]
                        );

                        /*
                    |--------------------------------------------------------------------------
                    | Insert Pages
                    |--------------------------------------------------------------------------
                    */

                        $order = 1;

                        foreach ($pages as $page) {

                            Log::debug(
                                '[Importer] Saving Page',
                                [

                                    'page' => $page['page_number'],

                                    'title' => $page['title'],

                                ]
                            );

                            $topicContent = TopicContent::create([

                                'topic_id' => $topic->id,

                                'type' => 'text',

                                'title' => $page['title'],

                                'content' => $page['content'],

                                'meta' => [],

                                'order' => $order++,

                                'status' => true,

                                'publish_status' => 'published',

                                'created_by' => $createdBy,

                            ]);


                            Log::debug(
                                '[Importer] Page Saved',
                                [

                                    'id' => $topicContent->id,

                                    'order' => $topicContent->order,

                                ]
                            );

                            $plainText = trim(
                                strip_tags(
                                    $topicContent->content
                                )
                            );

                            if (strlen($plainText) > 100) {

                                GenerateTopicContentAudioJob::dispatch(
                                    $topicContent->id
                                )->onQueue('audio');
                            }
                        }
                    }
                }
            }
        });

        Log::info(
            '[Importer] Import Completed Successfully'
        );
    }
}
