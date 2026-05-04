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
            ->where('is_completed', false) // 🔥 MOST IMPORTANT
            ->whereNotNull('topic_id')
            ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
            ->orderBy('id') // sequence wise
            ->first();


        if (!$current) {
            $current = UserProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->latest('completed_at')
                ->with(['topic.program', 'topic.level', 'topic.module', 'topic.chapter'])
                ->first();
        }

        $lastCompleted = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {
                $q->where('type', 'topic');
            })
            ->with(['assessment.assessmentable']) // topic relation
            ->latest('submitted_at')
            ->first();
        /*
    |-----------------------------------------
    | 🔹 COMPLETED TOPICS (CONTENT BASED)
    |-----------------------------------------
    */
        $completedTopicIds = \DB::table('topic_contents as tc')
            ->select('tc.topic_id')
            ->join('user_content_progress as ucp', function ($join) use ($userId) {
                $join->on('tc.id', '=', 'ucp.topic_content_id')
                    ->where('ucp.user_id', $userId)
                    ->where('ucp.is_read', 1);
            })
            ->groupBy('tc.topic_id')
            ->havingRaw('COUNT(tc.id) = (
            SELECT COUNT(*) FROM topic_contents 
            WHERE topic_contents.topic_id = tc.topic_id
        )')
            ->pluck('topic_id');

        /*
|-----------------------------------------
| 🔹 TOTAL TOPICS (MASTER BASED)
|-----------------------------------------
*/

        // 1. Get ALL topics (master data)
        $allTopicIds = \App\Models\Topic::pluck('id');

        // 2. Total topics
        $totalLessons = $allTopicIds->count();

        /*
|-----------------------------------------
| 🔹 COMPLETED TOPICS (CONTENT BASED)
|-----------------------------------------
*/

        // Topic wise completion check
        $completedTopicIds = \DB::table('topic_contents as tc')
            ->select('tc.topic_id')
            ->leftJoin('user_content_progress as ucp', function ($join) use ($userId) {
                $join->on('tc.id', '=', 'ucp.topic_content_id')
                    ->where('ucp.user_id', $userId)
                    ->where('ucp.is_read', 1);
            })
            ->groupBy('tc.topic_id')
            ->havingRaw('COUNT(tc.id) = COUNT(ucp.id)') // 🔥 ALL content must be read
            ->pluck('tc.topic_id');

        // 3. Completed topics count
        $completedLessons = $completedTopicIds->count();

        /*
|-----------------------------------------
| 🔹 PROGRESS %
|-----------------------------------------
*/
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
    | 🔹 LOAD FULL STRUCTURE
    |-----------------------------------------
    */
        $levels = Level::with('modules.chapters.topics')->get();

        $completedLevelIds = [];
        $moduleStats = [];
        $chapterStats = [];

        /*
    |-----------------------------------------
    | 🔹 HIERARCHY PROGRESS CALCULATION
    |-----------------------------------------
    */
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

                $moduleTopics = $module->chapters->flatMap->topics;
                $moduleTopicIds = $moduleTopics->pluck('id')->toArray();

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

            $levelTopics = $level->modules->flatMap->chapters->flatMap->topics;
            $levelTopicIds = $levelTopics->pluck('id')->toArray();

            $completed = count(array_intersect($levelTopicIds, $completedTopicIds->toArray()));

            if (count($levelTopicIds) > 0 && $completed === count($levelTopicIds)) {
                $completedLevelIds[] = $level->id;
            }
        }

        /*
    |-----------------------------------------
    | 🔹 NEXT NAVIGATION ENGINE
    |-----------------------------------------
    */
        $nextAction = null;

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

        /*
    |-----------------------------------------
    | 🔹 CERTIFICATE
    |-----------------------------------------
    */
        $certificate = Certification::where('user_id', $userId)
            ->latest('issued_at')
            ->first();
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

                // ❗ topic ko lesson treat kar rahe hain (as per requirement)
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
| 🔹 AVG SCORES
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

        // Overall Avg (optional but useful)
        $overallAvgScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->avg('percentage');


        /*
|-----------------------------------------
| 🔹 CURRENT TOPIC PROGRESS (CONTENT BASED)
|-----------------------------------------
*/
        $currentTopicProgress = null;

        if ($current && $current->topic_id) {

            $topicId = $current->topic_id;

            // total content in topic
            $totalContent = TopicContent::where('topic_id', $topicId)->count();

            // read content
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
                'pending_quizzes' => 0,

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
