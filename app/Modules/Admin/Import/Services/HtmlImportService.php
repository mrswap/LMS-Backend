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

        $cleanHtml = $this->cleanerService->clean(
            $import->raw_html
        );

        /*
        |--------------------------------------------------------------------------
        | CONTENT IMPORT
        |--------------------------------------------------------------------------
        */

        $parsedData =
            $this->hierarchyParserService
            ->parse($cleanHtml);

        $this->topicImporterService->import(

            $parsedData,

            $import->program_id,

            $import->level_id,

            $import->created_by
        );

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENT IMPORT
        |--------------------------------------------------------------------------
        */

        $questions =
            $this->assessmentParserService
            ->parse($cleanHtml);

        $this->assessmentImporterService
            ->import($questions);
    }
}