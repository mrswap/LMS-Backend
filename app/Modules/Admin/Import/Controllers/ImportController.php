<?php

namespace App\Modules\Admin\Import\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Modules\Admin\Import\Jobs\ProcessHtmlImportJob;
use App\Modules\Admin\Import\Requests\ImportContentRequest;
use Illuminate\Http\JsonResponse;
use Throwable;
use Illuminate\Http\Request;

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




    /*
    |--------------------------------------------------------------------------
    | Import Logs Listing
    |--------------------------------------------------------------------------
    */

    public function logs(Request $request)
    {
        $query = ImportLog::query()

            ->with([
                'program:id,title',
                'level:id,title',
                'creator:id,name'
            ]);

        /*
        |--------------------------------------------------------------------------
        | FILTER : LEVEL
        |--------------------------------------------------------------------------
        */

        if ($request->filled('level_id')) {

            $query->where(
                'level_id',
                $request->level_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER : STATUS
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {

            $query->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->whereHas('program', function ($q2) use ($search) {

                    $q2->where(
                        'title',
                        'LIKE',
                        "%{$search}%"
                    );
                })

                    ->orWhereHas('level', function ($q2) use ($search) {

                        $q2->where(
                            'title',
                            'LIKE',
                            "%{$search}%"
                        );
                    })

                    ->orWhere(
                        'status',
                        'LIKE',
                        "%{$search}%"
                    )

                    ->orWhere(
                        'error_message',
                        'LIKE',
                        "%{$search}%"
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

        $sortBy = $request->get(
            'sortBy',
            'created_at'
        );

        $order = strtolower(
            $request->get('order', 'desc')
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [

            'id',
            'status',
            'created_at',
            'updated_at',
        ];

        if (!in_array($sortBy, $allowedSorts)) {

            $sortBy = 'created_at';
        }

        $query->orderBy(
            $sortBy,
            $order
        );

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $limit = (int) $request->get('limit', 10);

        $limit = $limit > 0 && $limit <= 100
            ? $limit
            : 10;

        $logs = $query->paginate($limit);

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM
        |--------------------------------------------------------------------------
        */

        $logs->getCollection()->transform(function ($log) {

            return [

                'id' => $log->id,

                'program' => $log->program
                    ? [
                        'id' => $log->program->id,
                        'title' => $log->program->title,
                    ]
                    : null,

                'level' => $log->level
                    ? [
                        'id' => $log->level->id,
                        'title' => $log->level->title,
                    ]
                    : null,

                'status' => $log->status,

                'error_message' => $log->error_message,

                'meta' => $log->meta,

                'created_by' => $log->creator
                    ? [
                        'id' => $log->creator->id,
                        'name' => $log->creator->name,
                    ]
                    : null,

                'created_at' => $log->created_at,

                'updated_at' => $log->updated_at,
            ];
        });

        return response()->json([

            'success' => true,

            'data' => $logs
        ]);
    }
}
