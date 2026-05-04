<?php

namespace App\Modules\Admin\Dashboard\Services;

use App\Models\User;
use App\Models\Level;
use App\Models\TopicContent;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Certification;
use App\Models\UserProgress;

class DashboardService
{
    public function getDashboard()
    {
        return [
            'summary' => $this->getSummary(),
            'user_progress' => $this->getUserProgress(),
            'content_status' => $this->getContentStatus(),
            'assessment_stats' => $this->getAssessmentStats(),
            'certification_stats' => $this->getCertificationStats(),
            'levels' => $this->getLevelAnalytics(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 SUMMARY
    |-----------------------------------------
    */
    private function getSummary()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),

            'total_levels' => Level::count(),

            'total_lessons' => TopicContent::count(),

            'total_assessments' => Assessment::count(),

            'certificates_issued' => Certification::count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 USER PROGRESS
    |-----------------------------------------
    */
    private function getUserProgress()
    {
        return [
            'not_started' => UserProgress::where('is_unlocked', false)->count(),

            'in_progress' => UserProgress::where('is_unlocked', true)
                ->where('is_completed', false)
                ->count(),

            'completed' => UserProgress::where('is_completed', true)->count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 CONTENT STATUS
    |-----------------------------------------
    */
    private function getContentStatus()
    {
        return [
            'published' => TopicContent::where('status', true)->count(),
            'draft' => TopicContent::where('status', false)->count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 ASSESSMENT
    |-----------------------------------------
    */
    private function getAssessmentStats()
    {
        return [
            'total_attempts' => AssessmentAttempt::count(),
            'avg_score' => round(AssessmentAttempt::avg('percentage') ?? 0, 2),
            'pass_rate' => AssessmentAttempt::where('status', 'passed')->count(),
            'fail_rate' => AssessmentAttempt::where('status', 'failed')->count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 CERTIFICATION
    |-----------------------------------------
    */
    private function getCertificationStats()
    {
        return [
            'total_certificates' => Certification::count(),
        ];
    }

    /*
    |-----------------------------------------
    | 🔹 LEVEL ANALYTICS
    |-----------------------------------------
    */
    private function getLevelAnalytics()
    {
        return Level::with('modules.chapters.topics')->get()->map(function ($level) {

            $totalUsers = UserProgress::where('level_id', $level->id)->count();

            $completedUsers = UserProgress::where('level_id', $level->id)
                ->where('is_completed', true)
                ->count();

            return [
                'id' => $level->id,
                'title' => $level->title,

                'total_users' => $totalUsers,
                'completed_users' => $completedUsers,

                'completion_percent' => $totalUsers > 0
                    ? round(($completedUsers / $totalUsers) * 100, 2)
                    : 0
            ];
        });
    }
}
