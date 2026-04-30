<?php

namespace App\Modules\Trainee\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentFeedback;
use App\Models\AssessmentAttempt;
use App\Services\AuditService;

class FeedbackController extends Controller
{
    public function store($id, Request $request)
    {
        AuditService::log('feedback_submitted', 'User submitted feedback for assessment attempt ID: ' . $request->attempt_id);
        $request->validate([
            'attempt_id' => 'required|exists:assessment_attempts,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);

        $attempt = AssessmentAttempt::where('id', $request->attempt_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($attempt->status === 'in_progress') {
            return response()->json([
                'message' => 'Submit assessment first'
            ], 422);
        }

        $feedback = AssessmentFeedback::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'attempt_id' => $request->attempt_id
            ],
            [
                'assessment_id' => $id,
                'rating' => $request->rating,
                'review' => $request->review
            ]
        );

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'data' => $feedback
        ]);
    }
}
