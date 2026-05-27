<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\ImportLog;

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
        */

        $cleanHtml = $this->cleanerService->clean(
            $import->raw_html
        );

        /*
        |--------------------------------------------------------------------------
        | STEP 2
        |--------------------------------------------------------------------------
        */

        $parsedData =
            $this->hierarchyParserService->parse(
                $cleanHtml
            );

        /*
        |--------------------------------------------------------------------------
        | STEP 3
        |--------------------------------------------------------------------------
        | IMPORT MODULE/CHAPTER/TOPIC/CONTENTS
        */

        $this->topicImporterService->import(

            $parsedData,

            $import->program_id,

            $import->level_id,

            $import->created_by
        );

        /*
        |--------------------------------------------------------------------------
        | STEP 4
        |--------------------------------------------------------------------------
        | PARSE ASSESSMENTS
        */

        $assessmentData =
            $this->assessmentParserService->parse(
                $cleanHtml
            );

        logger()->info('ASSESSMENT PARSED', [
            'questions_count' =>
                count($assessmentData['questions'] ?? []),

            'checklists_count' =>
                count($assessmentData['checklists'] ?? []),

            'sample' =>
                $assessmentData['questions'][0] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | STEP 5
        |--------------------------------------------------------------------------
        | IMPORT ASSESSMENTS
        */

        $this->assessmentImporterService->import(
            $assessmentData
        );
    }
}