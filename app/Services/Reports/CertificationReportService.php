<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\Certification;

class CertificationReportService
{
    public function getReport(Request $request, $userId = null)
    {
        $perPage = $request->get('per_page', 10);

        $query = Certification::query()
            ->where('status', true)
            ->with([

                'user:id,name,email,employee_id',

                'program:id,title',

                'level:id,title',

                'module:id,title',

                'chapter:id,title',

                'topic:id,title'
            ]);

        /*
        |--------------------------------------------------
        | 🔥 FORCE USER FILTER (TRAINEE)
        |--------------------------------------------------
        */
        if ($userId) {

            $query->where('user_id', $userId);
        }

        /*
        |--------------------------------------------------
        | FILTERS
        |--------------------------------------------------
        */

        if (
            !$userId &&
            $request->filled('user_id')
        ) {

            $query->where(
                'user_id',
                $request->user_id
            );
        }

        if ($request->filled('program_id')) {

            $query->where(
                'program_id',
                $request->program_id
            );
        }

        if ($request->filled('level_id')) {

            $query->where(
                'level_id',
                $request->level_id
            );
        }

        if ($request->filled('module_id')) {

            $query->where(
                'module_id',
                $request->module_id
            );
        }

        if ($request->filled('chapter_id')) {

            $query->where(
                'chapter_id',
                $request->chapter_id
            );
        }

        if ($request->filled('topic_id')) {

            $query->where(
                'topic_id',
                $request->topic_id
            );
        }

        if ($request->filled('type')) {

            $query->where(
                'type',
                $request->type
            );
        }

        if ($request->filled('status')) {

            $query->where(
                'status',
                $request->status
            );
        }

        if (
            $request->filled('from_date') &&
            $request->filled('to_date')
        ) {

            $query->whereBetween('issued_at', [

                $request->from_date,
                $request->to_date
            ]);
        }

        /*
        |--------------------------------------------------
        | SORTING
        |--------------------------------------------------
        */

        $sortBy = $request->get(
            'sort_by',
            'issued_at'
        );

        $sortOrder = $request->get(
            'sort_order',
            'desc'
        );

        $query->orderBy(
            $sortBy,
            $sortOrder
        );

        /*
        |--------------------------------------------------
        | PAGINATION
        |--------------------------------------------------
        */

        $results = $query->paginate($perPage);

        /*
        |--------------------------------------------------
        | TRANSFORM
        |--------------------------------------------------
        */

        $results->getCollection()->transform(function (
            $item
        ) {

            return [

                /*
                |--------------------------------------------------
                | USER
                |--------------------------------------------------
                */

                'user_name' => $item->user?->name,

                'email' => $item->user?->email,

                'employee_id' => $item->user?->employee_id,

                /*
                |--------------------------------------------------
                | PROGRAM
                |--------------------------------------------------
                */

                'program' => $item->program?->title,

                /*
                |--------------------------------------------------
                | TYPE
                |--------------------------------------------------
                */

                'type' => $item->type,

                /*
                |--------------------------------------------------
                | HIERARCHY
                |--------------------------------------------------
                |
                | RESPONSE STRUCTURE SAME RAKHA HAI
                | Sirf module/chapter add hua hai
                |
                */

                'level' => $item->level?->title,

                'module' => $item->module?->title,

                'chapter' => $item->chapter?->title,

                'topic' => $item->topic?->title,

                /*
                |--------------------------------------------------
                | CERTIFICATE
                |--------------------------------------------------
                */

                'certificate_id' => $item->certificate_id,

                'passed_attempt_id' => $item->assessment_attempt_id,

                /*
                |--------------------------------------------------
                | SCORE
                |--------------------------------------------------
                */

                'score' => $item->score,

                'percentage' => $item->percentage,

                /*
                |--------------------------------------------------
                | DATES
                |--------------------------------------------------
                */

                'certificate_issue_date' => $item->issued_at,

                /*
                |--------------------------------------------------
                | STATUS
                |--------------------------------------------------
                */

                'certificate_status' => $item->status
                    ? 'Active'
                    : 'Revoked',

                /*
                |--------------------------------------------------
                | FILE
                |--------------------------------------------------
                */

                'certificate_file' => $item->file
            ];
        });

        return $results;
    }
}
