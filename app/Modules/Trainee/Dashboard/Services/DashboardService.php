<?php

namespace App\Modules\Trainee\Dashboard\Services;

use App\Models\UserProgress;
use App\Models\UserContentProgress;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\TopicContent;
use App\Models\Certification;
use App\Models\Level;

class DashboardService
{
    public function getDashboard($userId)
    {
        /*
        |-----------------------------------------
        | 🔹 CURRENT + LAST COMPLETED
        |-----------------------------------------
        */
        $current = UserProgress::where('user_id', $userId)
            ->where('is_unlocked', true)
            ->whereNotNull('topic_id')
            ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
            ->orderByDesc('updated_at')
            ->first();

        $lastCompleted = UserProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereNotNull('topic_id')
            ->with('topic')
            ->latest('completed_at')
            ->first();

        /*
        |-----------------------------------------
        | 🔹 LESSON STATS
        |-----------------------------------------
        */
        $totalLessons = TopicContent::count();

        $completedLessons = UserContentProgress::where('user_id', $userId)
            ->where('is_read', true)
            ->count();

        $progressPercent = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 2)
            : 0;

        /*
        |-----------------------------------------
        | 🔹 CURRENT TOPIC CONTENTS
        |-----------------------------------------
        */
        $contents = [];

        if ($current && $current->topic_id) {
            $contents = TopicContent::where('topic_id', $current->topic_id)
                ->select('id', 'title', 'type')
                ->orderBy('order')
                ->get();
        }

        /*
        |-----------------------------------------
        | 🔹 LEVEL CARDS
        |-----------------------------------------
        */
        $levels = Level::with('modules.chapters.topics')->get();

        $levelCards = $levels->map(function ($level) use ($userId) {

            $progress = UserProgress::where('user_id', $userId)
                ->where('level_id', $level->id)
                ->get();

            $total = $progress->count();
            $completed = $progress->where('is_completed', true)->count();

            $status = 'locked';
            if ($total > 0 && $completed === $total) {
                $status = 'completed';
            } elseif ($progress->where('is_unlocked', true)->count() > 0) {
                $status = 'unlocked';
            }

            $modules = $level->modules;
            $chapters = $modules->flatMap->chapters;
            $topics = $chapters->flatMap->topics;

            $lessonCount = TopicContent::whereIn('topic_id', $topics->pluck('id'))->count();

            return [
                'id' => $level->id,
                'title' => $level->title,
                'description' => $level->description,

                'status' => $status,

                'total_modules' => $modules->count(),
                'total_topics' => $topics->count(),
                'total_lessons' => $lessonCount,

                'completion_percent' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,

                'cta' => $status === 'completed'
                    ? 'view_certificate'
                    : ($status === 'unlocked' ? 'continue' : 'start')
            ];
        });

        /*
        |-----------------------------------------
        | 🔹 STATS
        |-----------------------------------------
        */
        $totalLevels = $levels->count();
        $completedLevels = $levelCards->where('status', 'completed')->count();

        $totalTopics = UserProgress::where('user_id', $userId)
            ->whereNotNull('topic_id')
            ->count();

        $completedTopics = UserProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereNotNull('topic_id')
            ->count();

        $avgTopicScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', fn($q) => $q->where('type', 'topic'))
            ->avg('percentage');

        $avgExamScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', fn($q) => $q->where('type', 'level'))
            ->avg('percentage');

        /*
        |-----------------------------------------
        | 🔹 CERTIFICATE
        |-----------------------------------------
        */
        $certificate = Certification::where('user_id', $userId)
            ->latest('issued_at')
            ->first();

        /*
        |-----------------------------------------
        | 🔹 QUIZ / EXAM UNLOCK LOGIC (FINAL)
        |-----------------------------------------
        */

        // 🔹 Completed Topics
        $completedTopicIds = UserProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereNotNull('topic_id')
            ->pluck('topic_id');

        // 🔹 Topic Quiz Unlock
        $pendingTopicQuiz = Assessment::where('type', 'topic')
            ->whereIn('assessmentable_id', $completedTopicIds)
            ->where('status', true)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('status', 'passed');
            })
            ->with('assessmentable')
            ->first();

        // 🔹 Level Completion Check
        $levelIds = UserProgress::where('user_id', $userId)
            ->whereNotNull('level_id')
            ->pluck('level_id')
            ->unique();

        $completedLevelIds = [];

        foreach ($levelIds as $levelId) {

            $total = UserProgress::where('user_id', $userId)
                ->where('level_id', $levelId)
                ->whereNotNull('topic_id')
                ->count();

            $completed = UserProgress::where('user_id', $userId)
                ->where('level_id', $levelId)
                ->where('is_completed', true)
                ->whereNotNull('topic_id')
                ->count();

            if ($total > 0 && $total === $completed) {
                $completedLevelIds[] = $levelId;
            }
        }

        // 🔹 Level Exam Unlock
        $pendingLevelExam = Assessment::where('type', 'level')
            ->whereIn('assessmentable_id', $completedLevelIds)
            ->where('status', true)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('status', 'passed');
            })
            ->with('assessmentable')
            ->first();

        // 🔹 Next Topic
        $nextTopic = UserProgress::where('user_id', $userId)
            ->where('is_unlocked', true)
            ->where('is_completed', false)
            ->whereNotNull('topic_id')
            ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
            ->orderBy('id')
            ->first();

        /*
        |-----------------------------------------
        | 🔥 PRIORITY ENGINE
        |-----------------------------------------
        */
        if ($pendingTopicQuiz) {

            $topic = $pendingTopicQuiz->assessmentable;

            $nextAction = [
                'type' => 'topic_quiz',
                'assessment_id' => $pendingTopicQuiz->id,
                'assessment_title' => $pendingTopicQuiz->title,
                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title
                ]
            ];

        } elseif ($pendingLevelExam) {

            $level = $pendingLevelExam->assessmentable;

            $nextAction = [
                'type' => 'level_exam',
                'assessment_id' => $pendingLevelExam->id,
                'assessment_title' => $pendingLevelExam->title,
                'level' => [
                    'id' => $level->id,
                    'title' => $level->title
                ]
            ];

        } elseif ($nextTopic) {

            $nextAction = [
                'type' => 'next_topic',
                'program' => [
                    'id' => $nextTopic->topic?->program?->id,
                    'title' => $nextTopic->topic?->program?->title
                ],
                'level' => [
                    'id' => $nextTopic->topic?->level?->id,
                    'title' => $nextTopic->topic?->level?->title
                ],
                'module' => [
                    'id' => $nextTopic->topic?->module?->id,
                    'title' => $nextTopic->topic?->module?->title
                ],
                'chapter' => [
                    'id' => $nextTopic->topic?->chapter?->id,
                    'title' => $nextTopic->topic?->chapter?->title
                ],
                'topic' => [
                    'id' => $nextTopic->topic?->id,
                    'title' => $nextTopic->topic?->title
                ]
            ];

        } else {
            $nextAction = null;
        }

        /*
        |-----------------------------------------
        | 🔹 FINAL RESPONSE
        |-----------------------------------------
        */
        return [
            'current_learning' => [
                'program' => [
                    'id' => $current?->topic?->program?->id,
                    'title' => $current?->topic?->program?->title
                ],
                'level' => [
                    'id' => $current?->topic?->level?->id,
                    'title' => $current?->topic?->level?->title
                ],
                'module' => [
                    'id' => $current?->topic?->module?->id,
                    'title' => $current?->topic?->module?->title
                ],
                'chapter' => [
                    'id' => $current?->topic?->chapter?->id,
                    'title' => $current?->topic?->chapter?->title
                ],
                'topic' => [
                    'id' => $current?->topic?->id,
                    'title' => $current?->topic?->title
                ],

                'last_completed_topic' => [
                    'id' => $lastCompleted?->topic?->id,
                    'title' => $lastCompleted?->topic?->title
                ],

                'progress_percent' => $progressPercent,
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'pending_quizzes' => $pendingTopicQuiz ? 1 : 0,
                'last_activity_date' => $current?->updated_at,

                'cta' => [
                    'type' => 'resume',
                    'topic_id' => $current?->topic_id
                ]
            ],

            'levels' => $levelCards,

            'current_topic_contents' => $contents,

            'stats' => [
                'total_levels' => $totalLevels,
                'completed_levels' => $completedLevels,
                'remaining_levels' => $totalLevels - $completedLevels,

                'total_topics' => $totalTopics,
                'completed_topics' => $completedTopics,

                'avg_topic_score' => round($avgTopicScore ?? 0, 2),
                'avg_exam_score' => round($avgExamScore ?? 0, 2),

                'certificates_earned' => Certification::where('user_id', $userId)->count()
            ],

            'last_certificate' => $certificate,

            'next_action' => $nextAction
        ];
    }
}