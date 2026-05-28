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
use App\Services\HierarchyVisibilityService;

class DashboardService
{
    protected $visibilityService;

    public function __construct(
        HierarchyVisibilityService $visibilityService
    ) {
        $this->visibilityService = $visibilityService;
    }

    public function getDashboard($userId)
    {

        /*
        |--------------------------------------------------------------------------
        | 🔹 ACTIVE HIERARCHY IDS
        |--------------------------------------------------------------------------
        */

        $activeTopicIds = $this->visibilityService
            ->activeTopicIds();

        $activeChapterIds = $this->visibilityService
            ->activeChapterIds();

        $activeModuleIds = $this->visibilityService
            ->activeModuleIds();

        $activeLevelIds = $this->visibilityService
            ->activeLevelIds();

        /*
        |--------------------------------------------------------------------------
        | 🔹 CURRENT + LAST COMPLETED
        |--------------------------------------------------------------------------
        */

        $current = UserProgress::where('user_id', $userId)
            ->where('is_unlocked', true)
            ->where('is_completed', false)
            ->whereNotNull('topic_id')

            ->whereHas('topic', function ($q) use (
                $activeTopicIds
            ) {

                $q->whereIn('id', $activeTopicIds);
            })

            ->with([
                'topic.program',
                'topic.level',
                'topic.module',
                'topic.chapter'
            ])
            ->orderBy('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 🔹 FALLBACK
        |--------------------------------------------------------------------------
        */

        if (!$current) {

            $current = UserProgress::where('user_id', $userId)
                ->where('is_completed', true)
                ->whereNotNull('topic_id')

                ->whereHas('topic', function ($q) use (
                    $activeTopicIds
                ) {

                    $q->whereIn('id', $activeTopicIds);
                })

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
        |--------------------------------------------------------------------------
        | 🔹 LAST PASSED TOPIC QUIZ
        |--------------------------------------------------------------------------
        */

        $lastCompletedAttempt = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')

            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->with('assessment.assessmentable')
            ->latest('submitted_at')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 🔹 PASSED TOPICS
        |--------------------------------------------------------------------------
        */

        $completedTopicIds = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')

            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->with('assessment')
            ->get()

            ->map(function ($attempt) {

                return $attempt->assessment?->assessmentable_id;
            })

            ->filter(function ($id) use (
                $activeTopicIds
            ) {

                return in_array($id, $activeTopicIds);
            })

            ->unique()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 🔹 STARTED TOPICS
        |--------------------------------------------------------------------------
        */

        $startedTopicIds = DB::table('topic_contents as tc')

            ->join(
                'topics as t',
                't.id',
                '=',
                'tc.topic_id'
            )

            ->whereNull('t.deleted_at')
            ->where('t.status', true)

            ->whereNull('tc.deleted_at')
            ->where('tc.status', true)
            ->where('tc.publish_status', 'published')

            ->join(
                'user_content_progress as ucp',
                function ($join) use ($userId) {

                    $join->on(
                        'tc.id',
                        '=',
                        'ucp.topic_content_id'
                    )
                        ->where('ucp.user_id', $userId)
                        ->where('ucp.is_read', 1);
                }
            )

            ->distinct()
            ->pluck('tc.topic_id');

        /*
        |--------------------------------------------------------------------------
        | 🔹 TOTAL TOPICS
        |--------------------------------------------------------------------------
        */

        $totalLessons = count($activeTopicIds);

        $completedLessons = $completedTopicIds->count();

        $progressPercent = $totalLessons > 0
            ? round(
                ($completedLessons / $totalLessons) * 100,
                2
            )
            : 0;

        /*
        |--------------------------------------------------------------------------
        | 🔹 CURRENT TOPIC CONTENTS
        |--------------------------------------------------------------------------
        */

        $contents = [];

        if ($current && $current->topic_id) {

            $contents = TopicContent::where(
                'topic_id',
                $current->topic_id
            )
                ->where('status', true)
                ->whereNull('deleted_at')
                ->where(
                    'publish_status',
                    'published'
                )

                ->select(
                    'id',
                    'title',
                    'type'
                )

                ->orderBy('order')
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 LOAD STRUCTURE
        |--------------------------------------------------------------------------
        */

        $levels = Level::with([

            'modules' => function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            },

            'modules.chapters' => function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            },

            'modules.chapters.topics' => function ($q) {

                $q->where('status', true)
                    ->whereNull('deleted_at');
            },

            'modules.chapters.topics.program'

        ])
            ->where('status', true)
            ->whereNull('deleted_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 🔹 CERTIFICATIONS
        |--------------------------------------------------------------------------
        */

        $certifications = Certification::where(
            'user_id',
            $userId
        )
            ->where('status', true)
            ->whereNull('deleted_at')
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
        |--------------------------------------------------------------------------
        | 🔹 LEVEL / MODULE / CHAPTER STATS
        |--------------------------------------------------------------------------
        */

        foreach ($levels as $level) {

            foreach ($level->modules as $module) {

                foreach ($module->chapters as $chapter) {

                    $chapterTopicIds = $chapter->topics
                        ->pluck('id')
                        ->toArray();

                    $chapterCompletedTopics = count(
                        array_intersect(
                            $chapterTopicIds,
                            $completedTopicIds->toArray()
                        )
                    );

                    $chapterPassed = isset(
                        $chapterCertifications[$chapter->id]
                    );

                    $chapterStats[] = [

                        'chapter_id' => $chapter->id,

                        'chapter_title' => $chapter->title,

                        'module_id' => $module->id,

                        'level_id' => $level->id,

                        'total_topics' => count(
                            $chapterTopicIds
                        ),

                        'completed_topics' =>
                        $chapterCompletedTopics,

                        'progress_percent' =>
                        count($chapterTopicIds) > 0
                            ? round(
                                (
                                    $chapterCompletedTopics
                                    / count($chapterTopicIds)
                                ) * 100,
                                2
                            )
                            : 0,

                        'is_passed' => $chapterPassed
                    ];
                }

                $moduleTopicIds = $module->chapters
                    ->flatMap->topics
                    ->pluck('id')
                    ->toArray();

                $moduleCompletedTopics = count(
                    array_intersect(
                        $moduleTopicIds,
                        $completedTopicIds->toArray()
                    )
                );

                $modulePassed = isset(
                    $moduleCertifications[$module->id]
                );

                $moduleStats[] = [

                    'module_id' => $module->id,

                    'module_title' => $module->title,

                    'level_id' => $level->id,

                    'total_topics' => count(
                        $moduleTopicIds
                    ),

                    'completed_topics' =>
                    $moduleCompletedTopics,

                    'progress_percent' =>
                    count($moduleTopicIds) > 0
                        ? round(
                            (
                                $moduleCompletedTopics
                                / count($moduleTopicIds)
                            ) * 100,
                            2
                        )
                        : 0,

                    'is_passed' => $modulePassed
                ];
            }

            if (isset($levelCertifications[$level->id])) {

                $completedLevelIds[] = $level->id;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 PENDING ASSESSMENTS
        |--------------------------------------------------------------------------
        */

        $pendingAssessment = Assessment::where(
            'status',
            true
        )
            ->whereNull('deleted_at')

            ->whereDoesntHave(
                'attempts',
                function ($q) use ($userId) {

                    $q->where('user_id', $userId)
                        ->where('status', 'passed');
                }
            )

            ->where(function ($q) use (
                $activeTopicIds,
                $activeChapterIds,
                $activeModuleIds,
                $activeLevelIds
            ) {

                /*
                |--------------------------------------------------------------------------
                | TOPIC
                |--------------------------------------------------------------------------
                */

                $q->orWhere(function ($qq) use (
                    $activeTopicIds
                ) {

                    $qq->where('type', 'topic')
                        ->whereIn(
                            'assessmentable_id',
                            $activeTopicIds
                        );
                });

                /*
                |--------------------------------------------------------------------------
                | CHAPTER
                |--------------------------------------------------------------------------
                */

                $q->orWhere(function ($qq) use (
                    $activeChapterIds
                ) {

                    $qq->where('type', 'chapter')
                        ->whereIn(
                            'assessmentable_id',
                            $activeChapterIds
                        );
                });

                /*
                |--------------------------------------------------------------------------
                | MODULE
                |--------------------------------------------------------------------------
                */

                $q->orWhere(function ($qq) use (
                    $activeModuleIds
                ) {

                    $qq->where('type', 'module')
                        ->whereIn(
                            'assessmentable_id',
                            $activeModuleIds
                        );
                });

                /*
                |--------------------------------------------------------------------------
                | LEVEL
                |--------------------------------------------------------------------------
                */

                $q->orWhere(function ($qq) use (
                    $activeLevelIds
                ) {

                    $qq->where('type', 'level')
                        ->whereIn(
                            'assessmentable_id',
                            $activeLevelIds
                        );
                });
            })

            ->with('assessmentable')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 🔹 NEXT ACTION
        |--------------------------------------------------------------------------
        */

        $nextAction = null;

        if ($pendingAssessment) {

            $entity = $pendingAssessment->assessmentable;

            $nextAction = [

                'type' =>
                $pendingAssessment->type . '_exam',

                'assessment_id' =>
                $pendingAssessment->id,

                'assessment_title' =>
                $pendingAssessment->title,

                strtolower(
                    $pendingAssessment->type
                ) => [

                    'id' => $entity?->id,

                    'title' => $entity?->title
                ]
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 LAST CERTIFICATE
        |--------------------------------------------------------------------------
        */

        $certificate = Certification::where(
            'user_id',
            $userId
        )
            ->where('status', true)
            ->whereNull('deleted_at')
            ->latest('issued_at')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 🔹 CURRENT TOPIC PROGRESS
        |--------------------------------------------------------------------------
        */

        $currentTopicProgress = null;

        if ($current && $current->topic_id) {

            $topicId = $current->topic_id;

            $totalContent = TopicContent::where(
                'topic_id',
                $topicId
            )
                ->where('status', true)
                ->whereNull('deleted_at')
                ->where(
                    'publish_status',
                    'published'
                )
                ->count();

            $readContent = UserContentProgress::where(
                'user_id',
                $userId
            )

                ->whereIn(
                    'topic_content_id',
                    function ($q) use ($topicId) {

                        $q->select('id')
                            ->from('topic_contents')

                            ->where(
                                'topic_id',
                                $topicId
                            )

                            ->where('status', true)
                            ->whereNull('deleted_at')

                            ->where(
                                'publish_status',
                                'published'
                            );
                    }
                )

                ->where('is_read', 1)
                ->count();

            $currentTopicProgress = [

                'topic_id' => $topicId,

                'total_contents' => $totalContent,

                'read_contents' => $readContent,

                'progress_percent' =>
                $totalContent > 0
                    ? round(
                        (
                            $readContent
                            / $totalContent
                        ) * 100,
                        2
                    )
                    : 0
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 🔹 LEVEL CARDS
        |--------------------------------------------------------------------------
        */

        $levelCards = $levels->map(
            function ($level) use (
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

                $completedTopics = count(
                    array_intersect(
                        $levelTopicIds,
                        $completedTopicIds->toArray()
                    )
                );

                $startedTopics = count(
                    array_intersect(
                        $levelTopicIds,
                        $startedTopicIds->toArray()
                    )
                );

                $totalTopics = count(
                    $levelTopicIds
                );

                $progressPercent = $totalTopics > 0
                    ? round(
                        (
                            $completedTopics
                            / $totalTopics
                        ) * 100,
                        2
                    )
                    : 0;

                $status = 'locked';

                if (
                    isset(
                        $levelCertifications[$level->id]
                    )
                ) {

                    $status = 'completed';
                } elseif ($startedTopics > 0) {

                    $status = 'unlocked';
                }

                return [

                    'id' => $level->id,

                    'title' => $level->title,

                    'description' =>
                    $level->description,

                    'status' => $status,

                    'is_passed' => isset(
                        $levelCertifications[$level->id]
                    ),

                    'total_modules' =>
                    $level->modules->count(),

                    'total_topics' => $totalTopics,

                    'total_lessons' => $totalTopics,

                    'completed_topics' =>
                    $completedTopics,

                    'started_topics' =>
                    $startedTopics,

                    'completion_percent' =>
                    $progressPercent,

                    'cta' => $status === 'completed'
                        ? 'view_certificate'
                        : (
                            $status === 'unlocked'
                            ? 'continue'
                            : 'start'
                        ),

                    'modules' => $level->modules->map(
                        function ($module) use (
                            $completedTopicIds,
                            $startedTopicIds,
                            $moduleCertifications,
                            $chapterCertifications
                        ) {

                            $moduleTopicIds = $module
                                ->chapters
                                ->flatMap->topics
                                ->pluck('id')
                                ->toArray();

                            $completedTopics = count(
                                array_intersect(
                                    $moduleTopicIds,
                                    $completedTopicIds
                                        ->toArray()
                                )
                            );

                            $startedTopics = count(
                                array_intersect(
                                    $moduleTopicIds,
                                    $startedTopicIds
                                        ->toArray()
                                )
                            );

                            $totalTopics = count(
                                $moduleTopicIds
                            );

                            $progressPercent =
                                $totalTopics > 0
                                ? round(
                                    (
                                        $completedTopics
                                        / $totalTopics
                                    ) * 100,
                                    2
                                )
                                : 0;

                            return [

                                'module_id' =>
                                $module->id,

                                'module_title' =>
                                $module->title,

                                'is_passed' => isset(
                                    $moduleCertifications[$module->id]
                                ),

                                'total_topics' =>
                                $totalTopics,

                                'completed_topics' =>
                                $completedTopics,

                                'started_topics' =>
                                $startedTopics,

                                'progress_percent' =>
                                $progressPercent,

                                'chapters' =>
                                $module->chapters->map(
                                    function (
                                        $chapter
                                    ) use (
                                        $completedTopicIds,
                                        $startedTopicIds,
                                        $chapterCertifications
                                    ) {

                                        $chapterTopicIds =
                                            $chapter->topics
                                            ->pluck('id')
                                            ->toArray();

                                        $completedTopics =
                                            count(
                                                array_intersect(
                                                    $chapterTopicIds,
                                                    $completedTopicIds
                                                        ->toArray()
                                                )
                                            );

                                        $startedTopics =
                                            count(
                                                array_intersect(
                                                    $chapterTopicIds,
                                                    $startedTopicIds
                                                        ->toArray()
                                                )
                                            );

                                        $totalTopics =
                                            count(
                                                $chapterTopicIds
                                            );

                                        $progressPercent =
                                            $totalTopics > 0
                                            ? round(
                                                (
                                                    $completedTopics
                                                    / $totalTopics
                                                ) * 100,
                                                2
                                            )
                                            : 0;

                                        return [

                                            'chapter_id' =>
                                            $chapter->id,

                                            'chapter_title' =>
                                            $chapter->title,

                                            'is_passed' =>
                                            isset(
                                                $chapterCertifications[$chapter->id]
                                            ),

                                            'total_topics' =>
                                            $totalTopics,

                                            'completed_topics' =>
                                            $completedTopics,

                                            'started_topics' =>
                                            $startedTopics,

                                            'progress_percent' =>
                                            $progressPercent
                                        ];
                                    }
                                )
                            ];
                        }
                    )
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | 🔹 AVG SCORES
        |--------------------------------------------------------------------------
        */

        $avgTopicScore = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('status', 'passed')

            ->whereHas('assessment', function ($q) {

                $q->where('type', 'topic')
                    ->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->avg('percentage');

        $avgExamScore = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('status', 'passed')

            ->whereHas('assessment', function ($q) {

                $q->whereIn('type', [
                    'chapter',
                    'module',
                    'level'
                ])
                    ->where('status', true)
                    ->whereNull('deleted_at');
            })

            ->avg('percentage');

        $overallAvgScore = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('status', 'passed')
            ->avg('percentage');

        /*
        |--------------------------------------------------------------------------
        | 🔹 FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'current_learning' => [

                'program' => [
                    'id' =>
                    $current?->topic?->program?->id,

                    'title' =>
                    $current?->topic?->program?->title
                ],

                'level' => [
                    'id' =>
                    $current?->topic?->level?->id,

                    'title' =>
                    $current?->topic?->level?->title
                ],

                'module' => [
                    'id' =>
                    $current?->topic?->module?->id,

                    'title' =>
                    $current?->topic?->module?->title
                ],

                'chapter' => [
                    'id' =>
                    $current?->topic?->chapter?->id,

                    'title' =>
                    $current?->topic?->chapter?->title
                ],

                'topic' => [
                    'id' =>
                    $current?->topic?->id,

                    'title' =>
                    $current?->topic?->title
                ],

                'last_completed_topic' => [
                    'id' =>
                    $lastCompletedAttempt?->assessment
                        ?->assessmentable?->id,

                    'title' =>
                    $lastCompletedAttempt?->assessment
                        ?->assessmentable?->title
                ],

                'progress_percent' =>
                $progressPercent,

                'completed_lessons' =>
                $completedLessons,

                'total_lessons' =>
                $totalLessons,

                'pending_quizzes' =>
                Assessment::where(
                    'status',
                    true
                )
                    ->whereNull('deleted_at')

                    ->whereDoesntHave(
                        'attempts',
                        function ($q) use ($userId) {

                            $q->where(
                                'user_id',
                                $userId
                            )
                                ->where(
                                    'status',
                                    'passed'
                                );
                        }
                    )

                    ->where(function ($q) use (
                        $activeTopicIds,
                        $activeChapterIds,
                        $activeModuleIds,
                        $activeLevelIds
                    ) {

                        $q->orWhere(
                            function ($qq) use (
                                $activeTopicIds
                            ) {

                                $qq->where(
                                    'type',
                                    'topic'
                                )
                                    ->whereIn(
                                        'assessmentable_id',
                                        $activeTopicIds
                                    );
                            }
                        );

                        $q->orWhere(
                            function ($qq) use (
                                $activeChapterIds
                            ) {

                                $qq->where(
                                    'type',
                                    'chapter'
                                )
                                    ->whereIn(
                                        'assessmentable_id',
                                        $activeChapterIds
                                    );
                            }
                        );

                        $q->orWhere(
                            function ($qq) use (
                                $activeModuleIds
                            ) {

                                $qq->where(
                                    'type',
                                    'module'
                                )
                                    ->whereIn(
                                        'assessmentable_id',
                                        $activeModuleIds
                                    );
                            }
                        );

                        $q->orWhere(
                            function ($qq) use (
                                $activeLevelIds
                            ) {

                                $qq->where(
                                    'type',
                                    'level'
                                )
                                    ->whereIn(
                                        'assessmentable_id',
                                        $activeLevelIds
                                    );
                            }
                        );
                    })

                    ->count(),

                'last_activity_date' =>
                $current?->updated_at,

                'cta' => [
                    'type' => 'resume',
                    'topic_id' =>
                    $current?->topic_id
                ]
            ],

            'levels' => $levelCards,

            'current_topic_contents' => $contents,

            'stats' => [

                'total_levels' =>
                $levels->count(),

                'completed_levels' =>
                count($completedLevelIds),

                'remaining_levels' =>
                $levels->count()
                    - count($completedLevelIds),

                'total_topics' =>
                $totalLessons,

                'completed_topics' =>
                $completedLessons,

                'avg_topic_score' =>
                round(
                    $avgTopicScore ?? 0,
                    2
                ),

                'avg_exam_score' =>
                round(
                    $avgExamScore ?? 0,
                    2
                ),

                'overall_avg_score' =>
                round(
                    $overallAvgScore ?? 0,
                    2
                ),

                'modules_progress' =>
                $moduleStats,

                'chapters_progress' =>
                $chapterStats,

                'current_topic_progress' =>
                $currentTopicProgress,

                'certificates_earned' =>
                Certification::where(
                    'user_id',
                    $userId
                )
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->count()
            ],

            'last_certificate' =>
            $certificate,

            'next_action' =>
            $nextAction
        ];
    }
}
