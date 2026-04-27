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

        // 🔥 Topic filter
        if ($request->filled('topic_id')) {
            $query->whereHas('assessment', function ($q) use ($request) {
                $q->where('type', 'topic')
                    ->where('assessmentable_id', $request->topic_id);
            });
        }

        // 🔥 Level filter
        if ($request->filled('level_id')) {
            $query->whereHas('assessment', function ($q) use ($request) {
                $q->where('type', 'level')
                    ->where('assessmentable_id', $request->level_id);
            });
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
}
