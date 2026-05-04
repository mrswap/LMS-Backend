<?php

namespace App\Modules\Admin\Dashboard\Services;

use App\Models\User;
use App\Models\Level;
use App\Models\Topic;
use App\Models\TopicContent;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Certification;
use App\Models\UserProgress;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboard()
    {
        return [
            'kpis' => $this->getKPIs(),
            'learning_funnel' => $this->getLearningFunnel(),
            'engagement' => $this->getEngagementStats(),
            'assessment' => $this->getAssessmentInsights(),
            'content' => $this->getContentInsights(),
            'levels' => $this->getLevelAnalytics(),
            'top_performers' => $this->getTopPerformers(),
            'at_risk_users' => $this->getAtRiskUsers(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 KPI BLOCK (Dashboard Cards)
    |-----------------------------------------
    */
    private function getKPIs()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),

            'total_levels' => Level::count(),
            'total_topics' => Topic::count(),
            'total_lessons' => TopicContent::count(),

            'total_assessments' => Assessment::count(),
            'certificates_issued' => Certification::count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 LEARNING FUNNEL (VERY IMPORTANT)
    |-----------------------------------------
    */
    private function getLearningFunnel()
    {
        return [
            'not_started' => User::whereDoesntHave('progress')->count(),

            'started' => UserProgress::distinct('user_id')->count('user_id'),

            'in_progress' => UserProgress::where('is_unlocked', true)
                ->where('is_completed', false)
                ->count(),

            'completed' => UserProgress::where('is_completed', true)->count(),

            'certified' => Certification::distinct('user_id')->count('user_id'),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 ENGAGEMENT (LAST ACTIVITY)
    |-----------------------------------------
    */
    private function getEngagementStats()
    {
        return [
            'daily_active_users' => UserProgress::whereDate('updated_at', today())->count(),

            'weekly_active_users' => UserProgress::where('updated_at', '>=', now()->subDays(7))->count(),

            'monthly_active_users' => UserProgress::where('updated_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 ASSESSMENT INSIGHTS
    |-----------------------------------------
    */
    private function getAssessmentInsights()
    {
        $totalAttempts = AssessmentAttempt::count();
        $passed = AssessmentAttempt::where('status', 'passed')->count();
        $failed = AssessmentAttempt::where('status', 'failed')->count();

        return [
            'total_attempts' => $totalAttempts,

            'avg_score' => round(AssessmentAttempt::avg('percentage') ?? 0, 2),

            'pass_rate' => $totalAttempts > 0
                ? round(($passed / $totalAttempts) * 100, 2)
                : 0,

            'fail_rate' => $totalAttempts > 0
                ? round(($failed / $totalAttempts) * 100, 2)
                : 0,

            'most_failed_assessments' => Assessment::withCount([
                'attempts as fail_count' => function ($q) {
                    $q->where('status', 'failed');
                }
            ])
                ->orderByDesc('fail_count')
                ->limit(5)
                ->get(['id', 'title']),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 CONTENT INSIGHTS
    |-----------------------------------------
    */
    private function getContentInsights()
    {
        return [
            'published' => TopicContent::where('status', true)->count(),
            'draft' => TopicContent::where('status', false)->count(),

            'unused_topics' => Topic::whereDoesntHave('contents')->count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 LEVEL ANALYTICS (REAL PROGRESSION)
    |-----------------------------------------
    */
    private function getLevelAnalytics()
    {
        return Level::with('modules.chapters.topics')->get()->map(function ($level) {

            $topicIds = $level->modules
                ->flatMap->chapters
                ->flatMap->topics
                ->pluck('id');

            $totalUsers = User::count();

            $completedUsers = UserProgress::whereIn('topic_id', $topicIds)
                ->where('is_completed', true)
                ->distinct('user_id')
                ->count('user_id');

            return [
                'id' => $level->id,
                'title' => $level->title,

                'total_topics' => $topicIds->count(),

                'completed_users' => $completedUsers,

                'completion_percent' => $totalUsers > 0
                    ? round(($completedUsers / $totalUsers) * 100, 2)
                    : 0,
            ];
        });
    }

    /*
    |-----------------------------------------
    | 🔹 TOP PERFORMERS
    |-----------------------------------------
    */
    private function getTopPerformers()
    {
        return AssessmentAttempt::select('user_id', DB::raw('AVG(percentage) as avg_score'))
            ->where('status', 'passed')
            ->groupBy('user_id')
            ->orderByDesc('avg_score')
            ->limit(5)
            ->with('user:id,name,email')
            ->get();
    }

    /*
    |-----------------------------------------
    | 🔹 AT RISK USERS (VERY IMPORTANT)
    |-----------------------------------------
    */
    private function getAtRiskUsers()
    {
        return AssessmentAttempt::select('user_id', DB::raw('AVG(percentage) as avg_score'))
            ->groupBy('user_id')
            ->havingRaw('AVG(percentage) < 40')
            ->with('user:id,name,email')
            ->limit(10)
            ->get();
    }
}
