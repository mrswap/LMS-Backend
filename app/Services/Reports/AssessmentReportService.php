<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAnswer;

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
            ->whereHas('assessment', function ($q) {

                $q->where('status', true);
            })
            ->with([

                'user:id,name,email,employee_id',

                'assessment:id,title,passing_score,assessmentable_id,assessmentable_type,type,status',

                'assessment.assessmentable',

                'answers:id,attempt_id,selected_option_id,is_correct'
            ]);

        /*
    |--------------------------------------------------
    | USER SCOPING
    |--------------------------------------------------
    */
        if ($userId) {

            $query->where('user_id', $userId);
        } elseif ($request->filled('user_id')) {

            $query->where('user_id', $request->user_id);
        }

        /*
    |--------------------------------------------------
    | FILTERS
    |--------------------------------------------------
    */
        if ($request->filled('type')) {

            $query->whereHas('assessment', function ($q) use ($request) {

                $q->where('type', $request->type);
            });
        }

        if ($request->filled('status')) {

            $query->where('status', $request->status);
        }

        if (
            $request->filled('from_date') &&
            $request->filled('to_date')
        ) {

            $query->whereBetween('submitted_at', [

                $request->from_date,
                $request->to_date
            ]);
        }

        /*
    |--------------------------------------------------
    | SORTING
    |--------------------------------------------------
    */
        $query->orderBy(
            $request->get('sort_by', 'submitted_at'),
            $request->get('sort_order', 'desc')
        );

        /*
    |--------------------------------------------------
    | PAGINATION
    |--------------------------------------------------
    */
        $results = $query->paginate($perPage);

        $collection = $results->getCollection();

        /*
    |--------------------------------------------------
    | BULK ATTEMPTS
    |--------------------------------------------------
    */
        $userIds = $collection->pluck('user_id')->unique();

        $assessmentIds = $collection->pluck('assessment_id')->unique();

        $allAttempts = AssessmentAttempt::whereIn(
            'user_id',
            $userIds
        )
            ->whereIn(
                'assessment_id',
                $assessmentIds
            )
            ->select(
                'id',
                'user_id',
                'assessment_id',
                'score',
                'percentage',
                'status',
                'submitted_at'
            )
            ->get()
            ->groupBy(
                fn($item) =>
                $item->user_id . '-' . $item->assessment_id
            );

        /*
    |--------------------------------------------------
    | BULK ANSWERS
    |--------------------------------------------------
    */
        $attemptIds = $allAttempts
            ->flatten()
            ->pluck('id');

        $allAnswers = AssessmentAnswer::whereIn(
            'attempt_id',
            $attemptIds
        )
            ->get([
                'attempt_id',
                'selected_option_id',
                'is_correct'
            ])
            ->groupBy('attempt_id');

        /*
    |--------------------------------------------------
    | TRANSFORM
    |--------------------------------------------------
    */
        $collection->transform(function (
            $item
        ) use (
            $allAttempts,
            $allAnswers
        ) {

            $key = $item->user_id . '-' . $item->assessment_id;

            $attempts = ($allAttempts[$key] ?? collect())
                ->sortBy('submitted_at')
                ->values();

            /*
        |--------------------------------------------------
        | CURRENT ANSWERS
        |--------------------------------------------------
        */
            $answers = $item->answers;

            $correct = $answers
                ->where('is_correct', true)
                ->count();

            $incorrect = $answers
                ->where('is_correct', false)
                ->whereNotNull('selected_option_id')
                ->count();

            $skipped = $answers
                ->whereNull('selected_option_id')
                ->count();

            $totalQuestions = $answers->count();

            /*
        |--------------------------------------------------
        | ATTEMPTS DATA
        |--------------------------------------------------
        */
            $attemptsData = $attempts->map(function (
                $a
            ) use (
                $allAnswers
            ) {

                $answers = $allAnswers[$a->id] ?? collect();

                return [

                    'id' => $a->id,

                    'score' => $a->score,

                    'percentage' => $a->percentage,

                    'status' => $a->status,

                    'submitted_at' => $a->submitted_at,

                    'total_questions' => $answers->count(),

                    'correct_answers' => $answers
                        ->where('is_correct', true)
                        ->count(),

                    'incorrect_answers' => $answers
                        ->where('is_correct', false)
                        ->whereNotNull('selected_option_id')
                        ->count(),

                    'skipped' => $answers
                        ->whereNull('selected_option_id')
                        ->count(),
                ];
            });

            /*
        |--------------------------------------------------
        | PASSED ATTEMPT
        |--------------------------------------------------
        */
            $passedAttempt = $attempts
                ->where('status', 'passed')
                ->first();

            /*
        |--------------------------------------------------
        | HIERARCHY
        |--------------------------------------------------
        */
            $assessment = $item->assessment;

            $assessmentable = $assessment?->assessmentable;

            $hierarchy = null;

            if ($assessmentable) {

                /*
            |--------------------------------------------------
            | TOPIC
            |--------------------------------------------------
            */
                if ($assessment->type === 'topic') {

                    $assessmentable->loadMissing(
                        'chapter.module.level.program'
                    );

                    $hierarchy = [

                        'type' => 'topic',

                        'topic' => [
                            'id' => $assessmentable->id,
                            'title' => $assessmentable->title
                        ],

                        'chapter' => [
                            'id' => $assessmentable->chapter?->id,
                            'title' => $assessmentable->chapter?->title
                        ],

                        'module' => [
                            'id' => $assessmentable->chapter?->module?->id,
                            'title' => $assessmentable->chapter?->module?->title
                        ],

                        'level' => [
                            'id' => $assessmentable->chapter?->module?->level?->id,
                            'title' => $assessmentable->chapter?->module?->level?->title
                        ],

                        'program' => [
                            'id' => $assessmentable->chapter?->module?->level?->program?->id,
                            'title' => $assessmentable->chapter?->module?->level?->program?->title
                        ],
                    ];
                }

                /*
            |--------------------------------------------------
            | CHAPTER
            |--------------------------------------------------
            */
                if ($assessment->type === 'chapter') {

                    $assessmentable->loadMissing(
                        'module.level.program'
                    );

                    $hierarchy = [

                        'type' => 'chapter',

                        'chapter' => [
                            'id' => $assessmentable->id,
                            'title' => $assessmentable->title
                        ],

                        'module' => [
                            'id' => $assessmentable->module?->id,
                            'title' => $assessmentable->module?->title
                        ],

                        'level' => [
                            'id' => $assessmentable->module?->level?->id,
                            'title' => $assessmentable->module?->level?->title
                        ],

                        'program' => [
                            'id' => $assessmentable->module?->level?->program?->id,
                            'title' => $assessmentable->module?->level?->program?->title
                        ],
                    ];
                }

                /*
            |--------------------------------------------------
            | MODULE
            |--------------------------------------------------
            */
                if ($assessment->type === 'module') {

                    $assessmentable->loadMissing(
                        'level.program'
                    );

                    $hierarchy = [

                        'type' => 'module',

                        'module' => [
                            'id' => $assessmentable->id,
                            'title' => $assessmentable->title
                        ],

                        'level' => [
                            'id' => $assessmentable->level?->id,
                            'title' => $assessmentable->level?->title
                        ],

                        'program' => [
                            'id' => $assessmentable->level?->program?->id,
                            'title' => $assessmentable->level?->program?->title
                        ],
                    ];
                }

                /*
            |--------------------------------------------------
            | LEVEL
            |--------------------------------------------------
            */
                if ($assessment->type === 'level') {

                    $assessmentable->loadMissing(
                        'program'
                    );

                    $hierarchy = [

                        'type' => 'level',

                        'level' => [
                            'id' => $assessmentable->id,
                            'title' => $assessmentable->title
                        ],

                        'program' => [
                            'id' => $assessmentable->program?->id,
                            'title' => $assessmentable->program?->title
                        ],
                    ];
                }
            }

            return [

                'user_name' => $item->user?->name,

                'email' => $item->user?->email,

                'employee_id' => $item->user?->employee_id,

                'assessment_name' => $assessment?->title,

                'assessment_type' => $assessment?->type,

                'related_name' => $assessmentable?->title,

                'hierarchy' => $hierarchy,

                'attempt_date' => $item->submitted_at,

                'score' => $item->score,

                'percentage' => $item->percentage,

                'passing_score' => $assessment?->passing_score,

                'status' => $item->status,

                'attempt_count' => $attempts->count(),

                'passed_attempt_id' => $passedAttempt?->id,

                'attempts' => $attemptsData,

                'total_questions' => $totalQuestions,

                'correct_answers' => $correct,

                'incorrect_answers' => $incorrect,

                'skipped' => $skipped,
            ];
        });

        return $results;
    }
}
