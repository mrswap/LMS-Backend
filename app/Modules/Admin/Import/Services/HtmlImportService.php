<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\ImportLog;

class HtmlImportService
{
    public function __construct(
        protected HtmlCleanerService $cleanerService,
        protected HierarchyParserService $hierarchyParserService,
        protected TopicImporterService $topicImporterService,
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

        $parsedData = $this->hierarchyParserService->parse(
            $cleanHtml
        );

        /*
        |--------------------------------------------------------------------------
        | STEP 3
        |--------------------------------------------------------------------------
        */

        $this->topicImporterService->import(

            $parsedData,

            $import->program_id,

            $import->level_id,

            $import->created_by
        );
    }
}