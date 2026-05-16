<?php

namespace App\Modules\Admin\Import\Jobs;

use App\Models\ImportLog;
use App\Modules\Admin\Import\Services\HtmlImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessHtmlImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    public int $importId;

    /*
    |--------------------------------------------------------------------------
    | Create Job Instance
    |--------------------------------------------------------------------------
    */

    public function __construct(
        int $importId
    ) {
        $this->importId = $importId;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Job
    |--------------------------------------------------------------------------
    */

    public function handle(
        HtmlImportService $htmlImportService
    ): void {

        $import = ImportLog::findOrFail(
            $this->importId
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | Mark Processing
            |--------------------------------------------------------------------------
            */

            $import->markProcessing();

            /*
            |--------------------------------------------------------------------------
            | Process Import
            |--------------------------------------------------------------------------
            */

            $htmlImportService->handle(
                $import
            );

            /*
            |--------------------------------------------------------------------------
            | Mark Completed
            |--------------------------------------------------------------------------
            */

            $import->markCompleted();

        } catch (Throwable $e) {

            report($e);

            /*
            |--------------------------------------------------------------------------
            | Mark Failed
            |--------------------------------------------------------------------------
            */

            $import->markFailed(
                $e->getMessage()
            );

            throw $e;
        }
    }
}