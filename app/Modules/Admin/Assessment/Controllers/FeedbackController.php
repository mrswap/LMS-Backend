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
            'assessment:id,title,type',
            'attempt:id,score,percentage,status',
            'assessment.assessmentable'
        ]);

        /*
        |-----------------------------
        | FILTERS
        |-----------------------------
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

        if ($request->filled('type')) {

            $type = strtolower($request->type);

            if ($type === 'topic') {

                $query->whereHas('assessment', function ($q) use ($request) {
                    $q->where('type', 'topic');

                    // optional specific topic filter
                    if ($request->filled('topic_id')) {
                        $q->where('assessmentable_id', $request->topic_id);
                    }
                });
            } elseif ($type === 'level') {

                $query->whereHas('assessment', function ($q) use ($request) {
                    $q->where('type', 'level');

                    // optional specific level filter
                    if ($request->filled('level_id')) {
                        $q->where('assessmentable_id', $request->level_id);
                    }
                });
            }
        }

        /*
        |-----------------------------
        | SEARCH (review text)
        |-----------------------------
        */
        if ($request->filled('search')) {
            $query->where('review', 'like', '%' . $request->search . '%');
        }

        /*
        |-----------------------------
        | SORTING
        |-----------------------------
        */
        $query->orderBy('created_at', 'desc');

        /*
        |-----------------------------
        | PAGINATION
        |-----------------------------
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
