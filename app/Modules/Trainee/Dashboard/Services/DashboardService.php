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
        |--------------------------------------------------
        | 🔹 CURRENT + LAST COMPLETED
        |--------------------------------------------------
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

        /*
        |--------------------------------------------------
        | 🔹 FALLBACK
        |--------------------------------------------------
        */
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
        |--------------------------------------------------
        | 🔹 LAST PASSED TOPIC QUIZ
        |--------------------------------------------------
        */
        $lastCompletedAttempt = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true);
            })
            ->with('assessment.assessmentable')
            ->latest('submitted_at')
            ->first();

        /*
        |--------------------------------------------------
        | 🔹 PASSED TOPICS
        |--------------------------------------------------
        */
        $completedTopicIds = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true);
            })
            ->with('assessment')
            ->get()
            ->map(function ($attempt) {

                return $attempt->assessment?->assessmentable_id;
            })
            ->filter()
            ->unique()
            ->values();

        /*
        |--------------------------------------------------
        | 🔹 STARTED TOPICS
        |--------------------------------------------------
        */
        $startedTopicIds = DB::table('topic_contents as tc')
            ->join('topics as t', 't.id', '=', 'tc.topic_id')

            ->whereNull('t.deleted_at')
            ->where('t.status', true)

            ->whereNull('tc.deleted_at')
            ->where('tc.status', true)
            ->where('tc.publish_status', 'published')

            ->join('user_content_progress as ucp', function ($join) use ($userId) {

                $join->on('tc.id', '=', 'ucp.topic_content_id')
                    ->where('ucp.user_id', $userId)
                    ->where('ucp.is_read', 1);
            })
            ->distinct()
            ->pluck('tc.topic_id');

        /*
        |--------------------------------------------------
        | 🔹 TOTAL TOPICS
        |--------------------------------------------------
        */
        $totalLessons = Topic::where('status', true)
            ->count();

        $completedLessons = $completedTopicIds->count();

        $progressPercent = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 2)
            : 0;

        /*
        |--------------------------------------------------
        | 🔹 CURRENT TOPIC CONTENTS
        |--------------------------------------------------
        */
        $contents = [];

        if ($current && $current->topic_id) {

            $contents = TopicContent::where('topic_id', $current->topic_id)
                ->where('status', true)
                ->where('publish_status', 'published')
                ->select('id', 'title', 'type')
                ->orderBy('order')
                ->get();
        }

        /*
        |--------------------------------------------------
        | 🔹 LOAD STRUCTURE
        |--------------------------------------------------
        */
        $levels = Level::with([

            'modules' => function ($q) {
                $q->where('status', true);
            },

            'modules.chapters' => function ($q) {
                $q->where('status', true);
            },

            'modules.chapters.topics' => function ($q) {
                $q->where('status', true);
            },

            'modules.chapters.topics.program'

        ])
            ->where('status', true)
            ->get();

        /*
        |--------------------------------------------------
        | 🔹 CERTIFICATIONS
        |--------------------------------------------------
        */
        $certifications = Certification::where('user_id', $userId)
            ->where('status', true)
            ->get();

        $moduleCertifications = $certifications
            ->where('type', 'module')
            ->keyBy('module_id');

        $chapterCertifications = $certifications
            ->where('type', 'chapter')
            ->keyBy('chapter_id');

        $topicCertifications = $certifications
            ->where('type', 'topic')
            ->keyBy('topic_id');

        $levelCertifications = $certifications
            ->where('type', 'level')
            ->keyBy('level_id');

        $completedLevelIds = [];

        $moduleStats = [];

        $chapterStats = [];

        /*
        |--------------------------------------------------
        | 🔹 LEVEL / MODULE / CHAPTER STATS
        |--------------------------------------------------
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

                    $chapterPassed = isset(
                        $chapterCertifications[$chapter->id]
                    );

                    $chapterStats[] = [

                        'chapter_id' => $chapter->id,

                        'chapter_title' => $chapter->title,

                        'module_id' => $module->id,

                        'level_id' => $level->id,

                        'total_topics' => count($chapterTopicIds),

                        'completed_topics' => $chapterCompletedTopics,

                        'progress_percent' => count($chapterTopicIds) > 0
                            ? round(($chapterCompletedTopics / count($chapterTopicIds)) * 100, 2)
                            : 0,

                        'is_passed' => $chapterPassed
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

                $modulePassed = isset(
                    $moduleCertifications[$module->id]
                );

                $moduleStats[] = [

                    'module_id' => $module->id,

                    'module_title' => $module->title,

                    'level_id' => $level->id,

                    'total_topics' => count($moduleTopicIds),

                    'completed_topics' => $moduleCompletedTopics,

                    'progress_percent' => count($moduleTopicIds) > 0
                        ? round(($moduleCompletedTopics / count($moduleTopicIds)) * 100, 2)
                        : 0,

                    'is_passed' => $modulePassed
                ];
            }

            if (isset($levelCertifications[$level->id])) {

                $completedLevelIds[] = $level->id;
            }
        }

        /*
        |--------------------------------------------------
        | 🔹 PENDING ASSESSMENTS
        |--------------------------------------------------
        */
        $pendingAssessment = Assessment::where('status', true)

            ->whereDoesntHave('attempts', function ($q) use ($userId) {

                $q->where('user_id', $userId)
                    ->where('status', 'passed');
            })

            ->where(function ($q) use (
                $completedTopicIds,
                $moduleCertifications,
                $chapterCertifications
            ) {

                /*
                |-----------------------------------------
                | TOPIC
                |-----------------------------------------
                */
                $q->orWhere(function ($qq) use ($completedTopicIds) {

                    $qq->where('type', 'topic')
                        ->whereIn(
                            'assessmentable_id',
                            $completedTopicIds
                        );
                });

                /*
                |-----------------------------------------
                | CHAPTER
                |-----------------------------------------
                */
                $q->orWhere(function ($qq) use (
                    $chapterCertifications
                ) {

                    $qq->where('type', 'chapter')
                        ->whereNotIn(
                            'assessmentable_id',
                            $chapterCertifications->keys()
                        );
                });

                /*
                |-----------------------------------------
                | MODULE
                |-----------------------------------------
                */
                $q->orWhere(function ($qq) use (
                    $moduleCertifications
                ) {

                    $qq->where('type', 'module')
                        ->whereNotIn(
                            'assessmentable_id',
                            $moduleCertifications->keys()
                        );
                });

                /*
                |-----------------------------------------
                | LEVEL
                |-----------------------------------------
                */
                $q->orWhere('type', 'level');
            })

            ->with('assessmentable')
            ->first();

        /*
        |--------------------------------------------------
        | 🔹 NEXT ACTION
        |--------------------------------------------------
        */
        $nextAction = null;

        if ($pendingAssessment) {

            $entity = $pendingAssessment->assessmentable;

            $nextAction = [

                'type' => $pendingAssessment->type . '_exam',

                'assessment_id' => $pendingAssessment->id,

                'assessment_title' => $pendingAssessment->title,

                strtolower($pendingAssessment->type) => [

                    'id' => $entity?->id,

                    'title' => $entity?->title
                ]
            ];
        }

        /*
        |--------------------------------------------------
        | 🔹 LAST CERTIFICATE
        |--------------------------------------------------
        */
        $certificate = Certification::where('user_id', $userId)
            ->where('status', true)
            ->latest('issued_at')
            ->first();

        /*
        |--------------------------------------------------
        | 🔹 CURRENT TOPIC PROGRESS
        |--------------------------------------------------
        */
        $currentTopicProgress = null;

        if ($current && $current->topic_id) {

            $topicId = $current->topic_id;

            $totalContent = TopicContent::where('topic_id', $topicId)
                ->where('status', true)
                ->where('publish_status', 'published')
                ->count();

            $readContent = UserContentProgress::where('user_id', $userId)
                ->whereIn('topic_content_id', function ($q) use ($topicId) {

                    $q->select('id')
                        ->from('topic_contents')
                        ->where('topic_id', $topicId)
                        ->where('status', true)
                        ->where('publish_status', 'published');
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
        |--------------------------------------------------
        | 🔹 LEVEL CARDS
        |--------------------------------------------------
        */
        $levelCards = $levels->map(function ($level) use (
            $completedTopicIds,
            $startedTopicIds,
            $levelCertifications,
            $moduleCertifications,
            $chapterCertifications
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

            if (isset($levelCertifications[$level->id])) {

                $status = 'completed';
            } elseif ($startedTopics > 0) {

                $status = 'unlocked';
            }

            return [

                'id' => $level->id,

                'title' => $level->title,

                'description' => $level->description,

                'status' => $status,

                'is_passed' => isset(
                    $levelCertifications[$level->id]
                ),

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
                    $startedTopicIds,
                    $moduleCertifications,
                    $chapterCertifications
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

                        'is_passed' => isset(
                            $moduleCertifications[$module->id]
                        ),

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
                            $startedTopicIds,
                            $chapterCertifications
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

                                'is_passed' => isset(
                                    $chapterCertifications[$chapter->id]
                                ),

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
        |--------------------------------------------------
        | 🔹 AVG SCORES
        |--------------------------------------------------
        */
        $avgTopicScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true);
            })
            ->avg('percentage');

        $avgExamScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) {

                $q->whereIn('type', [
                    'chapter',
                    'module',
                    'level'
                ])
                    ->where('status', true);
            })
            ->avg('percentage');

        $overallAvgScore = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->avg('percentage');

        /*
        |--------------------------------------------------
        | 🔹 FINAL RESPONSE
        |--------------------------------------------------
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

                'pending_quizzes' => Assessment::where('status', true)
                    ->whereDoesntHave('attempts', function ($q) use ($userId) {

                        $q->where('user_id', $userId)
                            ->where('status', 'passed');
                    })
                    ->count(),

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

                'certificates_earned' => Certification::where('user_id', $userId)
                    ->where('status', true)
                    ->count()
            ],

            'last_certificate' => $certificate,

            'next_action' => $nextAction
        ];
    }
}
