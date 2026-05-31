<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentFeedback;

class FeedbackController extends Controller
{

    public function index(Request $request)
    {
        $query = AssessmentFeedback::with([

            'user:id,name,email',

            'assessment:id,title,type,assessmentable_id,assessmentable_type',

            'attempt:id,score,percentage,status',

            'assessment.assessmentable'
        ]);

        /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

        if ($request->filled('user_id')) {

            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('assessment_id')) {

            $query->where('assessment_id', $request->assessment_id);
        }

        if ($request->filled('attempt_id')) {

            $query->where('attempt_id', $request->attempt_id);
        }

        if ($request->filled('rating')) {

            $query->where('rating', $request->rating);
        }

        /*
    |--------------------------------------------------------------------------
    | TYPE FILTER
    |--------------------------------------------------------------------------
    |
    | Supported:
    | topic
    | chapter
    | module
    | level
    |--------------------------------------------------------------------------
    */

        if ($request->filled('type')) {

            $allowedTypes = [
                'topic',
                'chapter',
                'module',
                'level'
            ];

            $type = strtolower($request->type);

            if (!in_array($type, $allowedTypes)) {

                return response()->json([

                    'success' => false,

                    'message' => 'Invalid assessment type'
                ], 422);
            }

            $query->whereHas('assessment', function ($q) use ($request, $type) {

                $q->where('type', $type);

                /*
            |--------------------------------------------------------------------------
            | SPECIFIC PARENT FILTERS
            |--------------------------------------------------------------------------
            */

                switch ($type) {

                    /*
                |--------------------------------------------------------------------------
                | TOPIC
                |--------------------------------------------------------------------------
                */

                    case 'topic':

                        if ($request->filled('topic_id')) {

                            $q->where('assessmentable_id', $request->topic_id)
                                ->where(
                                    'assessmentable_type',
                                    \App\Models\Topic::class
                                );
                        }

                        break;

                    /*
                |--------------------------------------------------------------------------
                | CHAPTER
                |--------------------------------------------------------------------------
                */

                    case 'chapter':

                        if ($request->filled('chapter_id')) {

                            $q->where('assessmentable_id', $request->chapter_id)
                                ->where(
                                    'assessmentable_type',
                                    \App\Models\Chapter::class
                                );
                        }

                        break;

                    /*
                |--------------------------------------------------------------------------
                | MODULE
                |--------------------------------------------------------------------------
                */

                    case 'module':

                        if ($request->filled('module_id')) {

                            $q->where('assessmentable_id', $request->module_id)
                                ->where(
                                    'assessmentable_type',
                                    \App\Models\Module::class
                                );
                        }

                        break;

                    /*
                |--------------------------------------------------------------------------
                | LEVEL
                |--------------------------------------------------------------------------
                */

                    case 'level':

                        if ($request->filled('level_id')) {

                            $q->where('assessmentable_id', $request->level_id)
                                ->where(
                                    'assessmentable_type',
                                    \App\Models\Level::class
                                );
                        }

                        break;
                }
            });
        }

        /*
    |--------------------------------------------------------------------------
    | HIERARCHY FILTERS (OPTIONAL)
    |--------------------------------------------------------------------------
    |
    | Allows:
    | level_id
    | module_id
    | chapter_id
    | topic_id
    |--------------------------------------------------------------------------
    */

        if (
            $request->filled('level_id') ||
            $request->filled('module_id') ||
            $request->filled('chapter_id') ||
            $request->filled('topic_id')
        ) {

            $query->whereHas('assessment', function ($q) use ($request) {

                /*
            |--------------------------------------------------------------------------
            | TOPIC FILTER
            |--------------------------------------------------------------------------
            */

                if ($request->filled('topic_id')) {

                    $q->where(function ($qq) use ($request) {

                        $qq->where(
                            'assessmentable_type',
                            \App\Models\Topic::class
                        )->where(
                            'assessmentable_id',
                            $request->topic_id
                        );
                    });
                }

                /*
            |--------------------------------------------------------------------------
            | CHAPTER FILTER
            |--------------------------------------------------------------------------
            */ elseif ($request->filled('chapter_id')) {

                    $topicIds = \App\Models\Topic::where(
                        'chapter_id',
                        $request->chapter_id
                    )->pluck('id');

                    $q->where(function ($qq) use ($request, $topicIds) {

                        // chapter assessment
                        $qq->where(function ($q1) use ($request) {

                            $q1->where(
                                'assessmentable_type',
                                \App\Models\Chapter::class
                            )->where(
                                'assessmentable_id',
                                $request->chapter_id
                            );
                        })

                            // topic assessments under chapter
                            ->orWhere(function ($q2) use ($topicIds) {

                                $q2->where(
                                    'assessmentable_type',
                                    \App\Models\Topic::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                            });
                    });
                }

                /*
            |--------------------------------------------------------------------------
            | MODULE FILTER
            |--------------------------------------------------------------------------
            */ elseif ($request->filled('module_id')) {

                    $chapterIds = \App\Models\Chapter::where(
                        'module_id',
                        $request->module_id
                    )->pluck('id');

                    $topicIds = \App\Models\Topic::whereIn(
                        'chapter_id',
                        $chapterIds
                    )->pluck('id');

                    $q->where(function ($qq) use (
                        $request,
                        $chapterIds,
                        $topicIds
                    ) {

                        // module assessment
                        $qq->where(function ($q1) use ($request) {

                            $q1->where(
                                'assessmentable_type',
                                \App\Models\Module::class
                            )->where(
                                'assessmentable_id',
                                $request->module_id
                            );
                        })

                            // chapter assessments
                            ->orWhere(function ($q2) use ($chapterIds) {

                                $q2->where(
                                    'assessmentable_type',
                                    \App\Models\Chapter::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $chapterIds
                                );
                            })

                            // topic assessments
                            ->orWhere(function ($q3) use ($topicIds) {

                                $q3->where(
                                    'assessmentable_type',
                                    \App\Models\Topic::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                            });
                    });
                }

                /*
            |--------------------------------------------------------------------------
            | LEVEL FILTER
            |--------------------------------------------------------------------------
            */ elseif ($request->filled('level_id')) {

                    $moduleIds = \App\Models\Module::where(
                        'level_id',
                        $request->level_id
                    )->pluck('id');

                    $chapterIds = \App\Models\Chapter::whereIn(
                        'module_id',
                        $moduleIds
                    )->pluck('id');

                    $topicIds = \App\Models\Topic::whereIn(
                        'chapter_id',
                        $chapterIds
                    )->pluck('id');

                    $q->where(function ($qq) use (
                        $request,
                        $moduleIds,
                        $chapterIds,
                        $topicIds
                    ) {

                        // level assessment
                        $qq->where(function ($q1) use ($request) {

                            $q1->where(
                                'assessmentable_type',
                                \App\Models\Level::class
                            )->where(
                                'assessmentable_id',
                                $request->level_id
                            );
                        })

                            // module assessments
                            ->orWhere(function ($q2) use ($moduleIds) {

                                $q2->where(
                                    'assessmentable_type',
                                    \App\Models\Module::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $moduleIds
                                );
                            })

                            // chapter assessments
                            ->orWhere(function ($q3) use ($chapterIds) {

                                $q3->where(
                                    'assessmentable_type',
                                    \App\Models\Chapter::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $chapterIds
                                );
                            })

                            // topic assessments
                            ->orWhere(function ($q4) use ($topicIds) {

                                $q4->where(
                                    'assessmentable_type',
                                    \App\Models\Topic::class
                                )->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                            });
                    });
                }
            });
        }

        /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

        if ($request->filled('search')) {

            $query->where('review', 'like', '%' . $request->search . '%');
        }

        /*
    |--------------------------------------------------------------------------
    | SORTING
    |--------------------------------------------------------------------------
    */

        $query->orderBy('created_at', 'desc');

        /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

        $limit = $request->get('limit', 10);

        return response()->json([

            'success' => true,

            'data' => $query->paginate($limit)
        ]);
    }


    public function show($id)
    {
        $feedback = AssessmentFeedback::with([
            'user:id,name,email',
            'assessment:id,title,type,assessmentable_id',
            'attempt:id,score,percentage,status',
            'assessment.assessmentable'
        ])->find($id);

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'review' => $feedback->review,

                'user' => $feedback->user,

                'assessment' => [
                    'id' => $feedback->assessment->id,
                    'title' => $feedback->assessment->title,
                    'type' => $feedback->assessment->type,
                    'linked_to' => $feedback->assessment->assessmentable // topic or level
                ],

                'attempt' => [
                    'id' => $feedback->attempt->id,
                    'score' => $feedback->attempt->score,
                    'percentage' => $feedback->attempt->percentage,
                    'status' => $feedback->attempt->status
                ],

                'created_at' => $feedback->created_at
            ]
        ]);
    }
}
