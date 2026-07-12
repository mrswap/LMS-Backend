<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\ImportLog;
use Exception;
use App\Modules\Admin\Import\DTO\AssessmentImportDTO;

class HtmlImportService {
    public function __construct(

        protected HtmlCleanerService $cleanerService,

        protected HierarchyParserService $hierarchyParserService,

        protected TopicImporterService $topicImporterService,

        protected OpenAIAssessmentParserService $openAIAssessmentParserService,

        protected AssessmentImporterService $assessmentImporterService,

        protected AssessmentMatchingService $assessmentMatchingService
    ) {
    }

    public function handle(
        ImportLog $import
    ): void {

        $startTime = microtime(true);

        logger()->info(
            '[Import] Started',
            [
                'import_id' => $import->id,
                'program_id' => $import->program_id,
                'level_id' => $import->level_id,
                'type' => $import->type,
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | STEP 1 : Clean HTML
    |--------------------------------------------------------------------------
    */

        $cleanHtml = $this->cleanerService->clean(
            $import->raw_html
        );

        if (blank($cleanHtml)) {

            throw new Exception(
                'Empty HTML received after cleaning.'
            );
        }

        logger()->info(
            '[Import] HTML Cleaned',
            [
                'original_length' => strlen($import->raw_html),
                'clean_length' => strlen($cleanHtml),
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | STEP 2 : Content Import
    |--------------------------------------------------------------------------
    */

        if (

            in_array(

                strtolower($import->type),

                [
                    'content',
                    'all',
                ]

            )

        ) {

            logger()->info(
                '[Content] Parsing Started'
            );

            $parsedData = $this->hierarchyParserService
                ->parse($cleanHtml);

            logger()->info(
                '[Content] Parsing Completed',
                [

                    'modules' => count(
                        $parsedData['modules'] ?? []
                    ),

                ]
            );

            if (

                !empty($parsedData['modules'] ?? [])

            ) {

                logger()->info(
                    '[Content] Database Import Started'
                );

                $this->topicImporterService->import(

                    $parsedData,

                    $import->program_id,

                    $import->level_id,

                    $import->created_by

                );

                logger()->info(
                    '[Content] Database Import Completed'
                );
            } else {

                logger()->warning(
                    '[Content] No Modules Detected'
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | STEP 3 : Assessment Import
    |--------------------------------------------------------------------------
    */

        if (

            in_array(

                strtolower($import->type),

                [
                    'quiz',
                    'exam',
                    'both',
                ]

            )

        ) {

            logger()->info(
                '[Assessment] Parsing Started'
            );

            $assessmentData =
                $this->openAIAssessmentParserService
                ->parse(
                    html: $cleanHtml,
                    importId: $import->id
                );



            logger()->info(
                '[Assessment] Parsing Completed',
                [

                    'module_questions' =>
                    $assessmentData->totalModuleQuestions(),

                    'topic_questions' =>
                    $assessmentData->totalTopicQuestions(),

                    'topics' =>
                    $assessmentData->totalTopics(),

                    'total_questions' =>
                    $assessmentData->totalQuestions(),

                ]
            );

            $hasAssessments =

                $assessmentData->hasModuleAssessment()

                ||

                $assessmentData->hasTopicAssessments();

            if ($hasAssessments) {

                logger()->info(
                    '[Assessment] Database Import Started'
                );

                $this->assessmentImporterService->import(

                    dto: $assessmentData,

                    programId: $import->program_id,

                    levelId: $import->level_id,

                    moduleId: (int) data_get(
                        $import->meta,
                        'module_id',
                        0
                    ),

                    createdBy: $import->created_by

                );

                logger()->info(
                    '[Assessment] Database Import Completed'
                );
            } else {

                logger()->warning(
                    '[Assessment] No Assessment Found'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FINISH
        |--------------------------------------------------------------------------
        */

        logger()->info(
            '[Import] Completed',
            [

                'import_id' => $import->id,

                'execution_time' => round(
                    microtime(true) - $startTime,
                    3
                ) . ' sec',

                'memory_usage' => round(
                    memory_get_peak_usage(true) / 1024 / 1024,
                    2
                ) . ' MB',

            ]
        );
    }
}
