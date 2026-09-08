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
use Illuminate\Support\Facades\Cache;

class DashboardService {
    protected $visibilityService;

    public function __construct(
        HierarchyVisibilityService $visibilityService
    ) {
        $this->visibilityService = $visibilityService;
    }

    public function getDashboard() {
        return Cache::remember(
            'admin_dashboard',
            now()->addSeconds(60),
            function () {
                return [
                    'overview' => $this->getOverview(),

                    'learning_funnel' => $this->getLearningFunnel(),

                    'engagement' => $this->getEngagementAnalytics(),

                    'publishing_pipeline' => $this->getPublishingPipeline(),

                    'assessment_analytics' => $this->getAssessmentAnalytics(),

                    'certification_analytics' => $this->getCertificationAnalytics(),

                    'program_analytics' => $this->getProgramAnalytics(),

                    'risk_indicators' => $this->getRiskIndicators(),

                    'top_performers' => $this->getTopPerformers(),
                ];
            }
        );
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
        $stats = AssessmentAttempt::query()
            ->join(
                'assessments',
                'assessments.id',
                '=',
                'assessment_attempts.assessment_id'
            )
            ->where('assessments.status', true)
            ->whereNull('assessments.deleted_at')
            ->selectRaw("
            COUNT(assessment_attempts.id) as total_attempts,

            SUM(
                CASE
                    WHEN assessment_attempts.status = 'passed'
                    THEN 1 ELSE 0
                END
            ) as passed_attempts,

            SUM(
                CASE
                    WHEN assessment_attempts.status = 'failed'
                    THEN 1 ELSE 0
                END
            ) as failed_attempts,

            AVG(assessment_attempts.percentage) as avg_score,

            AVG(
                CASE
                    WHEN assessments.type = 'topic'
                    THEN assessment_attempts.percentage
                END
            ) as topic_quiz_avg,

            AVG(
                CASE
                    WHEN assessments.type = 'chapter'
                    THEN assessment_attempts.percentage
                END
            ) as chapter_exam_avg,

            AVG(
                CASE
                    WHEN assessments.type = 'module'
                    THEN assessment_attempts.percentage
                END
            ) as module_exam_avg,

            AVG(
                CASE
                    WHEN assessments.type = 'level'
                    THEN assessment_attempts.percentage
                END
            ) as level_exam_avg
        ")
            ->first();

        $totalAttempts = (int) ($stats->total_attempts ?? 0);
        $passedAttempts = (int) ($stats->passed_attempts ?? 0);
        $failedAttempts = (int) ($stats->failed_attempts ?? 0);

        return [

            'total_attempts' => $totalAttempts,

            'passed_attempts' => $passedAttempts,

            'failed_attempts' => $failedAttempts,

            'pass_rate' => $totalAttempts > 0
                ? round(
                    ($passedAttempts / $totalAttempts) * 100,
                    2
                )
                : 0,

            'fail_rate' => $totalAttempts > 0
                ? round(
                    ($failedAttempts / $totalAttempts) * 100,
                    2
                )
                : 0,

            'avg_score' => round(
                $stats->avg_score ?? 0,
                2
            ),

            'topic_quiz_avg' => round(
                $stats->topic_quiz_avg ?? 0,
                2
            ),

            'chapter_exam_avg' => round(
                $stats->chapter_exam_avg ?? 0,
                2
            ),

            'module_exam_avg' => round(
                $stats->module_exam_avg ?? 0,
                2
            ),

            'level_exam_avg' => round(
                $stats->level_exam_avg ?? 0,
                2
            ),

            'most_failed_assessments' =>
            Assessment::where('status', true)
                ->whereNull('deleted_at')
                ->withCount([
                    'attempts as fail_count' => function ($q) {
                        $q->where('status', 'failed');
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
        $stats = Certification::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->selectRaw("
            COUNT(*) as total_certificates,

            SUM(
                CASE
                    WHEN type = 'topic'
                    THEN 1 ELSE 0
                END
            ) as topic_certificates,

            SUM(
                CASE
                    WHEN type = 'chapter'
                    THEN 1 ELSE 0
                END
            ) as chapter_certificates,

            SUM(
                CASE
                    WHEN type = 'module'
                    THEN 1 ELSE 0
                END
            ) as module_certificates,

            SUM(
                CASE
                    WHEN type = 'level'
                    THEN 1 ELSE 0
                END
            ) as level_certificates,

            SUM(
                CASE
                    WHEN issued_at >= CURDATE()
                    THEN 1 ELSE 0
                END
            ) as certificates_issued_today,

            SUM(
                CASE
                    WHEN issued_at >= DATE_FORMAT(
                        CURRENT_DATE,
                        '%Y-%m-01'
                    )
                    THEN 1 ELSE 0
                END
            ) as certificates_issued_this_month
        ")
            ->first();

        return [

            'total_certificates' => (int) (
                $stats->total_certificates ?? 0
            ),

            'topic_certificates' => (int) (
                $stats->topic_certificates ?? 0
            ),

            'chapter_certificates' => (int) (
                $stats->chapter_certificates ?? 0
            ),

            'module_certificates' => (int) (
                $stats->module_certificates ?? 0
            ),

            'level_certificates' => (int) (
                $stats->level_certificates ?? 0
            ),

            'certificates_issued_today' => (int) (
                $stats->certificates_issued_today ?? 0
            ),

            'certificates_issued_this_month' => (int) (
                $stats->certificates_issued_this_month ?? 0
            ),
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | 🔹 PROGRAM ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getProgramAnalytics() {
        /*
    |--------------------------------------------------------------------------
    | PROGRAMS
    |--------------------------------------------------------------------------
    */

        $programs = Program::query()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->get([
                'id',
                'title',
            ]);

        if ($programs->isEmpty()) {
            return collect();
        }

        $programIds = $programs->pluck('id');


        /*
    |--------------------------------------------------------------------------
    | LEVEL COUNTS
    |--------------------------------------------------------------------------
    */

        $levelCounts = Level::query()
            ->select(
                'program_id',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('program_id', $programIds)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->groupBy('program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | MODULE COUNTS
    |--------------------------------------------------------------------------
    */

        $moduleCounts = Module::query()
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('modules.status', true)
            ->whereNull('modules.deleted_at')
            ->where('levels.status', true)
            ->whereNull('levels.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw('COUNT(modules.id) as total')
            )
            ->groupBy('levels.program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | CHAPTER COUNTS
    |--------------------------------------------------------------------------
    */

        $chapterCounts = Chapter::query()
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('chapters.status', true)
            ->whereNull('chapters.deleted_at')
            ->where('modules.status', true)
            ->whereNull('modules.deleted_at')
            ->where('levels.status', true)
            ->whereNull('levels.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw('COUNT(chapters.id) as total')
            )
            ->groupBy('levels.program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | TOPIC COUNTS
    |--------------------------------------------------------------------------
    */

        $topicCounts = Topic::query()
            ->join(
                'chapters',
                'chapters.id',
                '=',
                'topics.chapter_id'
            )
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('topics.status', true)
            ->whereNull('topics.deleted_at')
            ->where('chapters.status', true)
            ->whereNull('chapters.deleted_at')
            ->where('modules.status', true)
            ->whereNull('modules.deleted_at')
            ->where('levels.status', true)
            ->whereNull('levels.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw('COUNT(topics.id) as total')
            )
            ->groupBy('levels.program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | CONTENT COUNTS
    |--------------------------------------------------------------------------
    */

        $contentCounts = TopicContent::query()
            ->join(
                'topics',
                'topics.id',
                '=',
                'topic_contents.topic_id'
            )
            ->join(
                'chapters',
                'chapters.id',
                '=',
                'topics.chapter_id'
            )
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('topic_contents.status', true)
            ->whereNull('topic_contents.deleted_at')
            ->where('topics.status', true)
            ->whereNull('topics.deleted_at')
            ->where('chapters.status', true)
            ->whereNull('chapters.deleted_at')
            ->where('modules.status', true)
            ->whereNull('modules.deleted_at')
            ->where('levels.status', true)
            ->whereNull('levels.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw('COUNT(topic_contents.id) as total')
            )
            ->groupBy('levels.program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | TOPIC PUBLISHING
    |--------------------------------------------------------------------------
    */

        $topicPublishing = Topic::query()
            ->join(
                'chapters',
                'chapters.id',
                '=',
                'topics.chapter_id'
            )
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('topics.status', true)
            ->whereNull('topics.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw("
                SUM(
                    CASE
                        WHEN topics.publish_status = 'published'
                        THEN 1 ELSE 0
                    END
                ) as published,
                SUM(
                    CASE
                        WHEN topics.publish_status = 'draft'
                        THEN 1 ELSE 0
                    END
                ) as draft,
                SUM(
                    CASE
                        WHEN topics.publish_status = 'unpublished'
                        THEN 1 ELSE 0
                    END
                ) as unpublished
            ")
            )
            ->groupBy('levels.program_id')
            ->get()
            ->keyBy('program_id');


        /*
    |--------------------------------------------------------------------------
    | CONTENT PUBLISHING
    |--------------------------------------------------------------------------
    */

        $contentPublishing = TopicContent::query()
            ->join(
                'topics',
                'topics.id',
                '=',
                'topic_contents.topic_id'
            )
            ->join(
                'chapters',
                'chapters.id',
                '=',
                'topics.chapter_id'
            )
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where('topic_contents.status', true)
            ->whereNull('topic_contents.deleted_at')
            ->select(
                'levels.program_id',
                DB::raw("
                SUM(
                    CASE
                        WHEN topic_contents.publish_status = 'published'
                        THEN 1 ELSE 0
                    END
                ) as published,
                SUM(
                    CASE
                        WHEN topic_contents.publish_status = 'draft'
                        THEN 1 ELSE 0
                    END
                ) as draft,
                SUM(
                    CASE
                        WHEN topic_contents.publish_status = 'unpublished'
                        THEN 1 ELSE 0
                    END
                ) as unpublished
            ")
            )
            ->groupBy('levels.program_id')
            ->get()
            ->keyBy('program_id');


        /*
    |--------------------------------------------------------------------------
    | USER PROGRESS
    |--------------------------------------------------------------------------
    */

        $activeLearners = UserProgress::query()
            ->select(
                'program_id',
                DB::raw('COUNT(DISTINCT user_id) as total')
            )
            ->whereIn(
                'program_id',
                $programIds
            )
            ->groupBy('program_id')
            ->pluck('total', 'program_id');


        $completedTopics = UserProgress::query()
            ->select(
                'program_id',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn(
                'program_id',
                $programIds
            )
            ->where('is_completed', true)
            ->groupBy('program_id')
            ->pluck('total', 'program_id');


        /*
    |--------------------------------------------------------------------------
    | ASSESSMENT ANALYTICS
    |--------------------------------------------------------------------------
    */

        $assessmentStats = AssessmentAttempt::query()
            ->join(
                'assessments',
                'assessments.id',
                '=',
                'assessment_attempts.assessment_id'
            )
            ->join(
                'topics',
                function ($join) {
                    $join->on(
                        'topics.id',
                        '=',
                        'assessments.assessmentable_id'
                    )
                        ->where(
                            'assessments.assessmentable_type',
                            Topic::class
                        );
                }
            )
            ->join(
                'chapters',
                'chapters.id',
                '=',
                'topics.chapter_id'
            )
            ->join(
                'modules',
                'modules.id',
                '=',
                'chapters.module_id'
            )
            ->join(
                'levels',
                'levels.id',
                '=',
                'modules.level_id'
            )
            ->whereIn(
                'levels.program_id',
                $programIds
            )
            ->where(
                'assessments.type',
                'topic'
            )
            ->where(
                'assessments.status',
                true
            )
            ->whereNull(
                'assessments.deleted_at'
            )
            ->select(
                'levels.program_id',
                DB::raw(
                    'AVG(assessment_attempts.percentage) as avg_score'
                ),
                DB::raw(
                    'COUNT(assessment_attempts.id) as total_attempts'
                )
            )
            ->groupBy('levels.program_id')
            ->get()
            ->keyBy('program_id');


        /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Same response structure as your existing API.
    |
    */

        return $programs->map(function ($program) use (
            $levelCounts,
            $moduleCounts,
            $chapterCounts,
            $topicCounts,
            $contentCounts,
            $topicPublishing,
            $contentPublishing,
            $activeLearners,
            $completedTopics,
            $assessmentStats
        ) {

            $programId = $program->id;

            $topics = (int) ($topicCounts[$programId] ?? 0);

            $completed = (int) (
                $completedTopics[$programId] ?? 0
            );

            $topicPublish = $topicPublishing[$programId] ?? null;

            $contentPublish = $contentPublishing[$programId] ?? null;

            $assessment = $assessmentStats[$programId] ?? null;

            return [

                'id' => $program->id,

                'title' => $program->title,

                'structure' => [

                    'levels' => (int) (
                        $levelCounts[$programId] ?? 0
                    ),

                    'modules' => (int) (
                        $moduleCounts[$programId] ?? 0
                    ),

                    'chapters' => (int) (
                        $chapterCounts[$programId] ?? 0
                    ),

                    'topics' => $topics,

                    'contents' => (int) (
                        $contentCounts[$programId] ?? 0
                    ),
                ],

                'publishing' => [

                    'published_topics' => (int) (
                        $topicPublish->published ?? 0
                    ),

                    'draft_topics' => (int) (
                        $topicPublish->draft ?? 0
                    ),

                    'unpublished_topics' => (int) (
                        $topicPublish->unpublished ?? 0
                    ),

                    'published_contents' => (int) (
                        $contentPublish->published ?? 0
                    ),

                    'draft_contents' => (int) (
                        $contentPublish->draft ?? 0
                    ),

                    'unpublished_contents' => (int) (
                        $contentPublish->unpublished ?? 0
                    ),
                ],

                'learning' => [

                    'active_learners' => (int) (
                        $activeLearners[$programId] ?? 0
                    ),

                    'completed_topics' => $completed,

                    'completion_rate' => $topics > 0
                        ? round(
                            ($completed / $topics) * 100,
                            2
                        )
                        : 0,
                ],

                'assessment' => [

                    'topic_avg_score' => round(
                        $assessment->avg_score ?? 0,
                        2
                    ),

                    'total_attempts' => (int) (
                        $assessment->total_attempts ?? 0
                    ),
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
        $stats = $model::query()
            ->whereNull('deleted_at')
            ->selectRaw("
            SUM(
                CASE
                    WHEN publish_status = 'published'
                    THEN 1 ELSE 0
                END
            ) as published,

            SUM(
                CASE
                    WHEN publish_status = 'draft'
                    THEN 1 ELSE 0
                END
            ) as draft,

            SUM(
                CASE
                    WHEN publish_status = 'unpublished'
                    THEN 1 ELSE 0
                END
            ) as unpublished
        ")
            ->first();

        return [
            'published' => (int) ($stats->published ?? 0),
            'draft' => (int) ($stats->draft ?? 0),
            'unpublished' => (int) ($stats->unpublished ?? 0),
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
