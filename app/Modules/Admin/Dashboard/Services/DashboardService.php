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
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboard()
    {
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

    private function getOverview()
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | USERS
            |--------------------------------------------------------------------------
            */
            'total_users' => User::count(),

            'active_users' => User::where('is_active', true)->count(),

            'inactive_users' => User::where('is_active', false)->count(),

            /*
            |--------------------------------------------------------------------------
            | LMS STRUCTURE
            |--------------------------------------------------------------------------
            */
            'total_programs' => Program::count(),

            'total_levels' => Level::count(),

            'total_modules' => Module::count(),

            'total_chapters' => Chapter::count(),

            'total_topics' => Topic::count(),

            'total_contents' => TopicContent::count(),

            /*
            |--------------------------------------------------------------------------
            | LEARNING ENGINE
            |--------------------------------------------------------------------------
            */
            'total_assessments' => Assessment::count(),

            'total_certificates' => Certification::count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 LEARNING FUNNEL
    |--------------------------------------------------------------------------
    */

    private function getLearningFunnel()
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Never started
            |--------------------------------------------------------------------------
            */
            'not_started_users' => User::whereDoesntHave('progress')
                ->count(),

            /*
            |--------------------------------------------------------------------------
            | Started learning
            |--------------------------------------------------------------------------
            */
            'started_users' => UserProgress::distinct('user_id')
                ->count('user_id'),

            /*
            |--------------------------------------------------------------------------
            | Currently learning
            |--------------------------------------------------------------------------
            */
            'in_progress_users' => UserProgress::where('is_unlocked', true)
                ->where('is_completed', false)
                ->distinct('user_id')
                ->count('user_id'),

            /*
            |--------------------------------------------------------------------------
            | Completed users
            |--------------------------------------------------------------------------
            */
            'completed_users' => UserProgress::where('is_completed', true)
                ->distinct('user_id')
                ->count('user_id'),

            /*
            |--------------------------------------------------------------------------
            | Certified users
            |--------------------------------------------------------------------------
            */
            'certified_users' => Certification::distinct('user_id')
                ->count('user_id'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 ENGAGEMENT ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getEngagementAnalytics()
    {
        return [

            'daily_active_users' => UserProgress::whereDate('updated_at', today())
                ->distinct('user_id')
                ->count('user_id'),

            'weekly_active_users' => UserProgress::where(
                'updated_at',
                '>=',
                now()->subDays(7)
            )
                ->distinct('user_id')
                ->count('user_id'),

            'monthly_active_users' => UserProgress::where(
                'updated_at',
                '>=',
                now()->subDays(30)
            )
                ->distinct('user_id')
                ->count('user_id'),

            /*
            |--------------------------------------------------------------------------
            | Content Reads
            |--------------------------------------------------------------------------
            */
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

    private function getPublishingPipeline()
    {
        return [

            'programs' => $this->getPublishStats(Program::class),

            'levels' => $this->getPublishStats(Level::class),

            'modules' => $this->getPublishStats(Module::class),

            'chapters' => $this->getPublishStats(Chapter::class),

            'topics' => $this->getPublishStats(Topic::class),

            'contents' => $this->getPublishStats(TopicContent::class),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 ASSESSMENT ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getAssessmentAnalytics()
    {
        $totalAttempts = AssessmentAttempt::count();

        $passedAttempts = AssessmentAttempt::where(
            'status',
            'passed'
        )->count();

        $failedAttempts = AssessmentAttempt::where(
            'status',
            'failed'
        )->count();

        return [

            'total_attempts' => $totalAttempts,

            'passed_attempts' => $passedAttempts,

            'failed_attempts' => $failedAttempts,

            'pass_rate' => $totalAttempts > 0
                ? round(($passedAttempts / $totalAttempts) * 100, 2)
                : 0,

            'fail_rate' => $totalAttempts > 0
                ? round(($failedAttempts / $totalAttempts) * 100, 2)
                : 0,

            'avg_score' => round(
                AssessmentAttempt::avg('percentage') ?? 0,
                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Topic Quiz Avg
            |--------------------------------------------------------------------------
            */
            'topic_quiz_avg' => round(
                AssessmentAttempt::whereHas('assessment', function ($q) {
                    $q->where('type', 'topic');
                })->avg('percentage') ?? 0,
                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Level Exam Avg
            |--------------------------------------------------------------------------
            */
            'level_exam_avg' => round(
                AssessmentAttempt::whereHas('assessment', function ($q) {
                    $q->where('type', 'level');
                })->avg('percentage') ?? 0,
                2
            ),

            /*
            |--------------------------------------------------------------------------
            | Most Failed Assessments
            |--------------------------------------------------------------------------
            */
            'most_failed_assessments' => Assessment::withCount([
                'attempts as fail_count' => function ($q) {
                    $q->where('status', 'failed');
                }
            ])
                ->orderByDesc('fail_count')
                ->limit(10)
                ->get([
                    'id',
                    'title',
                    'type',
                    'passing_score',
                    'total_marks'
                ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 CERTIFICATION ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getCertificationAnalytics()
    {
        return [

            'total_certificates' => Certification::count(),

            'certificates_issued_today' => Certification::whereDate(
                'issued_at',
                today()
            )->count(),

            'certificates_issued_this_month' => Certification::whereMonth(
                'issued_at',
                now()->month
            )->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 PROGRAM ANALYTICS
    |--------------------------------------------------------------------------
    */

    private function getProgramAnalytics()
    {
        return Program::with([
            'levels.modules.chapters.topics.contents'
        ])->get()->map(function ($program) {

            $levels = $program->levels;

            $modules = $levels->flatMap->modules;

            $chapters = $modules->flatMap->chapters;

            $topics = $chapters->flatMap->topics;

            $contents = $topics->flatMap->contents;

            $topicIds = $topics->pluck('id');

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
                        ->where('publish_status', 'published')
                        ->count(),

                    'draft_topics' => $topics
                        ->where('publish_status', 'draft')
                        ->count(),

                    'unpublished_topics' => $topics
                        ->where('publish_status', 'unpublished')
                        ->count(),

                    'published_contents' => $contents
                        ->where('publish_status', 'published')
                        ->count(),

                    'draft_contents' => $contents
                        ->where('publish_status', 'draft')
                        ->count(),

                    'unpublished_contents' => $contents
                        ->where('publish_status', 'unpublished')
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
                        ->distinct('user_id')
                        ->count('user_id'),

                    'completed_topics' => UserProgress::where(
                        'program_id',
                        $program->id
                    )
                        ->where('is_completed', true)
                        ->count(),

                    'completion_rate' => $topicIds->count() > 0
                        ? round(
                            (
                                UserProgress::where(
                                    'program_id',
                                    $program->id
                                )
                                ->where('is_completed', true)
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

                    'avg_score' => round(
                        AssessmentAttempt::whereHas(
                            'assessment',
                            function ($q) use ($topicIds) {

                                $q->where('type', 'topic')
                                    ->whereIn(
                                        'assessmentable_id',
                                        $topicIds
                                    );
                            }
                        )->avg('percentage') ?? 0,
                        2
                    ),

                    'total_attempts' => AssessmentAttempt::whereHas(
                        'assessment',
                        function ($q) use ($topicIds) {

                            $q->where('type', 'topic')
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

    private function getRiskIndicators()
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Low Performing Users
            |--------------------------------------------------------------------------
            */
            'low_performing_users' => AssessmentAttempt::select(
                'user_id',
                DB::raw('AVG(percentage) as avg_score')
            )
                ->groupBy('user_id')
                ->havingRaw('AVG(percentage) < 40')
                ->with('user:id,name,email')
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
                    $q->where('publish_status', 'draft');
                }
            ])
                ->orderByDesc('draft_levels_count')
                ->limit(5)
                ->get([
                    'id',
                    'title'
                ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 🔹 TOP PERFORMERS
    |--------------------------------------------------------------------------
    */

    private function getTopPerformers()
    {
        return AssessmentAttempt::select(
            'user_id',
            DB::raw('AVG(percentage) as avg_score')
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

    private function getPublishStats($model)
    {
        return [

            'published' => $model::where(
                'publish_status',
                'published'
            )->count(),

            'draft' => $model::where(
                'publish_status',
                'draft'
            )->count(),

            'unpublished' => $model::where(
                'publish_status',
                'unpublished'
            )->count(),
        ];
    }
}
