<?php

namespace App\Services\Reports;

use App\Models\UserProgress;
use Illuminate\Http\Request;

class UserProgressReportService
{
    public function getReport(Request $request, $userId = null)
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

        // 🔥 FORCE USER FILTER (trainee)
        if ($userId) {
            $query->where('user_id', $userId);
        }
        /*
        |--------------------------------------------------
        | ❌ SKIP INVALID HIERARCHY (NULL IDs)
        |--------------------------------------------------
        */
        $query->whereNotNull('program_id')
            ->whereNotNull('level_id')
            ->whereNotNull('module_id')
            ->whereNotNull('chapter_id')
            ->whereNotNull('topic_id');
        /*
        |-----------------------------------------
        | FILTERS
        |-----------------------------------------
        */

        // admin only filter
        if (!$userId && $request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

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

        if ($request->filled('search') && !$userId) {
            $search = $request->search;

            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('updated_at', [
                $request->from_date,
                $request->to_date
            ]);
        }

        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $results = $query->paginate($perPage);

        $results->getCollection()->transform(function ($item) {

            if ($item->is_completed) {
                $status = 'Completed';
                $percentage = 100;
            } elseif ($item->is_unlocked) {
                $status = 'In Progress';
                $percentage = 50;
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
