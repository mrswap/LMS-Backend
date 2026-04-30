<?php

namespace App\Services\Reports;

use App\Models\UserProgress;
use Illuminate\Http\Request;

class UserProgressReportService
{
    public function getReport(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = UserProgress::query()
            ->with([
                'user:id,name,email,employee_id,role_id',
                'user.role:id,name',
                'program:id,title',
                'level:id,title',
                'module:id,title',
                'chapter:id,title',
                'topic:id,title',
            ]);

        /*
        |--------------------------------------------------
        | 🔍 FILTERS
        |--------------------------------------------------
        */

        // user filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // program filter
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // level filter
        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        // module filter
        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        // topic filter
        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        // status filter
        if ($request->filled('status')) {
            if ($request->status === 'completed') {
                $query->where('is_completed', true);
            } elseif ($request->status === 'in_progress') {
                $query->where('is_unlocked', true)
                    ->where('is_completed', false);
            } elseif ($request->status === 'not_started') {
                $query->where('is_unlocked', false);
            }
        }

        // date range filter
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('updated_at', [
                $request->from_date,
                $request->to_date
            ]);
        }

        /*
        |--------------------------------------------------
        | 🔽 SORTING
        |--------------------------------------------------
        */
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        /*
        |--------------------------------------------------
        | 📄 PAGINATION
        |--------------------------------------------------
        */
        $results = $query->paginate($perPage);

        /*
        |--------------------------------------------------
        | 🎯 TRANSFORM DATA
        |--------------------------------------------------
        */
        $results->getCollection()->transform(function ($item) {

            // completion status
            if ($item->is_completed) {
                $status = 'Completed';
                $percentage = 100;
            } elseif ($item->is_unlocked) {
                $status = 'In Progress';
                $percentage = 50; // 🔥 can improve later with real calc
            } else {
                $status = 'Not Started';
                $percentage = 0;
            }

            return [
                'user_name' => $item->user?->name,
                'email' => $item->user?->email,
                'employee_id' => $item->user?->employee_id,
                'role' => $item->user?->role?->name,

                'program' => $item->program?->title,
                'level' => $item->level?->title,
                'module' => $item->module?->title,
                'chapter' => $item->chapter?->title,
                'topic' => $item->topic?->title,

                'completion_status' => $status,
                'completion_percentage' => $percentage,

                'last_activity_date' => $item->updated_at,
                'completed_at' => $item->completed_at,

                'is_completed' => (bool) $item->is_completed,
                'is_unlocked' => (bool) $item->is_unlocked,
            ];
        });

        return $results;
    }
}
