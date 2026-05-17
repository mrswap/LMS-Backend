<?php

namespace App\Modules\Trainee\Dashboard\Services;

use App\Models\UserProgress;
use App\Models\UserContentProgress;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\TopicContent;
use App\Models\Certification;
use App\Models\Level;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

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
            ->where('is_completed', false)
            ->whereNotNull('topic_id')
            ->with([
                'topic.program',
                'topic.level',
                'topic.module',
                'topic.chapter'
            ])
            ->orderBy('id')
            ->first();

        // fallback if all completed
        if (!$current) {

            $current = UserProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->whereNotNull('topic_id')
                ->with([
                    'topic.program',
                    'topic.level',
                    'topic.module',
                    'topic.chapter'
                ])
                ->latest('completed_at')
                ->first();
        }

        /*
        |-----------------------------------------
        | 🔹 LAST COMPLETED TOPIC QUIZ
        |-----------------------------------------
        */
        $lastCompletedAttempt = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic');
            })
            ->with('assessment.assessmentable')
            ->latest('submitted_at')
            ->first();

        /*
        |-----------------------------------------
        | 🔹 COMPLETED TOPICS (FULL READ)
        |-----------------------------------------
        */
        $completedTopicIds = DB::table('topic_contents as tc')
            ->select('tc.topic_id')
            ->leftJoin('user_content_progress as ucp', function ($join) use ($userId) {

                $join->on('tc.id', '=', 'ucp.topic_content_id')
                    ->where('ucp.user_id', $userId)
                    ->where('ucp.is_read', 1);
            })
            ->groupBy('tc.topic_id')
            ->havingRaw('COUNT(tc.id) = COUNT(ucp.id)')
            ->pluck('tc.topic_id');

        /*
        |-----------------------------------------
        | 🔹 STARTED TOPICS (ANY CONTENT READ)
        |-----------------------------------------
        */
        $startedTopicIds = DB::table('topic_contents as tc')
            ->join('user_content_progress as ucp', function ($join) use ($userId) {

                $join->on('tc.id', '=', 'ucp.topic_content_id')
                    ->where('ucp.user_id', $userId)
                    ->where('ucp.is_read', 1);
            })
            ->distinct()
            ->pluck('tc.topic_id');

        /*
        |-----------------------------------------
        | 🔹 TOTAL TOPICS
        |-----------------------------------------
        */
        $totalLessons = Topic::count();

        $completedLessons = $completedTopicIds->count();

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
        | 🔹 LOAD FULL STRUCTURE
        |-----------------------------------------
        */
        $levels = Level::with([
            'modules.chapters.topics.program'
        ])->get();

        $completedLevelIds = [];

        $moduleStats = [];

        $chapterStats = [];

        /*
        |-----------------------------------------
        | 🔹 LEVEL / MODULE / CHAPTER STATS
        |-----------------------------------------
        */
        foreach ($levels as $level) {

            foreach ($level->modules as $module) {

                foreach ($module->chapters as $chapter) {

                    $chapterTopicIds = $chapter->topics
                        ->pluck('id')
                        ->toArray();

                    $chapterCompletedTopics = count(array_intersect(
                        $chapterTopicIds,
                        $completedTopicIds->toArray()
                    ));

                    $chapterStats[] = [

                        'chapter_id' => $chapter->id,

                        'chapter_title' => $chapter->title,

                        'module_id' => $module->id,

                        'level_id' => $level->id,

                        'total_topics' => count($chapterTopicIds),

                        'completed_topics' => $chapterCompletedTopics,

                        'progress_percent' => count($chapterTopicIds) > 0
                            ? round(($chapterCompletedTopics / count($chapterTopicIds)) * 100, 2)
                            : 0
                    ];
                }

                $moduleTopicIds = $module->chapters
                    ->flatMap->topics
                    ->pluck('id')
                    ->toArray();

                $moduleCompletedTopics = count(array_intersect(
                    $moduleTopicIds,
                    $completedTopicIds->toArray()
                ));

                $moduleStats[] = [

                    'module_id' => $module->id,

                    'module_title' => $module->title,

                    'level_id' => $level->id,

                    'total_topics' => count($moduleTopicIds),

                    'completed_topics' => $moduleCompletedTopics,

                    'progress_percent' => count($moduleTopicIds) > 0
                        ? round(($moduleCompletedTopics / count($moduleTopicIds)) * 100, 2)
                        : 0
                ];
            }

            $levelTopicIds = $level->modules
                ->flatMap->chapters
                ->flatMap->topics
                ->pluck('id')
                ->toArray();

            $levelCompletedTopics = count(array_intersect(
                $levelTopicIds,
                $completedTopicIds->toArray()
            ));

            if (
                count($levelTopicIds) > 0 &&
                $levelCompletedTopics === count($levelTopicIds)
            ) {

                $completedLevelIds[] = $level->id;
            }
        }

        /*
        |-----------------------------------------
        | 🔹 PENDING QUIZZES
        |-----------------------------------------
        */
        $pendingTopicQuizCount = Assessment::where('type', 'topic')
            ->whereIn('assessmentable_id', $completedTopicIds)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {

                $q->where('user_id', $userId)
                    ->where('status', 'passed');
            })
            ->count();

        /*
        |-----------------------------------------
        | 🔹 NEXT ACTION
        |-----------------------------------------
        */
        $pendingTopicQuiz = Assessment::where('type', 'topic')
            ->whereIn('assessmentable_id', $completedTopicIds)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {

                $q->where('user_id', $userId)
                    ->where('status', 'passed');
            })
            ->with('assessmentable')
            ->first();

        $pendingLevelExam = Assessment::where('type', 'level')
            ->whereIn('assessmentable_id', $completedLevelIds)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {

                $q->where('user_id', $userId)
                    ->where('status', 'passed');
            })
            ->with('assessmentable')
            ->first();

        $nextAction = null;

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

        } else {

            foreach ($levels as $level) {

                foreach ($level->modules as $module) {

                    foreach ($module->chapters as $chapter) {

                        foreach ($chapter->topics as $topic) {

                            if (!in_array($topic->id, $completedTopicIds->toArray())) {

                                $nextAction = [

                                    'type' => 'next_topic',

                                    'program' => [
                                        'id' => $topic->program_id,
                                        'title' => $topic->program?->title
                                    ],

                                    'level' => [
                                        'id' => $level->id,
                                        'title' => $level->title
                                    ],

                                    'module' => [
                                        'id' => $module->id,
                                        'title' => $module->title
                                    ],

                                    'chapter' => [
                                        'id' => $chapter->id,
                                        'title' => $chapter->title
                                    ],

                                    'topic' => [
                                        'id' => $topic->id,
                                        'title' => $topic->title
                                    ]
                                ];

                                break 4;
                            }
                        }
                    }
                }
            }
        }

        /*
        |-----------------------------------------
        | 🔹 LAST CERTIFICATE
        |-----------------------------------------
        */
        $certificate = Certification::where('user_id', $userId)
            ->latest('issued_at')
            ->first();

        /*
        |-----------------------------------------
        | 🔹 CURRENT TOPIC PROGRESS
        |-----------------------------------------
        */
        $currentTopicProgress = null;

        if ($current && $current->topic_id) {

            $topicId = $current->topic_id;

            $totalContent = TopicContent::where('topic_id', $topicId)->count();

            $readContent = UserContentProgress::where('user_id', $userId)
                ->whereIn('topic_content_id', function ($q) use ($topicId) {

                    $q->select('id')
                        ->from('topic_contents')
                        ->where('topic_id', $topicId);
                })
                ->where('is_read', 1)
                ->count();

            $currentTopicProgress = [

                'topic_id' => $topicId,

                'total_contents' => $totalContent,

                'read_contents' => $readContent,

                'progress_percent' => $totalContent > 0
                    ? round(($readContent / $totalContent) * 100, 2)
                    : 0
            ];
        }

        /*
        |-----------------------------------------
        | 🔹 LEVEL CARDS
        |-----------------------------------------
        */
        $levelCards = $levels->map(function ($level) use (
            $completedTopicIds,
            $startedTopicIds
        ) {

            $levelTopicIds = $level->modules
                ->flatMap->chapters
                ->flatMap->topics
                ->pluck('id')
                ->toArray();

            $completedTopics = count(array_intersect(
                $levelTopicIds,
                $completedTopicIds->toArray()
            ));

            $startedTopics = count(array_intersect(
                $levelTopicIds,
                $startedTopicIds->toArray()
            ));

            $totalTopics = count($levelTopicIds);

            $progressPercent = $totalTopics > 0
                ? round(($completedTopics / $totalTopics) * 100, 2)
                : 0;

            $status = 'locked';

            if (
                $completedTopics === $totalTopics &&
                $totalTopics > 0
            ) {

                $status = 'completed';

            } elseif ($startedTopics > 0) {

                $status = 'unlocked';
            }

            return [

                'id' => $level->id,

                'title' => $level->title,

                'description' => $level->description,

                'status' => $status,

                'total_modules' => $level->modules->count(),

                'total_topics' => $totalTopics,

                'total_lessons' => $totalTopics,

                'completed_topics' => $completedTopics,

                'started_topics' => $startedTopics,

                'completion_percent' => $progressPercent,

                'cta' => $status === 'completed'
                    ? 'view_certificate'
                    : ($status === 'unlocked'
                        ? 'continue'
                        : 'start'),

                /*
                |-----------------------------------------
                | 🔹 MODULES
                |-----------------------------------------
                */
                'modules' => $level->modules->map(function ($module) use (
                    $completedTopicIds,
                    $startedTopicIds
                ) {

                    $moduleTopicIds = $module->chapters
                        ->flatMap->topics
                        ->pluck('id')
                        ->toArray();

                    $completedTopics = count(array_intersect(
                        $moduleTopicIds,
                        $completedTopicIds->toArray()
                    ));

                    $startedTopics = count(array_intersect(
                        $moduleTopicIds,
                        $startedTopicIds->toArray()
                    ));

                    $totalTopics = count($moduleTopicIds);

                    $progressPercent = $totalTopics > 0
                        ? round(($completedTopics / $totalTopics) * 100, 2)
                        : 0;

                    return [

                        'module_id' => $module->id,

                        'module_title' => $module->title,

                        'total_topics' => $totalTopics,

                        'completed_topics' => $completedTopics,

                        'started_topics' => $startedTopics,

                        'progress_percent' => $progressPercent,

                        /*
                        |-----------------------------------------
                        | 🔹 CHAPTERS
                        |-----------------------------------------
                        */
                        'chapters' => $module->chapters->map(function ($chapter) use (
                            $completedTopicIds,
                            $startedTopicIds
                        ) {

                            $chapterTopicIds = $chapter->topics
                                ->pluck('id')
                                ->toArray();

                            $completedTopics = count(array_intersect(
                                $chapterTopicIds,
                                $completedTopicIds->toArray()
                            ));

                            $startedTopics = count(array_intersect(
                                $chapterTopicIds,
                                $startedTopicIds->toArray()
                            ));

                            $totalTopics = count($chapterTopicIds);

                            $progressPercent = $totalTopics > 0
                                ? round(($completedTopics / $totalTopics) * 100, 2)
                                : 0;

                            return [

                                'chapter_id' => $chapter->id,

                                'chapter_title' => $chapter->title,

                                'total_topics' => $totalTopics,

                                'completed_topics' => $completedTopics,

                                'started_topics' => $startedTopics,

                                'progress_percent' => $progressPercent
                            ];
                        })
                    ];
                })
            ];
        });

        /*
        |-----------------------------------------
        | 🔹 AVG SCORES
        |-----------------------------------------
        */
        $avgTopicScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic');
            })
            ->avg('percentage');

        $avgExamScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'level');
            })
            ->avg('percentage');

        $overallAvgScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->avg('percentage');

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
                    'id' => $lastCompletedAttempt?->assessment?->assessmentable?->id,
                    'title' => $lastCompletedAttempt?->assessment?->assessmentable?->title
                ],

                'progress_percent' => $progressPercent,

                'completed_lessons' => $completedLessons,

                'total_lessons' => $totalLessons,

                'pending_quizzes' => $pendingTopicQuizCount,

                'last_activity_date' => $current?->updated_at,

                'cta' => [
                    'type' => 'resume',
                    'topic_id' => $current?->topic_id
                ]
            ],

            'levels' => $levelCards,

            'current_topic_contents' => $contents,

            'stats' => [

                'total_levels' => $levels->count(),

                'completed_levels' => count($completedLevelIds),

                'remaining_levels' => $levels->count() - count($completedLevelIds),

                'total_topics' => $totalLessons,

                'completed_topics' => $completedLessons,

                'avg_topic_score' => round($avgTopicScore ?? 0, 2),

                'avg_exam_score' => round($avgExamScore ?? 0, 2),

                'overall_avg_score' => round($overallAvgScore ?? 0, 2),

                'modules_progress' => $moduleStats,

                'chapters_progress' => $chapterStats,

                'current_topic_progress' => $currentTopicProgress,

                'certificates_earned' => Certification::where('user_id', $userId)->count()
            ],

            'last_certificate' => $certificate,

            'next_action' => $nextAction
        ];
    }
}