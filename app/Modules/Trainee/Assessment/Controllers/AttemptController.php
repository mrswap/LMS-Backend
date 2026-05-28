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
use App\Models\Module;
use App\Models\Chapter;
use App\Services\AuditService;
use Carbon\Carbon;
use App\Models\AssessmentAttemptQuestion;
use App\Services\CertificationService;
use App\Models\User;
use App\Services\NotificationService;


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
        AuditService::log(
            'assessment_started',
            'User started an assessment',
            [
                'assessment_id' => $id
            ]
        );

        $userId = auth()->id();

        $assessment = Assessment::with('questions')
            ->findOrFail($id);

        /*
    |--------------------------------------------------------------------------
    | 🔐 ACCESS VALIDATION
    |--------------------------------------------------------------------------
    */

        $assessmentType = $assessment->type;

        $assessmentableId = $assessment->assessmentable_id;

        /*
    |--------------------------------------------------------------------------
    | TOPIC ASSESSMENT
    |--------------------------------------------------------------------------
    */

        if ($assessmentType === 'topic') {

            $topic = \App\Models\Topic::where(
                'status',
                1
            )->find($assessmentableId);

            if ($topic) {

                $isReady = app(
                    \App\Modules\Trainee\Progress\Controllers\ProgressController::class
                )->isTopicContentCompleted(
                    $topic,
                    $userId
                );

                if (! $isReady) {

                    return response()->json([
                        'message' => 'Complete all topic content first'
                    ], 422);
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | DYNAMIC HIGHER LEVEL VALIDATION
    |--------------------------------------------------------------------------
    */ else {

            $validationMap = [

                'chapter' => [

                    'model' => \App\Models\Chapter::class,

                    'content_model' => \App\Models\Topic::class,

                    'content_fk' => 'chapter_id',

                    'progress_fk' => 'chapter_id',

                    'message' => 'Complete all chapter topics first',
                ],

                'module' => [

                    'model' => \App\Models\Module::class,

                    'content_model' => \App\Models\Topic::class,

                    'content_fk' => 'module_id',

                    'progress_fk' => 'module_id',

                    'message' => 'Complete all module topics first',
                ],

                'level' => [

                    'model' => \App\Models\Level::class,

                    'content_model' => \App\Models\Module::class,

                    'content_fk' => 'level_id',

                    'progress_fk' => 'level_id',

                    'message' => 'Complete all level modules first',

                    'extra_progress_conditions' => function ($query) {

                        return $query
                            ->whereNull('chapter_id')
                            ->whereNull('topic_id')
                            ->whereNotNull('module_id');
                    }
                ],
            ];

            if (isset($validationMap[$assessmentType])) {

                $config = $validationMap[$assessmentType];

                $modelClass = $config['model'];

                $contentModel = $config['content_model'];

                $entity = $modelClass::where(
                    'status',
                    1
                )->find($assessmentableId);

                if ($entity) {

                    /*
|--------------------------------------------------------------------------
| ACTIVE CONTENT IDS
|--------------------------------------------------------------------------
|
| Ignore disabled hierarchy
|
*/

                    if ($assessmentType === 'chapter') {

                        /*
                                        |--------------------------------------------------------------------------
                                        | ONLY ACTIVE TOPICS OF ACTIVE CHAPTER
                                        |--------------------------------------------------------------------------
                                        */

                        $activeContentIds = \App\Models\Topic::where(
                            'chapter_id',
                            $entity->id
                        )
                            ->where('status', 1)
                            ->pluck('id');
                    }

                    /*
                        |--------------------------------------------------------------------------
                        | MODULE
                        |--------------------------------------------------------------------------
                        */ elseif ($assessmentType === 'module') {

                        /*
                                                    |--------------------------------------------------------------------------
                                                    | ACTIVE CHAPTERS
                                                    |--------------------------------------------------------------------------
                                                    */

                        $activeChapterIds = \App\Models\Chapter::where(
                            'module_id',
                            $entity->id
                        )
                            ->where('status', 1)
                            ->pluck('id');

                        /*
                                            |--------------------------------------------------------------------------
                                            | ACTIVE TOPICS OF ACTIVE CHAPTERS
                                            |--------------------------------------------------------------------------
                                            */

                        $activeContentIds = \App\Models\Topic::whereIn(
                            'chapter_id',
                            $activeChapterIds
                        )
                            ->where('status', 1)
                            ->pluck('id');
                    }

                    /*
                        |--------------------------------------------------------------------------
                        | LEVEL
                        |--------------------------------------------------------------------------
                        */ elseif ($assessmentType === 'level') {

                        /*
                    |--------------------------------------------------------------------------
                    | ACTIVE MODULES
                    |--------------------------------------------------------------------------
                    */

                        $activeModuleIds = \App\Models\Module::where(
                            'level_id',
                            $entity->id
                        )
                            ->where('status', 1)
                            ->pluck('id');

                        /*
                        |--------------------------------------------------------------------------
                        | ACTIVE CHAPTERS
                        |--------------------------------------------------------------------------
                        */

                        $activeChapterIds = \App\Models\Chapter::whereIn(
                            'module_id',
                            $activeModuleIds
                        )
                            ->where('status', 1)
                            ->pluck('id');

                        /*
                        |--------------------------------------------------------------------------
                        | ACTIVE TOPICS
                        |--------------------------------------------------------------------------
                        */

                        $activeContentIds = \App\Models\Topic::whereIn(
                            'chapter_id',
                            $activeChapterIds
                        )
                            ->where('status', 1)
                            ->pluck('id');
                    }

                    /*
                |--------------------------------------------------------------------------
                | TOTAL ACTIVE CONTENT
                |--------------------------------------------------------------------------
                */

                    $totalContent = $activeContentIds
                        ->count();

                    /*
                |--------------------------------------------------------------------------
                | COMPLETED CONTENT
                |--------------------------------------------------------------------------
                */

                    $progressQuery = \App\Models\UserProgress::where(
                        'user_id',
                        $userId
                    )
                        ->where(
                            $config['progress_fk'],
                            $entity->id
                        )
                        ->where('is_completed', true);

                    /*
                |--------------------------------------------------------------------------
                | EXTRA CONDITIONS
                |--------------------------------------------------------------------------
                */

                    if (
                        isset($config['extra_progress_conditions']) &&
                        is_callable($config['extra_progress_conditions'])
                    ) {

                        $progressQuery = $config['extra_progress_conditions'](
                            $progressQuery
                        );
                    } else {

                        $progressQuery->whereIn(
                            'topic_id',
                            $activeContentIds
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | FINAL COMPLETED COUNT
                |--------------------------------------------------------------------------
                */

                    $completedContent = $progressQuery
                        ->count();

                    /*
                |--------------------------------------------------------------------------
                | BLOCK ACCESS
                |--------------------------------------------------------------------------
                */

                    if (
                        $totalContent !==
                        $completedContent
                    ) {

                        return response()->json([
                            'message' => $config['message']
                        ], 422);
                    }
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 🔹 MAX ATTEMPTS
    |--------------------------------------------------------------------------
    */

        $examTypes = config(
            'assessment.exam.types',
            []
        );

        $isExam = in_array(
            $assessment->type,
            $examTypes,
            true
        );

        $maxAttempts = $isExam
            ? config('assessment.exam.max_attempts', 50)
            : config('assessment.quiz.max_attempts', 50);

        /*
    |--------------------------------------------------------------------------
    | 🔹 COMPLETED ATTEMPTS
    |--------------------------------------------------------------------------
    */

        $completedAttempts = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('assessment_id', $id)
            ->whereIn('status', [
                'passed',
                'failed'
            ])
            ->count();

        $remainingAttempts = max(
            0,
            $maxAttempts - $completedAttempts
        );

        /*
    |--------------------------------------------------------------------------
    | 🔁 ACTIVE ATTEMPT CHECK
    |--------------------------------------------------------------------------
    */

        $activeAttempt = AssessmentAttempt::where(
            'user_id',
            $userId
        )
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
                    ? $activeAttempt->started_at
                    ->copy()
                    ->addMinutes($assessment->duration)
                    : null,

                'total_attempts_allowed' => $maxAttempts,

                'attempts_used' => $completedAttempts,

                'attempts_remaining' => $remainingAttempts,
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | 🚫 LIMIT REACHED
    |--------------------------------------------------------------------------
    */

        if ($completedAttempts >= $maxAttempts) {

            return response()->json([

                'message' => 'Max attempts reached',

                'total_attempts_allowed' => $maxAttempts,

                'attempts_used' => $completedAttempts,

                'attempts_remaining' => 0,
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | ✅ CREATE ATTEMPT + QUESTIONS
    |--------------------------------------------------------------------------
    */

        $attempt = DB::transaction(function () use (

            $userId,
            $id,
            $assessment

        ) {

            /*
        |--------------------------------------------------------------------------
        | CREATE ATTEMPT
        |--------------------------------------------------------------------------
        */

            $attempt = AssessmentAttempt::create([

                'user_id' => $userId,

                'assessment_id' => $id,

                'started_at' => now(),

                'status' => 'in_progress'
            ]);

            /*
        |--------------------------------------------------------------------------
        | GENERATE QUESTION SET
        |--------------------------------------------------------------------------
        */

            $selectedQuestionIds = app(
                \App\Modules\Trainee\Assessment\Services\QuestionSelectionService::class
            )->generate(
                $assessment,
                $userId
            );

            /*
        |--------------------------------------------------------------------------
        | NO QUESTIONS
        |--------------------------------------------------------------------------
        */

            if (empty($selectedQuestionIds)) {

                throw new \Exception(
                    'No questions available for this assessment'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | STORE ATTEMPT QUESTIONS
        |--------------------------------------------------------------------------
        */

            foreach ($selectedQuestionIds as $questionId) {

                \App\Models\AssessmentAttemptQuestion::create([

                    'attempt_id' => $attempt->id,

                    'question_id' => $questionId
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | TEMP ATTRIBUTE
        |--------------------------------------------------------------------------
        */

            $attempt->selected_question_ids =
                $selectedQuestionIds;

            return $attempt;
        });

        /*
    |--------------------------------------------------------------------------
    | 👤 USER
    |--------------------------------------------------------------------------
    */

        $user = auth()->user();

        /*
    |--------------------------------------------------------------------------
    | 🔔 USER NOTIFICATION
    |--------------------------------------------------------------------------
    */

        if ($user) {

            app(\App\Services\NotificationService::class)->send(

                $user,

                'ASSESSMENT_STARTED',

                [

                    'title' => 'Assessment Started',

                    'message' => 'You started an assessment',

                    'screen' => 'AssessmentQuestions',

                    'id' => $assessment->id,

                    'meta' => [

                        'assessment_id' => $assessment->id,

                        'assessment_type' => $assessment->type,

                        'attempt_id' => $attempt->id,
                    ]
                ],

                ['db', 'push']
            );

            /*
        |--------------------------------------------------------------------------
        | 🛡 ADMIN PAYLOAD
        |--------------------------------------------------------------------------
        */

            $adminPayload = [

                'title' => 'Assessment Started',

                'message' => "{$user->name} started an assessment",

                'screen' => 'AssessmentReview',

                'id' => $assessment->id,

                'meta' => [

                    'user_id' => $user->id,

                    'user_name' => $user->name,

                    'assessment_id' => $assessment->id,

                    'assessment_type' => $assessment->type,

                    'attempt_id' => $attempt->id,
                ]
            ];

            /*
        |--------------------------------------------------------------------------
        | 🛡 ADMINS
        |--------------------------------------------------------------------------
        */

            app(\App\Services\NotificationService::class)
                ->sendToRole(
                    'admin',
                    'ASSESSMENT_STARTED',
                    $adminPayload,
                    ['db', 'push']
                );

            /*
        |--------------------------------------------------------------------------
        | 👑 SUPER ADMINS
        |--------------------------------------------------------------------------
        */

            app(\App\Services\NotificationService::class)
                ->sendToRole(
                    'superadmin',
                    'ASSESSMENT_STARTED',
                    $adminPayload,
                    ['db', 'push']
                );
        }

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'message' => 'New attempt started',

            'attempt_id' => $attempt->id,

            'type' => $assessment->type,

            'duration' => $assessment->duration,

            'started_at' => $attempt->started_at,

            'expires_at' => $assessment->duration
                ? $attempt->started_at
                ->copy()
                ->addMinutes($assessment->duration)
                : null,

            'question_count' => count(
                $attempt->selected_question_ids
            ),

            'total_attempts_allowed' => $maxAttempts,

            'attempts_used' => $completedAttempts,

            'attempts_remaining' => $remainingAttempts,
        ]);
    }

    // 🔹 QUESTIONS
    public function questions($id, Request $request)
    {
        $attemptId = $request->attempt_id;

        /*
        |--------------------------------------------------------------------------
        | 🔹 ATTEMPT
        |--------------------------------------------------------------------------
        */

        $attempt = AssessmentAttempt::with([

            'assessment',

            'attemptQuestions.question.options'

        ])->findOrFail($attemptId);

        $assessment = $attempt->assessment;

        /*
        |--------------------------------------------------------------------------
        | 🔹 ANSWERS
        |--------------------------------------------------------------------------
        */

        $answers = AssessmentAnswer::where(
            'attempt_id',
            $attemptId
        )
            ->get()
            ->keyBy('question_id');

        /*
        |--------------------------------------------------------------------------
        | 🔹 QUESTIONS TRANSFORM
        |--------------------------------------------------------------------------
        */

        $questions = $attempt->attemptQuestions
            ->map(function ($attemptQuestion) use ($answers) {

                $q = $attemptQuestion->question;

                return [

                    'id' => $q->id,

                    'question_text' => $q->question_text,

                    'file' => $q->file,

                    /*
                    |--------------------------------------------------------------------------
                    | 🆕 CASE STUDY
                    |--------------------------------------------------------------------------
                    */

                    'is_case' => (bool) $q->is_case,

                    'case_title' => $q->case_title,

                    'case_text' => $q->case_text,

                    'case_order' => $q->case_order,

                    /*
                    |--------------------------------------------------------------------------
                    | OPTIONS
                    |--------------------------------------------------------------------------
                    */

                    'options' => $q->options->map(fn($opt) => [

                        'id' => $opt->id,

                        'text' => $opt->option_text
                    ]),

                    /*
                    |--------------------------------------------------------------------------
                    | SELECTED OPTION
                    |--------------------------------------------------------------------------
                    */

                    'selected_option_id' =>
                    $answers[$q->id]
                        ->selected_option_id ?? null
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 🔹 ATTEMPT PROGRESS STATS
        |--------------------------------------------------------------------------
        */

        $totalQuestions = $questions->count();

        $answeredCount = $answers->filter(function ($a) {

            return !is_null($a->selected_option_id);
        })->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |--------------------------------------------------------------------------
        | 🔹 HIERARCHY CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = null;

        /*
        |--------------------------------------------------------------------------
        | TOPIC
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Topic::class
        ) {

            $topic = \App\Models\Topic::with([
                'chapter.module.level.program'
            ])->find($assessment->assessmentable_id);

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

        /*
        |--------------------------------------------------------------------------
        | CHAPTER
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Chapter::class
        ) {

            $chapter = \App\Models\Chapter::with([
                'module.level.program'
            ])->find($assessment->assessmentable_id);

            if ($chapter) {

                $context = [

                    'type' => 'chapter',

                    'chapter' => [
                        'id' => $chapter->id,
                        'title' => $chapter->title,
                    ],

                    'module' => [
                        'id' => $chapter->module->id ?? null,
                        'title' => $chapter->module->title ?? null,
                    ],

                    'level' => [
                        'id' => $chapter->module->level->id ?? null,
                        'title' => $chapter->module->level->title ?? null,
                    ],

                    'program' => [
                        'id' => $chapter->module->level->program->id ?? null,
                        'title' => $chapter->module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Module::class
        ) {

            $module = \App\Models\Module::with([
                'level.program'
            ])->find($assessment->assessmentable_id);

            if ($module) {

                $context = [

                    'type' => 'module',

                    'module' => [
                        'id' => $module->id,
                        'title' => $module->title,
                    ],

                    'level' => [
                        'id' => $module->level->id ?? null,
                        'title' => $module->level->title ?? null,
                    ],

                    'program' => [
                        'id' => $module->level->program->id ?? null,
                        'title' => $module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | LEVEL
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Level::class
        ) {

            $level = \App\Models\Level::with([
                'program'
            ])->find($assessment->assessmentable_id);

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
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'attempt_id' => $attemptId,

            'type' => $assessment->type,

            'duration' => $assessment->duration,

            'started_at' => $attempt->started_at,

            'expires_at' => $assessment->duration
                ? $attempt->started_at
                ->copy()
                ->addMinutes($assessment->duration)
                : null,

            /*
            |--------------------------------------------------------------------------
            | 🆕 ATTEMPT PROGRESS
            |--------------------------------------------------------------------------
            */

            'total_questions' => $totalQuestions,

            'answered_questions' => $answeredCount,

            'remaining_questions' => $remainingCount,

            /*
            |--------------------------------------------------------------------------
            | 🆕 CONTEXT
            |--------------------------------------------------------------------------
            */

            'context' => $context,

            /*
            |--------------------------------------------------------------------------
            | QUESTIONS
            |--------------------------------------------------------------------------
            */

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

        /*
        |--------------------------------------------------------------------------
        | 🔹 ATTEMPT
        |--------------------------------------------------------------------------
        */

        $attempt = AssessmentAttempt::with([
            'assessment',
            'attemptQuestions'
        ])->findOrFail(
            $request->attempt_id
        );

        /*
        |--------------------------------------------------------------------------
        | 🔒 ATTEMPT STATUS
        |--------------------------------------------------------------------------
        */

        if ($attempt->status !== 'in_progress') {

            return response()->json([
                'message' => 'Attempt already submitted or expired'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔒 TIME CHECK
        |--------------------------------------------------------------------------
        */

        if ($attempt->assessment->duration) {

            $expire = $attempt->started_at
                ->copy()
                ->addMinutes(
                    $attempt->assessment->duration
                );

            if (now()->greaterThan($expire)) {

                return response()->json([
                    'message' => 'Time expired. Cannot answer.'
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 🔒 QUESTION BELONGS TO ATTEMPT
        |--------------------------------------------------------------------------
        */

        $isAssigned = $attempt->attemptQuestions
            ->where(
                'question_id',
                $request->question_id
            )
            ->count();

        if (! $isAssigned) {

            return response()->json([
                'message' => 'Invalid question for this attempt'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 QUESTION
        |--------------------------------------------------------------------------
        */

        $question = \App\Models\AssessmentQuestion::with(
            'options'
        )->findOrFail(
            $request->question_id
        );

        /*
        |--------------------------------------------------------------------------
        | 🔒 OPTION BELONGS TO QUESTION
        |--------------------------------------------------------------------------
        */

        if ($request->selected_option_id) {

            $validOption = $question->options
                ->where(
                    'id',
                    $request->selected_option_id
                )
                ->first();

            if (! $validOption) {

                return response()->json([
                    'message' => 'Invalid option selected'
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 OPTION SNAPSHOT
        |--------------------------------------------------------------------------
        */

        $options = $question->options
            ->map(fn($opt) => [

                'id' => $opt->id,

                'text' => $opt->option_text
            ]);

        /*
        |--------------------------------------------------------------------------
        | 🔹 CORRECT OPTION
        |--------------------------------------------------------------------------
        */

        $correct = $question->options
            ->where('is_correct', true)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 💾 SAVE ANSWER
        |--------------------------------------------------------------------------
        */

        return AssessmentAnswer::updateOrCreate(

            [
                'attempt_id' => $request->attempt_id,

                'question_id' => $request->question_id
            ],

            [
                'question_text_snapshot' =>
                $question->question_text,

                'options_snapshot' => $options,

                'correct_option_id_snapshot' =>
                $correct->id ?? null,

                'marks_snapshot' => $question->marks,

                'selected_option_id' =>
                $request->selected_option_id,
            ]
        );
    }



    // 🔹 RESUME
    public function resume($id)
    {
        $userId = auth()->id();

        /*
        |--------------------------------------------------------------------------
        | 🔹 ATTEMPT
        |--------------------------------------------------------------------------
        */

        $attempt = AssessmentAttempt::with([

            'assessment',

            'attemptQuestions.question.options',

            'answers'

        ])
            ->where('user_id', $userId)
            ->where('assessment_id', $id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | ❌ NO ACTIVE ATTEMPT
        |--------------------------------------------------------------------------
        */

        if (! $attempt) {

            return response()->json([
                'message' => 'No active attempt'
            ], 404);
        }

        $assessment = $attempt->assessment;

        /*
        |--------------------------------------------------------------------------
        | ⏱ TIME
        |--------------------------------------------------------------------------
        */

        $duration = $assessment->duration ?? null;

        $expiresAt = $duration
            ? $attempt->started_at
            ->copy()
            ->addMinutes($duration)
            : null;

        /*
        |--------------------------------------------------------------------------
        | 🔹 ANSWERS MAP
        |--------------------------------------------------------------------------
        */

        $answersMap = $attempt->answers
            ->keyBy('question_id');

        /*
        |--------------------------------------------------------------------------
        | 🔹 QUESTIONS
        |--------------------------------------------------------------------------
        */

        $questions = $attempt->attemptQuestions
            ->map(function ($attemptQuestion) use ($answersMap) {

                $q = $attemptQuestion->question;

                return [

                    'id' => $q->id,

                    'question_text' => $q->question_text,

                    'file' => $q->file,

                    /*
                    |--------------------------------------------------------------------------
                    | 🆕 CASE STUDY
                    |--------------------------------------------------------------------------
                    */

                    'is_case' => (bool) $q->is_case,

                    'case_title' => $q->case_title,

                    'case_text' => $q->case_text,

                    'case_order' => $q->case_order,

                    /*
                    |--------------------------------------------------------------------------
                    | OPTIONS
                    |--------------------------------------------------------------------------
                    */

                    'options' => $q->options->map(fn($opt) => [

                        'id' => $opt->id,

                        'text' => $opt->option_text
                    ]),

                    /*
                    |--------------------------------------------------------------------------
                    | SELECTED OPTION
                    |--------------------------------------------------------------------------
                    */

                    'selected_option_id' =>
                    $answersMap[$q->id]
                        ->selected_option_id ?? null
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 🔹 ATTEMPT PROGRESS
        |--------------------------------------------------------------------------
        */

        $totalQuestions = $questions->count();

        $answeredCount = $attempt->answers
            ->filter(function ($a) {

                return ! is_null(
                    $a->selected_option_id
                );
            })
            ->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |--------------------------------------------------------------------------
        | 🔹 CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = null;

        /*
        |--------------------------------------------------------------------------
        | TOPIC
        |--------------------------------------------------------------------------
            */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Topic::class
        ) {

            $topic = \App\Models\Topic::with(
                'chapter.module.level.program'
            )->find(
                $assessment->assessmentable_id
            );

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

        /*
        |--------------------------------------------------------------------------
        | CHAPTER
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Chapter::class
        ) {

            $chapter = \App\Models\Chapter::with(
                'module.level.program'
            )->find(
                $assessment->assessmentable_id
            );

            if ($chapter) {

                $context = [

                    'type' => 'chapter',

                    'chapter' => [

                        'id' => $chapter->id,

                        'title' => $chapter->title,
                    ],

                    'module' => [

                        'id' => $chapter->module->id ?? null,

                        'title' => $chapter->module->title ?? null,
                    ],

                    'level' => [

                        'id' => $chapter->module->level->id ?? null,

                        'title' => $chapter->module->level->title ?? null,
                    ],

                    'program' => [

                        'id' => $chapter->module->level->program->id ?? null,

                        'title' => $chapter->module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Module::class
        ) {

            $module = \App\Models\Module::with(
                'level.program'
            )->find(
                $assessment->assessmentable_id
            );

            if ($module) {

                $context = [

                    'type' => 'module',

                    'module' => [

                        'id' => $module->id,

                        'title' => $module->title,
                    ],

                    'level' => [

                        'id' => $module->level->id ?? null,

                        'title' => $module->level->title ?? null,
                    ],

                    'program' => [

                        'id' => $module->level->program->id ?? null,

                        'title' => $module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | LEVEL
        |--------------------------------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Level::class
        ) {

            $level = \App\Models\Level::with(
                'program'
            )->find(
                $assessment->assessmentable_id
            );

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
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'attempt_id' => $attempt->id,

            'type' => $assessment->type,

            'started_at' => $attempt->started_at,

            'expires_at' => $expiresAt,

            /*
            |--------------------------------------------------------------------------
            | 🆕 ATTEMPT STATS
            |--------------------------------------------------------------------------
            */

            'total_questions' => $totalQuestions,

            'answered_questions' => $answeredCount,

            'remaining_questions' => $remainingCount,

            /*
            |--------------------------------------------------------------------------
            | 🆕 CONTEXT
            |--------------------------------------------------------------------------
            */

            'context' => $context,

            /*
            |--------------------------------------------------------------------------
            | 🆕 QUESTIONS
            |--------------------------------------------------------------------------
            */

            'questions' => $questions,

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL RAW ANSWERS
            |--------------------------------------------------------------------------
            */

            'answers' => $attempt->answers
        ]);
    }


    public function submit($id, Request $request)
    {
        AuditService::log(
            'assessment_submitted',
            'User submitted an assessment',
            [
                'assessment_id' => $id
            ]
        );

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

        /*
        |--------------------------------------------------
        | 📦 ATTEMPT
        |--------------------------------------------------
        */
        $attempt = AssessmentAttempt::with([
            'answers',
            'attemptQuestions.question',
            'assessment.assessmentable'
        ])
            ->where('id', $request->attempt_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        /*
        |--------------------------------------------------
        | ❌ ALREADY SUBMITTED
        |--------------------------------------------------
        */
        if ($attempt->status !== 'in_progress') {

            return response()->json([
                'message' => 'Already submitted'
            ], 422);
        }

        $assessment = $attempt->assessment;

        /*
        |--------------------------------------------------
        | 🔒 ASSESSMENT VALIDATION
        |--------------------------------------------------
        */
        if ((int) $assessment->id !== (int) $id) {

            return response()->json([
                'message' => 'Assessment mismatch'
            ], 422);
        }

        /*
        |--------------------------------------------------
        | 🔒 VALID ASSESSMENT TYPE
        |--------------------------------------------------
        */
        $allowedTypes = array_merge(
            config('assessment.quiz.types', []),
            config('assessment.exam.types', [])
        );

        if (!in_array($assessment->type, $allowedTypes, true)) {

            return response()->json([
                'message' => 'Invalid assessment type'
            ], 422);
        }

        /*
        |--------------------------------------------------
        | ⏱ SUBMIT TYPE
        |--------------------------------------------------
        */
        $submitType = $request->submit_type ?? 'manual';

        if ($assessment->duration) {

            $expire = $attempt->started_at
                ->copy()
                ->addMinutes($assessment->duration);

            if (now()->greaterThan($expire)) {
                $submitType = 'timeout';
            }
        }

        /*
        |--------------------------------------------------
        | 📊 QUESTION STATS
        |--------------------------------------------------
        */
        $totalQuestions = $attempt->attemptQuestions->count();

        $answeredCount = $attempt->answers
            ->whereNotNull('selected_option_id')
            ->count();

        $remainingCount = $totalQuestions - $answeredCount;

        /*
        |--------------------------------------------------
        | ⏱ TIME TAKEN
        |--------------------------------------------------
        */
        if ($request->filled('time_taken_seconds')) {

            $timeTaken = max(
                0,
                (int) $request->time_taken_seconds
            );
        } else {

            $startedAt = Carbon::parse($attempt->started_at);

            $timeTaken = $startedAt->diffInSeconds(now());
        }

        /*
        |--------------------------------------------------
        | 📊 ATTEMPT LIMIT
        |--------------------------------------------------
        */
        $examTypes = config(
            'assessment.exam.types',
            ['module']
        );

        $isExam = in_array(
            $assessment->type,
            $examTypes,
            true
        );

        $maxAttempts = $isExam
            ? config('assessment.exam.max_attempts', 50)
            : config('assessment.quiz.max_attempts', 50);

        $completedAttempts = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('assessment_id', $assessment->id)
            ->whereIn('status', ['passed', 'failed'])
            ->count();

        /*
        |--------------------------------------------------
        | 🧠 HIERARCHY BUILD
        |--------------------------------------------------
        */
        $context = null;

        /*
        |--------------------------------------------------
        | TOPIC
        |--------------------------------------------------
        */
        if ($assessment->assessmentable_type === Topic::class) {

            $topic = Topic::with(
                'chapter.module.level.program'
            )->find($assessment->assessmentable_id);

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

        /*
        |--------------------------------------------------
        | CHAPTER
        |--------------------------------------------------
        */ elseif ($assessment->assessmentable_type === Chapter::class) {

            $chapter = Chapter::with(
                'module.level.program'
            )->find($assessment->assessmentable_id);

            if ($chapter) {

                $context = [

                    'type' => 'chapter',

                    'chapter' => [
                        'id' => $chapter->id,
                        'title' => $chapter->title,
                    ],

                    'module' => [
                        'id' => $chapter->module->id ?? null,
                        'title' => $chapter->module->title ?? null,
                    ],

                    'level' => [
                        'id' => $chapter->module->level->id ?? null,
                        'title' => $chapter->module->level->title ?? null,
                    ],

                    'program' => [
                        'id' => $chapter->module->level->program->id ?? null,
                        'title' => $chapter->module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------
        | MODULE
        |--------------------------------------------------
        */ elseif ($assessment->assessmentable_type === Module::class) {

            $module = Module::with(
                'level.program'
            )->find($assessment->assessmentable_id);

            if ($module) {

                $context = [

                    'type' => 'module',

                    'module' => [
                        'id' => $module->id,
                        'title' => $module->title,
                    ],

                    'level' => [
                        'id' => $module->level->id ?? null,
                        'title' => $module->level->title ?? null,
                    ],

                    'program' => [
                        'id' => $module->level->program->id ?? null,
                        'title' => $module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------
        | LEVEL
        |--------------------------------------------------
        */ elseif ($assessment->assessmentable_type === Level::class) {

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

            /*
            |--------------------------------------------------
            | 🔍 EVALUATE
            |--------------------------------------------------
            */
            $result = $this->service
                ->evaluateAttempt($attempt);

            $percentage = (float) $result['percentage'];

            $passingPercentage = (float) $assessment->passing_score;

            $isPassed = $percentage >= $passingPercentage;

            /*
            |--------------------------------------------------
            | 💾 UPDATE ATTEMPT
            |--------------------------------------------------
            */
            $attempt->update([

                'score' => $result['marks'],

                'percentage' => $result['percentage'],

                'submitted_at' => now(),

                'status' => $isPassed
                    ? 'passed'
                    : 'failed',

                'time_taken' => $timeTaken,

                'submit_type' => $submitType
            ]);

            /*
            |--------------------------------------------------
            | 👤 USER
            |--------------------------------------------------
            */
            $user = auth()->user();

            /*
            |--------------------------------------------------
            | 🔔 USER NOTIFICATION
            |--------------------------------------------------
            */
            if ($user) {

                app(\App\Services\NotificationService::class)
                    ->send(

                        $user,

                        'ASSESSMENT_COMPLETED',

                        [
                            'title' => $isPassed
                                ? 'Assessment Passed'
                                : 'Assessment Failed',

                            'message' => $isPassed
                                ? 'You passed the assessment successfully'
                                : 'You failed the assessment',

                            'screen' => 'AssessmentResult',

                            'id' => $assessment->id,

                            'meta' => [

                                'assessment_id' => $assessment->id,

                                'assessment_type' => $assessment->type,

                                'attempt_id' => $attempt->id,

                                'score' => $result['marks'],

                                'percentage' => $result['percentage'],

                                'status' => $attempt->status,
                            ]
                        ],

                        ['db', 'push']
                    );

                $adminPayload = [

                    'title' => $isPassed
                        ? 'Assessment Passed'
                        : 'Assessment Failed',

                    'message' => $isPassed
                        ? "{$user->name} passed an assessment"
                        : "{$user->name} failed an assessment",

                    'screen' => 'AssessmentReview',

                    'id' => $assessment->id,

                    'meta' => [

                        'user_id' => $user->id,

                        'user_name' => $user->name,

                        'assessment_id' => $assessment->id,

                        'assessment_type' => $assessment->type,

                        'attempt_id' => $attempt->id,

                        'score' => $result['marks'],

                        'percentage' => $result['percentage'],

                        'status' => $attempt->status,
                    ]
                ];

                app(\App\Services\NotificationService::class)
                    ->sendToRole(
                        'admin',
                        'ASSESSMENT_COMPLETED',
                        $adminPayload,
                        ['db', 'push']
                    );

                app(\App\Services\NotificationService::class)
                    ->sendToRole(
                        'superadmin',
                        'ASSESSMENT_COMPLETED',
                        $adminPayload,
                        ['db', 'push']
                    );
            }

            /*
            |--------------------------------------------------
            | 🎓 CERTIFICATE
            |--------------------------------------------------
            */
            $certificate = null;

            /*
            |--------------------------------------------------
            | 🔓 PROGRESSION
            |--------------------------------------------------
            */
            if ($isPassed) {

                $progressionService = app(
                    ProgressionService::class
                );

                $assessmentType = $assessment->type;

                $assessmentable = $assessment->assessmentable;

                /*
                |--------------------------------------------------
                | TOPIC COMPLETION FLOW
                |--------------------------------------------------
                */
                if (
                    $assessmentType === 'topic' &&
                    $assessmentable instanceof Topic
                ) {

                    $progressionService
                        ->handleTopicCompletion(
                            $userId,
                            $assessmentable
                        );
                }

                /*
                |--------------------------------------------------
                | 🎓 DYNAMIC CERTIFICATE FLOW
                |--------------------------------------------------
                */
                $enabledCertificateTypes = config(
                    'assessment.certification.enabled_for_assessment_types',
                    []
                );

                if (
                    $assessmentable &&
                    in_array(
                        $assessmentType,
                        $enabledCertificateTypes,
                        true
                    )
                ) {

                    $certificate = app(
                        \App\Services\CertificationService::class
                    )->generate(

                        auth()->user(),

                        $assessmentable,

                        $attempt,

                        $assessmentType
                    );
                }

                /*
                |--------------------------------------------------
                | 🔓 DYNAMIC ASSESSMENT PASS FLOW
                |--------------------------------------------------
                */
                $assessmentCertificate = $progressionService
                    ->handleAssessmentPass(
                        $userId,
                        $assessment,
                        $attempt
                    );

                if (
                    $assessmentCertificate &&
                    !$certificate
                ) {

                    $certificate = $assessmentCertificate;
                }
            }

            /*
            |--------------------------------------------------
            | 📦 FINAL RESPONSE
            |--------------------------------------------------
            */
            return response()->json([

                'score' => $result['marks'],

                'obtained_marks' => $result['marks'],

                'total_marks' => $result['total_marks'],

                'passing_marks' => $assessment->passing_score,

                'total' => $totalQuestions,

                'percentage' => $result['percentage'],

                'correct' => $result['correct'],

                'wrong' => $result['wrong'],

                'skipped' => $result['skipped'],

                'status' => $attempt->status,

                'attempt_id' => $attempt->id,

                'type' => $assessment->type,

                'total_attempts_allowed' => $maxAttempts,

                'attempts_used' => $completedAttempts + 1,

                'attempts_remaining' => max(
                    0,
                    $maxAttempts - ($completedAttempts + 1)
                ),

                'answered_questions' => $answeredCount,

                'remaining_questions' => $remainingCount,

                'submit_type' => $submitType,

                'time_taken_seconds' => $timeTaken,

                'time_taken_minutes' => round(
                    $timeTaken / 60,
                    2
                ),

                'context' => $context,

                'certificate_generated' => $certificate
                    ? true
                    : false,

                'certificate_id' => $certificate->certificate_id ?? null,
            ]);
        });
    }
}
