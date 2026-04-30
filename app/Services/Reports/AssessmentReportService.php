<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\AssessmentAttempt;

class AssessmentReportService
{
    public function getReport(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = AssessmentAttempt::query()
            ->with([
                'user:id,name,email,employee_id',
                'assessment:id,title,passing_score,assessmentable_id,assessmentable_type',
                'assessment.assessmentable:id,title'
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

        // assessment type filter (topic / level)
        if ($request->filled('type')) {
            $query->whereHas('assessment', function ($q) use ($request) {
                $q->where('type', $request->type);
            });
        }

        // pass / fail filter
        if ($request->filled('status')) {
            if ($request->status === 'passed') {
                $query->where('status', 'passed');
            } elseif ($request->status === 'failed') {
                $query->where('status', 'failed');
            }
        }

        // date filter
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('submitted_at', [
                $request->from_date,
                $request->to_date
            ]);
        }

        /*
        |--------------------------------------------------
        | 🔽 SORTING
        |--------------------------------------------------
        */
        $sortBy = $request->get('sort_by', 'submitted_at');
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

            // answers breakdown
            $answers = $item->answers;

            $correct = $answers->where('is_correct', true)->count();

            $incorrect = $answers->where('is_correct', false)
                ->whereNotNull('selected_option_id')
                ->count();

            $skipped = $answers->whereNull('selected_option_id')->count();

            $totalQuestions = $answers->count();

            // attempt count (same user + same assessment)
            $attemptCount = \App\Models\AssessmentAttempt::where('user_id', $item->user_id)
                ->where('assessment_id', $item->assessment_id)
                ->count();

            return [
                'user_name' => $item->user?->name,
                'email' => $item->user?->email,
                'employee_id' => $item->user?->employee_id,

                'assessment_name' => $item->assessment?->title,

                // dynamic relation (topic or level)
                'related_name' => $item->assessment?->assessmentable?->title,

                'attempt_date' => $item->submitted_at,

                'score' => $item->score,
                'percentage' => $item->percentage,
                'passing_score' => $item->assessment?->passing_score,

                'status' => $item->status, // passed / failed

                'attempt_count' => $attemptCount,

                'total_questions' => $totalQuestions,
                'correct_answers' => $correct,
                'incorrect_answers' => $incorrect,
                'skipped' => $skipped,
            ];
        });

        return $results;
    }
}
