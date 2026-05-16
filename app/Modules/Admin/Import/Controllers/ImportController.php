<?php

namespace App\Modules\Admin\Import\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Modules\Admin\Import\Jobs\ProcessHtmlImportJob;
use App\Modules\Admin\Import\Requests\ImportContentRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Import HTML Content
    |--------------------------------------------------------------------------
    */

    public function import(
        ImportContentRequest $request
    ): JsonResponse {

        try {

            /*
            |------------------------------------------------------------------
            | Create Import Log
            |------------------------------------------------------------------
            */

            $import = ImportLog::create([

                'program_id' => $request->getProgramId(),

                'level_id' => $request->getLevelId(),

                'raw_html' => $request->getHtml(),

                'status' => 'pending',

                'meta' => [
                    'source' => 'word_html_paste',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],

                'created_by' => auth()->id(),
            ]);

            /*
            |------------------------------------------------------------------
            | Dispatch Job
            |------------------------------------------------------------------
            */

            ProcessHtmlImportJob::dispatch(
                $import->id
            );

            return response()->json([

                'success' => true,

                'message' => 'Import started successfully.',

                'data' => [
                    'import_id' => $import->id,
                    'status' => $import->status,
                ]
            ]);
        } catch (Throwable $e) {

            report($e);

            return response()->json([

                'success' => false,

                'message' => 'Failed to start import.',

                'error' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Something went wrong.'
            ], 500);
        }
    }
}
