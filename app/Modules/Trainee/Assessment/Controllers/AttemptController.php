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
use App\Services\AuditService;
use Carbon\Carbon;
use App\Services\CertificationService;


class AttemptController extends Controller
{
    protected $service;
    protected $certificationService;

    public function __construct(AssessmentService $service)
    {
        $this->service = $service;
    }

    // 🔹 START
    public function start($id)
    {
        AuditService::log('assessment_started', 'User started an assessment', ['assessment_id' => $id]);

        $userId = auth()->id();

        $assessment = Assessment::findOrFail($id);

        // 🔹 Topic content completion check
        if ($assessment->type === 'topic') {

            $topic = \App\Models\Topic::find($assessment->assessmentable_id);

            if ($topic) {
                $isReady = app(\App\Modules\Trainee\Progress\Controllers\ProgressController::class)
                    ->isTopicContentCompleted($topic, $userId);

                if (!$isReady) {
                    return response()->json([
                        'message' => 'Complete all content first'
                    ], 422);
                }
            }
        }

        // 🔹 max attempts config
        $maxAttempts = $assessment->type === 'level'
            ? config('assessment.exam.max_attempts', 2)
            : config('assessment.quiz.max_attempts', 5);

        // 🔹 completed attempts count (IMPORTANT)
        $completedAttempts = AssessmentAttempt::where('user_id', $userId)
            ->where('assessment_id', $id)
            ->whereIn('status', ['passed', 'failed'])
            ->count();

        $remainingAttempts = max(0, $maxAttempts - $completedAttempts);

        // 🔁 active attempt check
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
                    : null,

                // 🆕 attempt stats
                'total_attempts_allowed' => $maxAttempts,
                'attempts_used' => $completedAttempts,
                'attempts_remaining' => $remainingAttempts,
            ]);
        }

        // 🚫 limit reached
        if ($completedAttempts >= $maxAttempts) {
            return response()->json([
                'message' => 'Max attempts reached',

                'total_attempts_allowed' => $maxAttempts,
                'attempts_used' => $completedAttempts,
                'attempts_remaining' => 0,
            ], 422);
        }

        // ✅ create new attempt
        $attempt = AssessmentAttempt::create([
            'user_id' => $userId,
            'assessment_id' => $id,
            'started_at' => now(),
            'status' => 'in_progress'
        ]);

        return response()->json([
            'message' => 'New attempt started',

            'attempt_id' => $attempt->id,
            'type' => $assessment->type,
            'duration' => $assessment->duration,
            'started_at' => $attempt->started_at,
            'expires_at' => $assessment->duration
                ? $attempt->started_at->addMinutes($assessment->duration)
                : null,

            // 🆕 attempt stats
            'total_attempts_allowed' => $maxAttempts,
            'attempts_used' => $completedAttempts,
            'attempts_remaining' => $remainingAttempts,
        ]);
    }

    // 🔹 QUESTIONS
    public function questions($id, Request $request)
    {
        $attemptId = $request->attempt_id;

        $attempt = AssessmentAttempt::with('assessment')->findOrFail($attemptId);
        $assessment = Assessment::with('questions.options')->findOrFail($id);

        // 🔹 Answers (for selection + progress)
        $answers = AssessmentAnswer::where('attempt_id', $attemptId)
            ->get()
            ->keyBy('question_id');

        /*
        |-----------------------------
        | QUESTIONS TRANSFORM
        |-----------------------------
        */
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

        /*
        |-----------------------------
        | ATTEMPT PROGRESS STATS
        |-----------------------------
        */
        $totalQuestions = $assessment->questions->count();

        $answeredCount = $answers->filter(function ($a) {
            return !is_null($a->selected_option_id);
        })->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |-----------------------------
        | HIERARCHY (Topic / Level)
        |-----------------------------
        */
        $context = null;

        if ($assessment->assessmentable_type === \App\Models\Topic::class) {

            $topic = \App\Models\Topic::with('chapter.module.level.program')
                ->find($assessment->assessmentable_id);

            $context = [
                'type' => 'topic',
                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                ],
                'chapter' => [
                    'id' => $topic->chapter->id ?? null,
                    'title' => $topic->chapter->title ?? null,
                ],
                'module' => [
                    'id' => $topic->chapter->module->id ?? null,
                    'title' => $topic->chapter->module->title ?? null,
                ],
                'level' => [
                    'id' => $topic->chapter->module->level->id ?? null,
                    'title' => $topic->chapter->module->level->title ?? null,
                ],
                'program' => [
                    'id' => $topic->chapter->module->level->program->id ?? null,
                    'title' => $topic->chapter->module->level->program->title ?? null,
                ],
            ];
        }

        if ($assessment->assessmentable_type === \App\Models\Level::class) {

            $level = \App\Models\Level::with('program')
                ->find($assessment->assessmentable_id);

            $context = [
                'type' => 'level',
                'level' => [
                    'id' => $level->id,
                    'title' => $level->title,
                ],
                'program' => [
                    'id' => $level->program->id ?? null,
                    'title' => $level->program->title ?? null,
                ],
            ];
        }

        /*
        |-----------------------------
        | RESPONSE
        |-----------------------------
        */
        return response()->json([
            'attempt_id' => $attemptId,

            'type' => $assessment->type,

            'duration' => $assessment->duration,
            'started_at' => $attempt->started_at,
            'expires_at' => $assessment->duration
                ? $attempt->started_at->addMinutes($assessment->duration)
                : null,

            // 🆕 attempt progress
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredCount,
            'remaining_questions' => $remainingCount,

            // 🆕 context
            'context' => $context,

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
        $userId = auth()->id();

        $attempt = AssessmentAttempt::with([
            'assessment.questions.options',
            'answers'
        ])
            ->where('user_id', $userId)
            ->where('assessment_id', $id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'No active attempt'
            ], 404);
        }

        $assessment = $attempt->assessment;

        /*
        |-----------------------------
        | TIME
        |-----------------------------
        */
        $duration = $assessment->duration ?? null;
        $expiresAt = $duration
            ? $attempt->started_at->addMinutes($duration)
            : null;

        /*
        |-----------------------------
        | ANSWERS MAP
        |-----------------------------
        */
        $answersMap = $attempt->answers->keyBy('question_id');

        /*
        |-----------------------------
        | QUESTION LIST (WITH SELECTION)
        |-----------------------------
        */
        $questions = $assessment->questions->map(function ($q) use ($answersMap) {

            return [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'file' => $q->file,

                'options' => $q->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'text' => $opt->option_text
                ]),

                'selected_option_id' => $answersMap[$q->id]->selected_option_id ?? null
            ];
        });

        /*
        |-----------------------------
        | ATTEMPT PROGRESS
        |-----------------------------
        */
        $totalQuestions = $assessment->questions->count();

        $answeredCount = $attempt->answers->filter(function ($a) {
            return !is_null($a->selected_option_id);
        })->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |-----------------------------
        | CONTEXT (HIERARCHY)
        |-----------------------------
        */
        $context = null;

        if ($assessment->assessmentable_type === \App\Models\Topic::class) {

            $topic = \App\Models\Topic::with('chapter.module.level.program')
                ->find($assessment->assessmentable_id);

            $context = [
                'type' => 'topic',

                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                ],

                'chapter' => [
                    'id' => $topic->chapter->id ?? null,
                    'title' => $topic->chapter->title ?? null,
                ],

                'module' => [
                    'id' => $topic->chapter->module->id ?? null,
                    'title' => $topic->chapter->module->title ?? null,
                ],

                'level' => [
                    'id' => $topic->chapter->module->level->id ?? null,
                    'title' => $topic->chapter->module->level->title ?? null,
                ],

                'program' => [
                    'id' => $topic->chapter->module->level->program->id ?? null,
                    'title' => $topic->chapter->module->level->program->title ?? null,
                ],
            ];
        }

        if ($assessment->assessmentable_type === \App\Models\Level::class) {

            $level = \App\Models\Level::with('program')
                ->find($assessment->assessmentable_id);

            $context = [
                'type' => 'level',

                'level' => [
                    'id' => $level->id,
                    'title' => $level->title,
                ],

                'program' => [
                    'id' => $level->program->id ?? null,
                    'title' => $level->program->title ?? null,
                ],
            ];
        }

        /*
        |-----------------------------
        | RESPONSE
        |-----------------------------
        */
        return response()->json([
            'attempt_id' => $attempt->id,

            'type' => $assessment->type,

            'started_at' => $attempt->started_at,
            'expires_at' => $expiresAt,

            // 🆕 attempt stats
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredCount,
            'remaining_questions' => $remainingCount,

            // 🆕 context (topic / level name)
            'context' => $context,

            // 🆕 full questions (resume ready)
            'questions' => $questions,

            // raw answers (optional, keep if needed)
            'answers' => $attempt->answers
        ]);
    }


    public function submit($id, Request $request)
    {
        AuditService::log('assessment_submitted', 'User submitted an assessment', ['assessment_id' => $id]);

        /*
        |--------------------------------------------------
        | 🔐 VALIDATION
        |--------------------------------------------------
        */
        $request->validate([
            'attempt_id' => 'required|exists:assessment_attempts,id',
            'submit_type' => 'nullable|in:manual,quit',
            'time_taken_seconds' => 'nullable|numeric|min:0'
        ]);

        $userId = auth()->id();

        $attempt = AssessmentAttempt::with(['answers', 'assessment.questions'])
            ->where('id', $request->attempt_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // ❌ already submitted
        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'Already submitted'
            ], 422);
        }

        $assessment = $attempt->assessment;

        /*
        |--------------------------------------------------
        | ⏱ SUBMIT TYPE
        |--------------------------------------------------
        */
        $submitType = $request->submit_type ?? 'manual';

        if ($assessment->duration) {
            $expire = $attempt->started_at->addMinutes($assessment->duration);

            if (now()->greaterThan($expire)) {
                $submitType = 'timeout';
            }
        }

        /*
        |--------------------------------------------------
        | 📊 QUESTION STATS
        |--------------------------------------------------
        */
        $totalQuestions = $assessment->questions->count();

        $answeredCount = $attempt->answers->whereNotNull('selected_option_id')->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |--------------------------------------------------
        | ⏱ TIME TAKEN
        |--------------------------------------------------
        */
        if ($request->filled('time_taken_seconds')) {
            $timeTaken = max(0, (int)$request->time_taken_seconds);
        } else {
            $startedAt = Carbon::parse($attempt->started_at);
            $timeTaken = $startedAt->diffInSeconds(now());
        }

        /*
        |--------------------------------------------------
        | 📊 ATTEMPT LIMIT
        |--------------------------------------------------
        */
        $maxAttempts = $assessment->type === 'level'
            ? config('assessment.exam.max_attempts', 2)
            : config('assessment.quiz.max_attempts', 5);

        $completedAttempts = AssessmentAttempt::where('user_id', $userId)
            ->where('assessment_id', $assessment->id)
            ->whereIn('status', ['passed', 'failed'])
            ->count();

        /*
        |--------------------------------------------------
        | 🧠 HIERARCHY BUILD
        |--------------------------------------------------
        */
        $context = null;

        if ($assessment->assessmentable_type === Topic::class) {

            $topic = Topic::with('chapter.module.level.program')
                ->find($assessment->assessmentable_id);

            if ($topic) {
                $context = [
                    'type' => 'topic',
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                    ],
                    'chapter' => [
                        'id' => $topic->chapter->id ?? null,
                        'title' => $topic->chapter->title ?? null,
                    ],
                    'module' => [
                        'id' => $topic->chapter->module->id ?? null,
                        'title' => $topic->chapter->module->title ?? null,
                    ],
                    'level' => [
                        'id' => $topic->chapter->module->level->id ?? null,
                        'title' => $topic->chapter->module->level->title ?? null,
                    ],
                    'program' => [
                        'id' => $topic->chapter->module->level->program->id ?? null,
                        'title' => $topic->chapter->module->level->program->title ?? null,
                    ],
                ];
            }
        }

        if ($assessment->assessmentable_type === Level::class) {

            $level = Level::with('program')
                ->find($assessment->assessmentable_id);

            if ($level) {
                $context = [
                    'type' => 'level',
                    'level' => [
                        'id' => $level->id,
                        'title' => $level->title,
                    ],
                    'program' => [
                        'id' => $level->program->id ?? null,
                        'title' => $level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------
        | 🧠 MAIN TRANSACTION
        |--------------------------------------------------
        */
        return DB::transaction(function () use (
            $attempt,
            $assessment,
            $submitType,
            $timeTaken,
            $totalQuestions,
            $answeredCount,
            $remainingCount,
            $userId,
            $maxAttempts,
            $completedAttempts,
            $context
        ) {

            // 🔍 evaluate
            $result = $this->service->evaluateAttempt($attempt);

            $isPassed = $result['percentage'] >= $assessment->passing_score;

            // 💾 update attempt
            $attempt->update([
                'score' => $result['marks'],
                'percentage' => $result['percentage'],
                'submitted_at' => now(),
                'status' => $isPassed ? 'passed' : 'failed',
                'time_taken' => $timeTaken,
                'submit_type' => $submitType
            ]);

            $certificate = null;

            /*
            |--------------------------------------------------
            | 🔓 PROGRESSION + CERTIFICATE
            |--------------------------------------------------
            */
            if ($isPassed) {

                $progressionService = app(ProgressionService::class);

                if ($assessment->type === 'topic') {

                    $topic = Topic::find($assessment->assessmentable_id);

                    if ($topic) {
                        $progressionService->handleTopicCompletion($userId, $topic);

                        $certificate = app(\App\Services\CertificationService::class)
                            ->generate(auth()->user(), $topic, $attempt, 'topic');
                    }
                }

                if ($assessment->type === 'level') {

                    $level = Level::find($assessment->assessmentable_id);

                    if ($level) {
                        $progressionService->handleLevelExamPass($userId, $level);

                        $certificate = app(\App\Services\CertificationService::class)
                            ->generate(auth()->user(), $level, $attempt, 'level');
                    }
                }
            }

            /*
        |--------------------------------------------------
        | 📦 FINAL RESPONSE
        |--------------------------------------------------
        */
            return response()->json([

                // 🎯 RESULT
                'score' => $result['marks'],
                'total' => $totalQuestions,
                'percentage' => $result['percentage'],
                'correct' => $result['correct'],
                'wrong' => $result['wrong'],
                'skipped' => $result['skipped'],
                'status' => $attempt->status,

                // 📊 ATTEMPT
                'attempt_id' => $attempt->id,
                'type' => $assessment->type,

                'total_attempts_allowed' => $maxAttempts,
                'attempts_used' => $completedAttempts + 1,
                'attempts_remaining' => max(0, $maxAttempts - ($completedAttempts + 1)),

                // 📊 QUESTIONS
                'answered_questions' => $answeredCount,
                'remaining_questions' => $remainingCount,

                // ⏱ TIME
                'submit_type' => $submitType,
                'time_taken_seconds' => $timeTaken,
                'time_taken_minutes' => round($timeTaken / 60, 2),

                // 🧠 HIERARCHY
                'context' => $context,

                // 🎓 CERTIFICATE
                'certificate_generated' => $certificate ? true : false,
                'certificate_id' => $certificate->certificate_id ?? null,
            ]);
        });
    }   
}
