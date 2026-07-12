<?php

namespace App\Modules\Admin\Dashboard\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Level;
use App\Models\Module;
use App\Models\Program;
use App\Models\Topic;
use App\Models\TopicContent;
use App\Models\User;
use App\Models\UserContentProgress;
use App\Models\UserProgress;
use App\Services\HierarchyVisibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class DashboardService {
    protected $visibilityService;

    public function __construct(
        HierarchyVisibilityService $visibilityService
    ) {
        $this->visibilityService = $visibilityService;
    }

    public function getDashboard() {
        return [

            /*
            |--------------------------------------------------------------------------
            | 🔹 EXECUTIVE OVERVIEW
            |--------------------------------------------------------------------------
            */

            'overview' => $this->getOverview(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 LEARNING FUNNEL
            |--------------------------------------------------------------------------
            */

            'learning_funnel' => $this->getLearningFunnel(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 ENGAGEMENT ANALYTICS
            |--------------------------------------------------------------------------
            */

            'engagement' => $this->getEngagementAnalytics(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 CONTENT GOVERNANCE
            |--------------------------------------------------------------------------
            */

            'publishing_pipeline' => $this->getPublishingPipeline(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 ASSESSMENT ANALYTICS
            |--------------------------------------------------------------------------
            */

            'assessment_analytics' => $this->getAssessmentAnalytics(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 CERTIFICATION ANALYTICS
            |--------------------------------------------------------------------------
            */

            'certification_analytics' => $this->getCertificationAnalytics(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 PROGRAM ANALYTICS
            |--------------------------------------------------------------------------
            */

            'program_analytics' => $this->getProgramAnalytics(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 RISK INDICATORS
            |--------------------------------------------------------------------------
            */

            'risk_indicators' => $this->getRiskIndicators(),

            /*
            |--------------------------------------------------------------------------
            | 🔹 TOP PERFORMERS
            |--------------------------------------------------------------------------
            */

            'top_performers' => $this->getTopPerformers(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 OVERVIEW
    |--------------------------------------------------------------------------
    */

    private function getOverview() {
        return [

            /*
            |--------------------------------------------------------------------------
            | USERS
            |--------------------------------------------------------------------------
            */

            'total_users' => User::count(),

            'active_users' => User::where(
                'is_active',
                true
            )->count(),

            'inactive_users' => User::where(
                'is_active',
                false
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | LMS STRUCTURE
            |--------------------------------------------------------------------------
            */

            'total_programs' => Program::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_levels' => Level::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_modules' => Module::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_chapters' => Chapter::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_topics' => Topic::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_contents' => TopicContent::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->where(
                    'publish_status',
                    'published'
                )
                ->count(),

            /*
            |--------------------------------------------------------------------------
            | LEARNING ENGINE
            |--------------------------------------------------------------------------
            */

            'total_assessments' => Assessment::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'total_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 LEARNING FUNNEL
    |--------------------------------------------------------------------------
    */

    private function getLearningFunnel() {
        return [

            'not_started_users' => User::whereDoesntHave(
                'progress'
            )->count(),

            'started_users' => UserProgress::whereHas(
                'topic',
                function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                }
            )
                ->distinct('user_id')
                ->count('user_id'),

            'in_progress_users' => UserProgress::where(
                'is_unlocked',
                true
            )
                ->where('is_completed', false)

                ->whereHas('topic', function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                })

                ->distinct('user_id')
                ->count('user_id'),

            'completed_users' => UserProgress::where(
                'is_completed',
                true
            )

                ->whereHas('topic', function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                })

                ->distinct('user_id')
                ->count('user_id'),

            'certified_users' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 ENGAGEMENT ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getEngagementAnalytics() {
        return [

            'daily_active_users' => UserProgress::whereDate(
                'updated_at',
                today()
            )

                ->whereHas('topic', function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                })

                ->distinct('user_id')
                ->count('user_id'),

            'weekly_active_users' => UserProgress::where(
                'updated_at',
                '>=',
                now()->subDays(7)
            )

                ->whereHas('topic', function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                })

                ->distinct('user_id')
                ->count('user_id'),

            'monthly_active_users' => UserProgress::where(
                'updated_at',
                '>=',
                now()->subDays(30)
            )

                ->whereHas('topic', function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                })

                ->distinct('user_id')
                ->count('user_id'),

            'content_reads_today' => UserContentProgress::whereDate(
                'updated_at',
                today()
            )->count(),

            'total_content_reads' => UserContentProgress::count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 CONTENT GOVERNANCE
    |--------------------------------------------------------------------------
    */

    private function getPublishingPipeline() {
        return [

            'programs' => $this->getPublishStats(
                Program::class
            ),

            'levels' => $this->getPublishStats(
                Level::class
            ),

            'modules' => $this->getPublishStats(
                Module::class
            ),

            'chapters' => $this->getPublishStats(
                Chapter::class
            ),

            'topics' => $this->getPublishStats(
                Topic::class
            ),

            'contents' => $this->getPublishStats(
                TopicContent::class
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 ASSESSMENT ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getAssessmentAnalytics() {
        $totalAttempts = AssessmentAttempt::whereHas(
            'assessment',
            function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            }
        )->count();

        $passedAttempts = AssessmentAttempt::where(
            'status',
            'passed'
        )

            ->whereHas('assessment', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->count();

        $failedAttempts = AssessmentAttempt::where(
            'status',
            'failed'
        )

            ->whereHas('assessment', function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->count();

        return [

            'total_attempts' => $totalAttempts,

            'passed_attempts' => $passedAttempts,

            'failed_attempts' => $failedAttempts,

            'pass_rate' => $totalAttempts > 0
                ? round(
                    (
                        $passedAttempts
                        / $totalAttempts
                    ) * 100,
                    2
                )
                : 0,

            'fail_rate' => $totalAttempts > 0
                ? round(
                    (
                        $failedAttempts
                        / $totalAttempts
                    ) * 100,
                    2
                )
                : 0,

            'avg_score' => round(

                AssessmentAttempt::whereHas(
                    'assessment',
                    function ($q) {

                        $q->where('status', true)
                            ->whereNull('deleted_at');
                    }
                )->avg('percentage') ?? 0,

                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Topic Quiz Avg
            |--------------------------------------------------------------------------
            */

            'topic_quiz_avg' => round(

                AssessmentAttempt::whereHas(
                    'assessment',
                    function ($q) {

                        $q->where('type', 'topic')
                            ->where('status', true)
                            ->whereNull('deleted_at');
                    }
                )->avg('percentage') ?? 0,

                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Chapter Exam Avg
            |--------------------------------------------------------------------------
            */

            'chapter_exam_avg' => round(

                AssessmentAttempt::whereHas(
                    'assessment',
                    function ($q) {

                        $q->where('type', 'chapter')
                            ->where('status', true)
                            ->whereNull('deleted_at');
                    }
                )->avg('percentage') ?? 0,

                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Module Exam Avg
            |--------------------------------------------------------------------------
            */

            'module_exam_avg' => round(

                AssessmentAttempt::whereHas(
                    'assessment',
                    function ($q) {

                        $q->where('type', 'module')
                            ->where('status', true)
                            ->whereNull('deleted_at');
                    }
                )->avg('percentage') ?? 0,

                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Level Exam Avg
            |--------------------------------------------------------------------------
            */

            'level_exam_avg' => round(

                AssessmentAttempt::whereHas(
                    'assessment',
                    function ($q) {

                        $q->where('type', 'level')
                            ->where('status', true)
                            ->whereNull('deleted_at');
                    }
                )->avg('percentage') ?? 0,

                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Most Failed Assessments
            |--------------------------------------------------------------------------
            */

            'most_failed_assessments' => Assessment::where(
                'status',
                true
            )
                ->whereNull('deleted_at')

                ->withCount([

                    'attempts as fail_count' => function ($q) {

                        $q->where(
                            'status',
                            'failed'
                        );
                    },
                ])

                ->orderByDesc('fail_count')

                ->limit(10)

                ->get([
                    'id',
                    'title',
                    'type',
                    'passing_score',
                    'total_marks',
                ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 CERTIFICATION ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getCertificationAnalytics() {
        return [

            'total_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->count(),

            'topic_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->where('type', 'topic')
                ->count(),

            'chapter_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->where('type', 'chapter')
                ->count(),

            'module_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->where('type', 'module')
                ->count(),

            'level_certificates' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->where('type', 'level')
                ->count(),

            'certificates_issued_today' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->whereDate(
                    'issued_at',
                    today()
                )
                ->count(),

            'certificates_issued_this_month' => Certification::where(
                'status',
                true
            )
                ->whereNull('deleted_at')
                ->whereMonth(
                    'issued_at',
                    now()->month
                )
                ->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 PROGRAM ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getProgramAnalytics() {
        return Program::with([

            'levels.modules.chapters.topics.contents',

        ])

            ->where('status', true)
            ->whereNull('deleted_at')

            ->get()

            ->map(function ($program) {

                $levels = $program->levels
                    ->where('status', true)
                    ->whereNull('deleted_at');

                $modules = $levels
                    ->flatMap->modules
                    ->where('status', true)
                    ->whereNull('deleted_at');

                $chapters = $modules
                    ->flatMap->chapters
                    ->where('status', true)
                    ->whereNull('deleted_at');

                $topics = $chapters
                    ->flatMap->topics
                    ->where('status', true)
                    ->whereNull('deleted_at');

                $contents = $topics
                    ->flatMap->contents
                    ->where('status', true)
                    ->whereNull('deleted_at');

                $topicIds = $topics
                    ->pluck('id');

                return [

                    'id' => $program->id,

                    'title' => $program->title,

                    /*
                    |--------------------------------------------------------------------------
                    | STRUCTURE
                    |--------------------------------------------------------------------------
                    */

                    'structure' => [

                        'levels' => $levels->count(),

                        'modules' => $modules->count(),

                        'chapters' => $chapters->count(),

                        'topics' => $topics->count(),

                        'contents' => $contents->count(),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | PUBLISHING
                    |--------------------------------------------------------------------------
                    */

                    'publishing' => [

                        'published_topics' => $topics
                            ->where(
                                'publish_status',
                                'published'
                            )
                            ->count(),

                        'draft_topics' => $topics
                            ->where(
                                'publish_status',
                                'draft'
                            )
                            ->count(),

                        'unpublished_topics' => $topics
                            ->where(
                                'publish_status',
                                'unpublished'
                            )
                            ->count(),

                        'published_contents' => $contents
                            ->where(
                                'publish_status',
                                'published'
                            )
                            ->count(),

                        'draft_contents' => $contents
                            ->where(
                                'publish_status',
                                'draft'
                            )
                            ->count(),

                        'unpublished_contents' => $contents
                            ->where(
                                'publish_status',
                                'unpublished'
                            )
                            ->count(),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | LEARNING
                    |--------------------------------------------------------------------------
                    */

                    'learning' => [

                        'active_learners' => UserProgress::where(
                            'program_id',
                            $program->id
                        )
                            ->distinct(
                                'user_id'
                            )
                            ->count(
                                'user_id'
                            ),

                        'completed_topics' => UserProgress::where(
                            'program_id',
                            $program->id
                        )
                            ->where(
                                'is_completed',
                                true
                            )
                            ->count(),

                        'completion_rate' => $topicIds->count() > 0

                            ? round(
                                (
                                    UserProgress::where(
                                        'program_id',
                                        $program->id
                                    )
                                    ->where(
                                        'is_completed',
                                        true
                                    )
                                    ->count()
                                    / $topicIds->count()
                                ) * 100,
                                2
                            )

                            : 0,
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | ASSESSMENTS
                    |--------------------------------------------------------------------------
                    */

                    'assessment' => [

                        'topic_avg_score' => round(

                            AssessmentAttempt::whereHas(
                                'assessment',
                                function ($q) use (
                                    $topicIds
                                ) {

                                    $q->where(
                                        'type',
                                        'topic'
                                    )
                                        ->whereIn(
                                            'assessmentable_id',
                                            $topicIds
                                        )
                                        ->where(
                                            'status',
                                            true
                                        )
                                        ->whereNull(
                                            'deleted_at'
                                        );
                                }
                            )->avg('percentage') ?? 0,

                            2
                        ),

                        'total_attempts' => AssessmentAttempt::whereHas(
                            'assessment',
                            function ($q) use (
                                $topicIds
                            ) {

                                $q->where(
                                    'status',
                                    true
                                )
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->where(
                                        'type',
                                        'topic'
                                    )
                                    ->whereIn(
                                        'assessmentable_id',
                                        $topicIds
                                    );
                            }
                        )->count(),
                    ],
                ];
            });
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 RISK INDICATORS
    |--------------------------------------------------------------------------
    */

    private function getRiskIndicators() {
        return [

            /*
            |--------------------------------------------------------------------------
            | Low Performing Users
            |--------------------------------------------------------------------------
            */

            'low_performing_users' => AssessmentAttempt::whereHas(
                'assessment',
                function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                }
            )

                ->select(
                    'user_id',
                    DB::raw(
                        'AVG(percentage) as avg_score'
                    )
                )

                ->groupBy('user_id')

                ->havingRaw(
                    'AVG(percentage) < 40'
                )

                ->with(
                    'user:id,name,email'
                )

                ->limit(10)

                ->get(),

            /*
            |--------------------------------------------------------------------------
            | Inactive Users
            |--------------------------------------------------------------------------
            */

            'inactive_users_7_days' => User::whereDoesntHave(
                'progress',
                function ($q) {

                    $q->where(
                        'updated_at',
                        '>=',
                        now()->subDays(7)
                    );
                }
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | Draft Heavy Programs
            |--------------------------------------------------------------------------
            */

            'programs_with_high_drafts' => Program::withCount([

                'levels as draft_levels_count' => function ($q) {

                    $q->where(
                        'publish_status',
                        'draft'
                    );
                },
            ])

                ->whereNull('deleted_at')

                ->orderByDesc(
                    'draft_levels_count'
                )

                ->limit(5)

                ->get([
                    'id',
                    'title',
                ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 TOP PERFORMERS
    |--------------------------------------------------------------------------
    */

    private function getTopPerformers() {
        return AssessmentAttempt::whereHas(
            'assessment',
            function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            }
        )

            ->select(
                'user_id',
                DB::raw(
                    'AVG(percentage) as avg_score'
                )
            )

            ->where('status', 'passed')

            ->groupBy('user_id')

            ->orderByDesc('avg_score')

            ->limit(10)

            ->with('user:id,name,email')

            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 COMMON PUBLISH STATS
    |--------------------------------------------------------------------------
    */

    private function getPublishStats($model) {
        $instance = new $model;

        $table = $instance->getTable();

        $hasPublishStatus = Schema::hasColumn(
            $table,
            'publish_status'
        );

        /*
        |--------------------------------------------------------------------------
        | MODELS WITHOUT publish_status
        |--------------------------------------------------------------------------
        */

        if (! $hasPublishStatus) {

            return [

                'published' => $model::where(
                    'status',
                    true
                )
                    ->whereNull('deleted_at')
                    ->count(),

                'draft' => 0,

                'unpublished' => 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | MODELS WITH publish_status
        |--------------------------------------------------------------------------
        */

        return [

            'published' => $model::where(
                'publish_status',
                'published'
            )
                ->whereNull('deleted_at')
                ->count(),

            'draft' => $model::where(
                'publish_status',
                'draft'
            )
                ->whereNull('deleted_at')
                ->count(),

            'unpublished' => $model::where(
                'publish_status',
                'unpublished'
            )
                ->whereNull('deleted_at')
                ->count(),
        ];
    }


    public function contentHealth() {
        /*
    |--------------------------------------------------------------------------
    | TOPICS WITHOUT CONTENT
    |--------------------------------------------------------------------------
    */

        $topicsWithoutContent = Topic::select('id', 'title')
            ->doesntHave('contents')
            ->orderBy('id')
            ->get()
            ->map(function ($topic) {
                return [
                    'type'  => 'topic_content_missing',
                    'id'    => $topic->id,
                    'title' => $topic->title,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | TOPICS WITHOUT QUIZ
    |--------------------------------------------------------------------------
    */

        $topicQuizMissing = Topic::select('id', 'title')
            ->whereDoesntHave('assessments', function ($q) {
                $q->where('type', 'topic');
            })
            ->orderBy('id')
            ->get()
            ->map(function ($topic) {
                return [
                    'type'  => 'topic_quiz_missing',
                    'id'    => $topic->id,
                    'title' => $topic->title,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | TOPIC QUIZ WITHOUT QUESTIONS
    |--------------------------------------------------------------------------
    */

        $topicQuizWithoutQuestions = Assessment::with('assessmentable:id,title')
            ->where('assessmentable_type', Topic::class)
            ->where('type', 'topic')
            ->doesntHave('questions')
            ->orderBy('id')
            ->get()
            ->map(function ($assessment) {

                return [
                    'type'             => 'topic_quiz_without_questions',
                    'assessment_id'    => $assessment->id,
                    'assessment_title' => $assessment->title,
                    'topic_id'         => optional($assessment->assessmentable)->id,
                    'topic_title'      => optional($assessment->assessmentable)->title,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | TOPIC QUESTIONS WITHOUT OPTIONS
    |--------------------------------------------------------------------------
    */

        $topicQuestionsWithoutOptions = Assessment::with([
            'assessmentable:id,title',
            'questions' => function ($q) {
                $q->doesntHave('options');
            }
        ])
            ->where('assessmentable_type', Topic::class)
            ->where('type', 'topic')
            ->whereHas('questions', function ($q) {
                $q->doesntHave('options');
            })
            ->get()
            ->flatMap(function ($assessment) {

                return $assessment->questions->map(function ($question) use ($assessment) {

                    return [
                        'type'             => 'topic_question_without_options',
                        'assessment_id'    => $assessment->id,
                        'assessment_title' => $assessment->title,

                        'topic_id'         => optional($assessment->assessmentable)->id,
                        'topic_title'      => optional($assessment->assessmentable)->title,

                        'question_id'      => $question->id,
                        'question'         => $question->question_text,
                    ];
                });
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | MODULES WITHOUT EXAM
    |--------------------------------------------------------------------------
    */

        $moduleExamMissing = Module::select('id', 'title')
            ->whereDoesntHave('assessments', function ($q) {
                $q->where('type', 'module');
            })
            ->orderBy('id')
            ->get()
            ->map(function ($module) {

                return [
                    'type'  => 'module_exam_missing',
                    'id'    => $module->id,
                    'title' => $module->title,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | MODULE EXAM WITHOUT QUESTIONS
    |--------------------------------------------------------------------------
    */

        $moduleExamWithoutQuestions = Assessment::with('assessmentable:id,title')
            ->where('assessmentable_type', Module::class)
            ->where('type', 'module')
            ->doesntHave('questions')
            ->orderBy('id')
            ->get()
            ->map(function ($assessment) {

                return [
                    'type'             => 'module_exam_without_questions',
                    'assessment_id'    => $assessment->id,
                    'assessment_title' => $assessment->title,

                    'module_id'        => optional($assessment->assessmentable)->id,
                    'module_title'     => optional($assessment->assessmentable)->title,
                ];
            });

        /*
    |--------------------------------------------------------------------------
    | MODULE QUESTIONS WITHOUT OPTIONS
    |--------------------------------------------------------------------------
    */

        $moduleQuestionsWithoutOptions = Assessment::with([
            'assessmentable:id,title',
            'questions' => function ($q) {
                $q->doesntHave('options');
            }
        ])
            ->where('assessmentable_type', Module::class)
            ->where('type', 'module')
            ->whereHas('questions', function ($q) {
                $q->doesntHave('options');
            })
            ->get()
            ->flatMap(function ($assessment) {

                return $assessment->questions->map(function ($question) use ($assessment) {

                    return [
                        'type'             => 'module_question_without_options',
                        'assessment_id'    => $assessment->id,
                        'assessment_title' => $assessment->title,

                        'module_id'        => optional($assessment->assessmentable)->id,
                        'module_title'     => optional($assessment->assessmentable)->title,

                        'question_id'      => $question->id,
                        'question'         => $question->question_text,
                    ];
                });
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return [

            'summary' => [

                'topics_without_content' => $topicsWithoutContent->count(),

                'topic_quiz_missing' => $topicQuizMissing->count(),

                'topic_quiz_without_questions' => $topicQuizWithoutQuestions->count(),

                'topic_questions_without_options' => $topicQuestionsWithoutOptions->count(),

                'module_exam_missing' => $moduleExamMissing->count(),

                'module_exam_without_questions' => $moduleExamWithoutQuestions->count(),

                'module_questions_without_options' => $moduleQuestionsWithoutOptions->count(),
            ],

            'topics' => [

                'without_content' => $topicsWithoutContent,

                'quiz_missing' => $topicQuizMissing,

                'quiz_without_questions' => $topicQuizWithoutQuestions,

                'questions_without_options' => $topicQuestionsWithoutOptions,
            ],

            'modules' => [

                'exam_missing' => $moduleExamMissing,

                'exam_without_questions' => $moduleExamWithoutQuestions,

                'questions_without_options' => $moduleQuestionsWithoutOptions,
            ],
        ];
    }
}
