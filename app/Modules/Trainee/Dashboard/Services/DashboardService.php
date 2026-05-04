<?php

namespace App\Modules\Trainee\Dashboard\Services;

use App\Models\UserProgress;
use App\Models\UserContentProgress;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\TopicContent;
use App\Models\Certification;
use App\Models\Level;
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
            ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
            ->orderBy('id')
            ->first();

        // fallback (all topics completed)
        if (!$current) {
            $current = UserProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->latest('completed_at')
                ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
                ->first();
        }

        /*
    |-----------------------------------------
    | 🔹 LAST COMPLETED (QUIZ BASED - FIXED)
    |-----------------------------------------
    */
        $lastCompletedAttempt = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', fn($q) => $q->where('type', 'topic'))
            ->with('assessment.assessmentable')
            ->latest('submitted_at')
            ->first();

        /*
    |-----------------------------------------
    | 🔹 COMPLETED TOPICS (CONTENT BASED)
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
    | 🔹 TOTAL TOPICS (MASTER)
    |-----------------------------------------
    */
        $totalLessons = \App\Models\Topic::count();
        $completedLessons = $completedTopicIds->count();

        $progressPercent = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 2)
            : 0;

        /*
    |-----------------------------------------
    | 🔹 CURRENT CONTENTS
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
    | 🔹 STRUCTURE LOAD
    |-----------------------------------------
    */
        $levels = Level::with('modules.chapters.topics')->get();

        $completedLevelIds = [];
        $moduleStats = [];
        $chapterStats = [];

        foreach ($levels as $level) {

            foreach ($level->modules as $module) {

                foreach ($module->chapters as $chapter) {

                    $topicIds = $chapter->topics->pluck('id')->toArray();
                    $completed = count(array_intersect($topicIds, $completedTopicIds->toArray()));

                    $chapterStats[] = [
                        'chapter_id' => $chapter->id,
                        'chapter_title' => $chapter->title,
                        'module_id' => $module->id,
                        'level_id' => $level->id,
                        'total_topics' => count($topicIds),
                        'completed_topics' => $completed,
                        'progress_percent' => count($topicIds) > 0 ? round(($completed / count($topicIds)) * 100, 2) : 0
                    ];
                }

                $moduleTopicIds = $module->chapters->flatMap->topics->pluck('id')->toArray();
                $completed = count(array_intersect($moduleTopicIds, $completedTopicIds->toArray()));

                $moduleStats[] = [
                    'module_id' => $module->id,
                    'module_title' => $module->title,
                    'level_id' => $level->id,
                    'total_topics' => count($moduleTopicIds),
                    'completed_topics' => $completed,
                    'progress_percent' => count($moduleTopicIds) > 0 ? round(($completed / count($moduleTopicIds)) * 100, 2) : 0
                ];
            }

            $levelTopicIds = $level->modules->flatMap->chapters->flatMap->topics->pluck('id')->toArray();
            $completed = count(array_intersect($levelTopicIds, $completedTopicIds->toArray()));

            if (count($levelTopicIds) > 0 && $completed === count($levelTopicIds)) {
                $completedLevelIds[] = $level->id;
            }
        }

        /*
    |-----------------------------------------
    | 🔹 QUIZ UNLOCK COUNT (FIXED)
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
    | 🔹 NEXT ACTION ENGINE (PRIORITY FIXED)
    |-----------------------------------------
    */
        $pendingTopicQuiz = Assessment::where('type', 'topic')
            ->whereIn('assessmentable_id', $completedTopicIds)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'passed');
            })
            ->with('assessmentable')
            ->first();

        $pendingLevelExam = Assessment::where('type', 'level')
            ->whereIn('assessmentable_id', $completedLevelIds)
            ->whereDoesntHave('attempts', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'passed');
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
    | 🔹 CERTIFICATE
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
                    $q->select('id')->from('topic_contents')->where('topic_id', $topicId);
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

        $levelCards = $levels->map(function ($level) use ($completedTopicIds) {

            $levelTopicIds = $level->modules
                ->flatMap->chapters
                ->flatMap->topics
                ->pluck('id')
                ->toArray();

            $completed = count(array_intersect(
                $levelTopicIds,
                $completedTopicIds->toArray()
            ));

            $status = 'locked';

            if ($completed === count($levelTopicIds) && count($levelTopicIds) > 0) {
                $status = 'completed';
            } elseif ($completed > 0) {
                $status = 'unlocked';
            }

            return [
                'id' => $level->id,
                'title' => $level->title,
                'description' => $level->description,

                'status' => $status,

                'total_modules' => $level->modules->count(),
                'total_topics' => count($levelTopicIds),

                // topic = lesson (as per your requirement)
                'total_lessons' => count($levelTopicIds),

                'completion_percent' => count($levelTopicIds) > 0
                    ? round(($completed / count($levelTopicIds)) * 100, 2)
                    : 0,

                'cta' => $status === 'completed'
                    ? 'view_certificate'
                    : ($status === 'unlocked' ? 'continue' : 'start')
            ];
        });

        /*
|-----------------------------------------
| 🔹 AVG SCORES (ADD BACK)
|-----------------------------------------
*/

        // Topic Quiz Avg
        $avgTopicScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {
                $q->where('type', 'topic');
            })
            ->avg('percentage');

        // Level Exam Avg
        $avgExamScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {
                $q->where('type', 'level');
            })
            ->avg('percentage');

        // Overall Avg
        $overallAvgScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->avg('percentage');
        /*
    |-----------------------------------------
    | 🔹 FINAL RESPONSE (UNCHANGED STRUCTURE)
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
