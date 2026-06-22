<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\ImportLog;
use Exception;

class HtmlImportService
{
    public function __construct(

        protected HtmlCleanerService $cleanerService,

        protected HierarchyParserService $hierarchyParserService,

        protected TopicImporterService $topicImporterService,

        protected AssessmentParserService $assessmentParserService,

        protected AssessmentImporterService $assessmentImporterService,
    ) {}

    public function handle(
        ImportLog $import
    ): void {

        /*
        |--------------------------------------------------------------------------
        | STEP 1
        |--------------------------------------------------------------------------
        | CLEAN HTML
        */

        $cleanHtml = $this->cleanerService->clean(
            $import->raw_html
        );

        if (empty(trim($cleanHtml))) {

            throw new Exception(
                'Empty HTML received.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CONTENT IMPORT
        |--------------------------------------------------------------------------
        */

        if (

            in_array(

                $import->type,

                [
                    'content',
                    'all',
                    'both',
                ]

            )
        ) {

            $parsedData =
                $this->hierarchyParserService->parse(
                    $cleanHtml
                );

            logger()->info(
                'CONTENT PARSED',
                [

                    'modules' => count(
                        $parsedData['modules']
                            ?? []
                    ),
                ]
            );

            if (
                ! empty(
                    $parsedData['modules']
                        ?? []
                )
            ) {

                $this->topicImporterService->import(

                    $parsedData,

                    $import->program_id,

                    $import->level_id,

                    $import->created_by
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENT IMPORT
        |--------------------------------------------------------------------------
        */

        if (

            in_array(

                $import->type,

                [
                    'quiz',
                    'exam',
                    'all',
                    'both',
                ]

            )
        ) {

            $assessmentData =
                $this->assessmentParserService->parse(
                    $cleanHtml
                );

            logger()->info(
                'ASSESSMENT PARSED',
                [

                    'questions_count' => count(
                        $assessmentData['questions']
                            ?? []
                    ),

                    'checklists_count' => count(
                        $assessmentData['checklists']
                            ?? []
                    ),

                    'sample_question' => $assessmentData['questions'][0]
                        ?? null,
                ]
            );

            $hasAssessments =

                ! empty(
                    $assessmentData['questions']
                        ?? []
                )

                ||

                ! empty(
                    $assessmentData['checklists']
                        ?? []
                );

            if ($hasAssessments) {

                $this->assessmentImporterService->import(
                    $assessmentData
                );
            }
        }
    }
}
