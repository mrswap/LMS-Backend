<?php

namespace App\Modules\Trainee\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAnswer;
use App\Modules\Trainee\Assessment\Services\AssessmentService;
use DB;
use App\Modules\Trainee\Progress\Services\ProgressionService;
use App\Models\Topic;
use App\Models\Level;

class AttemptController extends Controller
{
    protected $service;

    public function __construct(AssessmentService $service)
    {
        $this->service = $service;
    }

    // 🔹 START
    public function start($id)
    {
        $userId = auth()->id();

        $assessment = Assessment::findOrFail($id);
        
        if ($assessment->type === 'topic') {

            $topic = \App\Models\Topic::find($assessment->assessmentable_id);

            if ($topic) {
                $isReady = app(\App\Modules\Trainee\Progress\Controllers\ProgressController::class)
                    ->isTopicContentCompleted($topic, auth()->id());

                if (!$isReady) {
                    return response()->json([
                        'message' => 'Complete all content first'
                    ], 422);
                }
            }
        }

        // 🧠 type-based config
        $maxAttempts = $assessment->type === 'level'
            ? config('assessment.exam.max_attempts', 2)
            : config('assessment.quiz.max_attempts', 5);

        // 🔁 check active attempt
        $activeAttempt = AssessmentAttempt::where('user_id', $userId)
            ->where('assessment_id', $id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($activeAttempt) {
            return response()->json([
                'message' => 'Resume existing attempt',
                'attempt_id' => $activeAttempt->id,
                'type' => $assessment->type,
                'duration' => $assessment->duration,
                'started_at' => $activeAttempt->started_at,
                'expires_at' => $assessment->duration
                    ? $activeAttempt->started_at->addMinutes($assessment->duration)
                    : null
            ]);
        }

        // 🔢 count completed attempts only
        $attemptCount = AssessmentAttempt::where('user_id', $userId)
            ->where('assessment_id', $id)
            ->whereIn('status', ['passed', 'failed'])
            ->count();

        if ($attemptCount >= $maxAttempts) {
            return response()->json([
                'message' => 'Max attempts reached'
            ], 422);
        }

        // ✅ create attempt
        $attempt = AssessmentAttempt::create([
            'user_id' => $userId,
            'assessment_id' => $id,
            'started_at' => now(),
            'status' => 'in_progress'
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'type' => $assessment->type,
            'duration' => $assessment->duration,
            'started_at' => $attempt->started_at,
            'expires_at' => $assessment->duration
                ? $attempt->started_at->addMinutes($assessment->duration)
                : null
        ]);
    }

    // 🔹 QUESTIONS
    public function questions($id, Request $request)
    {
        $attemptId = $request->attempt_id;

        $attempt = AssessmentAttempt::with('assessment')->findOrFail($attemptId);

        $assessment = Assessment::with('questions.options')->findOrFail($id);

        $answers = AssessmentAnswer::where('attempt_id', $attemptId)
            ->get()
            ->keyBy('question_id');

        $questions = $assessment->questions->map(function ($q) use ($answers) {

            return [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'file' => $q->file,
                'options' => $q->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'text' => $opt->option_text
                ]),
                'selected_option_id' => $answers[$q->id]->selected_option_id ?? null
            ];
        });

        return response()->json([
            'attempt_id' => $attemptId,
            'duration' => $assessment->duration,
            'started_at' => $attempt->started_at,
            'expires_at' => $assessment->duration
                ? $attempt->started_at->addMinutes($assessment->duration)
                : null,
            'questions' => $questions
        ]);
    }

    // 🔹 ANSWER
    public function answer(Request $request)
    {
        $request->validate([
            'attempt_id' => 'required|exists:assessment_attempts,id',
            'question_id' => 'required|exists:assessment_questions,id',
            'selected_option_id' => 'nullable|exists:assessment_options,id'
        ]);

        $question = \App\Models\AssessmentQuestion::with('options')
            ->findOrFail($request->question_id);

        $options = $question->options->map(fn($opt) => [
            'id' => $opt->id,
            'text' => $opt->option_text
        ]);

        $correct = $question->options->where('is_correct', true)->first();
        $attempt = AssessmentAttempt::findOrFail($request->attempt_id);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'Attempt already submitted or expired'
            ], 422);
        }
        if ($attempt->assessment->duration) {
            $expire = $attempt->started_at->addMinutes($attempt->assessment->duration);

            if (now()->greaterThan($expire)) {
                return response()->json([
                    'message' => 'Time expired. Cannot answer.'
                ], 422);
            }
        }
        return AssessmentAnswer::updateOrCreate(
            [
                'attempt_id' => $request->attempt_id,
                'question_id' => $request->question_id
            ],
            [
                'question_text_snapshot' => $question->question_text,
                'options_snapshot' => $options,
                'correct_option_id_snapshot' => $correct->id ?? null,
                'marks_snapshot' => $question->marks,
                'selected_option_id' => $request->selected_option_id,
            ]
        );
    }

    // 🔹 RESUME
    public function resume($id)
    {
        $attempt = AssessmentAttempt::with('assessment', 'answers')
            ->where('user_id', auth()->id())
            ->where('assessment_id', $id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'No active attempt'
            ], 404);
        }

        $duration = $attempt->assessment->duration ?? null;

        return response()->json([
            'attempt_id' => $attempt->id,
            'started_at' => $attempt->started_at,
            'expires_at' => $duration
                ? $attempt->started_at->addMinutes($duration)
                : null,
            'answers' => $attempt->answers
        ]);
    }


    public function submit($id, Request $request)
    {
        $attempt = AssessmentAttempt::with('answers')
            ->where('id', $request->attempt_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'Already submitted'
            ], 422);
        }

        $assessment = Assessment::findOrFail($id);

        // ⏱ timer check
        if ($assessment->duration) {
            $expire = $attempt->started_at->addMinutes($assessment->duration);

            if (now()->greaterThan($expire)) {
                // auto submit allowed
            }
        }

        return DB::transaction(function () use ($attempt, $assessment) {

            $result = $this->service->evaluateAttempt($attempt);

            // 🔥 determine pass/fail first
            $isPassed = $result['percentage'] >= $assessment->passing_score;

            $attempt->update([
                'score' => $result['marks'],
                'percentage' => $result['percentage'],
                'submitted_at' => now(),
                'status' => $isPassed ? 'passed' : 'failed'
            ]);

            /*
            |--------------------------------------------------------------------------
            | 🔥 PHASE 3 INTEGRATION (MOST IMPORTANT)
            |--------------------------------------------------------------------------
            */
            if ($isPassed) {

                $progressionService = app(ProgressionService::class);

                // 🟢 Topic Quiz
                if ($assessment->type === 'topic') {

                    // ⚠️ make sure assessment me topic_id hai
                    $topic = Topic::find($assessment->assessmentable_id);

                    if ($topic) {
                        $progressionService->handleTopicCompletion(
                            auth()->id(),
                            $topic
                        );
                    }
                }

                // 🔴 Level Exam
                if ($assessment->type === 'level') {

                    $level = Level::find($assessment->assessmentable_id);

                    if ($level) {
                        $progressionService->handleLevelExamPass(
                            auth()->id(),
                            $level
                        );
                    }
                }
            }

            return response()->json([
                'score' => $result['marks'],
                'total' => $result['total'],
                'percentage' => $result['percentage'],
                'correct' => $result['correct'],
                'wrong' => $result['wrong'],
                'skipped' => $result['skipped'],
                'status' => $attempt->status
            ]);
        });
    }
}
