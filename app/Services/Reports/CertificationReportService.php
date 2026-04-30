<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\Certification;

class CertificationReportService
{
    public function getReport(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Certification::query()
            ->with([
                'user:id,name,email,employee_id',
                'program:id,title',
                'level:id,title',
                'topic:id,title'
            ]);

        /*
        |-----------------------------------------
        | 🔍 FILTERS
        |-----------------------------------------
        */

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type); // topic / level
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status); // 1 / 0
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('issued_at', [
                $request->from_date,
                $request->to_date
            ]);
        }

        /*
        |-----------------------------------------
        | 🔽 SORTING
        |-----------------------------------------
        */

        $sortBy = $request->get('sort_by', 'issued_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        /*
        |-----------------------------------------
        | 📄 PAGINATION
        |-----------------------------------------
        */

        $results = $query->paginate($perPage);

        /*
        |-----------------------------------------
        | 🎯 TRANSFORM DATA
        |-----------------------------------------
        */

        $results->getCollection()->transform(function ($item) {

            return [
                'user_name' => $item->user?->name,
                'email' => $item->user?->email,
                'employee_id' => $item->user?->employee_id,

                'program' => $item->program?->title,

                'type' => $item->type, // topic / level

                'level' => $item->level?->title,
                'topic' => $item->topic?->title,

                'certificate_id' => $item->certificate_id,

                'score' => $item->score,
                'percentage' => $item->percentage,

                'certificate_issue_date' => $item->issued_at,

                'certificate_status' => $item->status ? 'Active' : 'Revoked',

                'certificate_file' => $item->file
            ];
        });

        return $results;
    }
}
