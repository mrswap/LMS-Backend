<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\AssessmentAttempt;

class AssessmentReportService
{
    public function getReport(Request $request, $userId = null)
    {
        $perPage = $request->get('per_page', 10);

        /*
        |--------------------------------------------------
        | BASE QUERY
        |--------------------------------------------------
        */
        $query = AssessmentAttempt::query()
            ->with([
                'user:id,name,email,employee_id',
                'assessment:id,title,passing_score,assessmentable_id,assessmentable_type,type',
                'assessment.assessmentable:id,title',
                'answers:id,attempt_id,selected_option_id,is_correct'
            ]);

        /*
        |--------------------------------------------------
        | 🔐 USER SCOPING (IMPORTANT)
        |--------------------------------------------------
        */
        if ($userId) {
            $query->where('user_id', $userId);
        }

        /*
        |--------------------------------------------------
        | 🔍 FILTERS
        |--------------------------------------------------
        */

        // admin only
        if (!$userId && $request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('type')) {
            $query->whereHas('assessment', function ($q) use ($request) {
                $q->where('type', $request->type);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

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
        | 🚀 BULK LOAD ATTEMPTS (NO N+1)
        |--------------------------------------------------
        */

        // collect unique pairs
        $pairs = $results->getCollection()->map(function ($item) {
            return $item->user_id . '-' . $item->assessment_id;
        })->unique();

        // extract ids
        $userIds = $results->pluck('user_id')->unique();
        $assessmentIds = $results->pluck('assessment_id')->unique();

        // fetch all attempts in one query
        $allAttempts = AssessmentAttempt::whereIn('user_id', $userIds)
            ->whereIn('assessment_id', $assessmentIds)
            ->select('id', 'user_id', 'assessment_id', 'score', 'percentage', 'status', 'submitted_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '-' . $item->assessment_id;
            });

        /*
        |--------------------------------------------------
        | 🎯 TRANSFORM
        |--------------------------------------------------
        */
        $results->getCollection()->transform(function ($item) use ($allAttempts) {

            $key = $item->user_id . '-' . $item->assessment_id;

            $attempts = $allAttempts[$key] ?? collect();

            // sort attempts
            $attempts = $attempts->sortBy('submitted_at')->values();

            /*
            |-----------------------------------------
            | ANSWER STATS
            |-----------------------------------------
            */
            $answers = $item->answers;

            $correct = $answers->where('is_correct', true)->count();

            $incorrect = $answers->where('is_correct', false)
                ->whereNotNull('selected_option_id')
                ->count();

            $skipped = $answers->whereNull('selected_option_id')->count();

            $totalQuestions = $answers->count();

            /*
            |-----------------------------------------
            | ATTEMPTS DATA
            |-----------------------------------------
            */
            $attemptsData = $attempts->map(function ($a) {
                return [
                    'id' => $a->id,
                    'score' => $a->score,
                    'percentage' => $a->percentage,
                    'status' => $a->status,
                    'submitted_at' => $a->submitted_at,
                ];
            });

            /*
            |-----------------------------------------
            | PASSED ATTEMPT
            |-----------------------------------------
            */
            $passedAttempt = $attempts
                ->where('status', 'passed')
                ->first(); // change to last() if needed

            return [
                'user_name' => $item->user?->name,
                'email' => $item->user?->email,
                'employee_id' => $item->user?->employee_id,

                'assessment_name' => $item->assessment?->title,
                'assessment_type' => $item->assessment?->type,

                'related_name' => $item->assessment?->assessmentable?->title,

                'attempt_date' => $item->submitted_at,

                'score' => $item->score,
                'percentage' => $item->percentage,
                'passing_score' => $item->assessment?->passing_score,

                'status' => $item->status,

                // 🔥 NEW
                'attempt_count' => $attempts->count(),
                'passed_attempt_id' => $passedAttempt?->id,
                'attempts' => $attemptsData,

                // question stats
                'total_questions' => $totalQuestions,
                'correct_answers' => $correct,
                'incorrect_answers' => $incorrect,
                'skipped' => $skipped,
            ];
        });

        return $results;
    }
}