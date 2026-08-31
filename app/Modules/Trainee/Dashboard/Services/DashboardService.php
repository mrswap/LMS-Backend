<?php

namespace App\Modules\Trainee\Dashboard\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Certification;
use App\Models\Level;
use App\Models\TopicContent;
use App\Models\UserContentProgress;
use App\Models\UserProgress;
use App\Services\HierarchyVisibilityService;
use Illuminate\Support\Facades\DB;

class DashboardService {
    protected $visibilityService;

    public function __construct(
        HierarchyVisibilityService $visibilityService
    ) {
        $this->visibilityService = $visibilityService;
    }

    public function getDashboard($userId) {

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
                'topic.chapter',
            ])
            ->orderBy('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 🔹 FALLBACK
        |--------------------------------------------------------------------------
        */

        if (! $current) {

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
                    'topic.chapter',
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

            'modules.chapters.topics.program',

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

                        'completed_topics' => $chapterCompletedTopics,

                        'progress_percent' => count($chapterTopicIds) > 0
                            ? round(
                                (
                                    $chapterCompletedTopics
                                    / count($chapterTopicIds)
                                ) * 100,
                                2
                            )
                            : 0,

                        'is_passed' => $chapterPassed,
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

                    'thumbnail' => $module->thumbnail,

                    'level_id' => $level->id,

                    'total_topics' => count(
                        $moduleTopicIds
                    ),

                    'completed_topics' => $moduleCompletedTopics,

                    'progress_percent' => count($moduleTopicIds) > 0
                        ? round(
                            (
                                $moduleCompletedTopics
                                / count($moduleTopicIds)
                            ) * 100,
                            2
                        )
                        : 0,

                    'is_passed' => $modulePassed,
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

                'type' => $pendingAssessment->type . '_exam',

                'assessment_id' => $pendingAssessment->id,

                'assessment_title' => $pendingAssessment->title,

                strtolower(
                    $pendingAssessment->type
                ) => [

                    'id' => $entity?->id,

                    'title' => $entity?->title,
                ],
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

                'progress_percent' => $totalContent > 0
                    ? round(
                        (
                            $readContent
                            / $totalContent
                        ) * 100,
                        2
                    )
                    : 0,
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

                    'description' => $level->description,

                    'thumbnail' => $level->thumbnail,

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

                                'module_id' => $module->id,

                                'module_title' => $module->title,

                                'thumbnail' => $module->thumbnail,

                                'is_passed' => isset(
                                    $moduleCertifications[$module->id]
                                ),

                                'total_topics' => $totalTopics,

                                'completed_topics' => $completedTopics,

                                'started_topics' => $startedTopics,

                                'progress_percent' => $progressPercent,

                                'chapters' => $module->chapters->map(
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

                                            'chapter_id' => $chapter->id,

                                            'chapter_title' => $chapter->title,

                                            'thumbnail' => $chapter->thumbnail,

                                            'is_passed' => isset(
                                                $chapterCertifications[$chapter->id]
                                            ),

                                            'total_topics' => $totalTopics,

                                            'completed_topics' => $completedTopics,

                                            'started_topics' => $startedTopics,

                                            'progress_percent' => $progressPercent,
                                        ];
                                    }
                                ),
                            ];
                        }
                    ),
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
                    'level',
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

        $assessmentStatus = $this->getAssessmentStatus(
            $userId,
            $current
        );

        /*
        |--------------------------------------------------------------------------
        | 🔹 FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'current_learning' => [

                'program' => [
                    'id' => $current?->topic?->program?->id,
                    'title' => $current?->topic?->program?->title,
                    'thumbnail' => $current?->topic?->program?->thumbnail,
                ],

                'level' => [
                    'id' => $current?->topic?->level?->id,
                    'title' => $current?->topic?->level?->title,
                    'thumbnail' => $current?->topic?->level?->thumbnail,
                ],

                'module' => [
                    'id' => $current?->topic?->module?->id,
                    'title' => $current?->topic?->module?->title,
                    'thumbnail' => $current?->topic?->module?->thumbnail,
                ],

                'chapter' => [
                    'id' => $current?->topic?->chapter?->id,
                    'title' => $current?->topic?->chapter?->title,
                    'thumbnail' => $current?->topic?->chapter?->thumbnail,
                ],

                'topic' => [
                    'id' => $current?->topic?->id,
                    'title' => $current?->topic?->title,
                    'thumbnail' => $current?->topic?->thumbnail,
                ],

                'last_completed_topic' => [
                    'id' => $lastCompletedAttempt?->assessment?->assessmentable?->id,
                    'title' => $lastCompletedAttempt?->assessment?->assessmentable?->title,
                    'thumbnail' => $lastCompletedAttempt?->assessment?->assessmentable?->thumbnail,
                ],

                'progress_percent' => $progressPercent,

                'completed_lessons' => $completedLessons,

                'total_lessons' => $totalLessons,

                'pending_quizzes' => Assessment::where('status', true)
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

                'last_activity_date' => $current?->updated_at,

                'cta' => [
                    'type' => 'resume',
                    'topic_id' => $current?->topic_id,
                ],
            ],

            'levels' => $levelCards,

            'current_topic_contents' => $contents,

            'stats' => [

                'total_levels' => $levels->count(),

                'completed_levels' => count($completedLevelIds),

                'remaining_levels' => $levels->count()
                    - count($completedLevelIds),

                'total_topics' => $totalLessons,

                'completed_topics' => $completedLessons,

                'avg_topic_score' => round(
                    $avgTopicScore ?? 0,
                    2
                ),

                'avg_exam_score' => round(
                    $avgExamScore ?? 0,
                    2
                ),

                'overall_avg_score' => round(
                    $overallAvgScore ?? 0,
                    2
                ),

                'modules_progress' => $moduleStats,

                'chapters_progress' => $chapterStats,

                'current_topic_progress' => $currentTopicProgress,

                'certificates_earned' => Certification::where(
                    'user_id',
                    $userId
                )
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->count(),
            ],

            'last_certificate' => $certificate,

            'next_action' => $nextAction,

            'assessment_status' => $assessmentStatus,
        ];
    }

    private function getAssessmentStatus(
        int $userId,
        ?UserProgress $current
    ) {

        if (!$current || !$current->topic) {
            return null;
        }

        /*
            |--------------------------------------------------------------------------
            | TOPIC
            |--------------------------------------------------------------------------
            */

        $topic = $this->getCurrentTopicAssessment(
            $userId,
            $current
        );

        if ($topic['status'] != 'passed') {
            return $topic;
        }

        /*
            |--------------------------------------------------------------------------
            | MODULE
            |--------------------------------------------------------------------------
            */

        $module = $this->getCurrentModuleAssessment(
            $userId,
            $current
        );

        if ($module['status'] != 'passed') {
            return $module;
        }

        /*
            |--------------------------------------------------------------------------
            | CHAPTER
            |--------------------------------------------------------------------------
            */

        $chapter = $this->getCurrentChapterAssessment(
            $userId,
            $current
        );

        if ($chapter['status'] != 'passed') {
            return $chapter;
        }

        /*
            |--------------------------------------------------------------------------
            | LEVEL
            |--------------------------------------------------------------------------
            */

        return $this->getCurrentLevelAssessment(
            $userId,
            $current
        );
    }

    private function getCurrentTopicAssessment(
        int $userId,
        UserProgress $current
    ) {

        $topic = $current->topic;

        /*
            |--------------------------------------------------------------------------
            | Assessment
            |--------------------------------------------------------------------------
            */

        $assessment = Assessment::where('type', 'topic')
            ->where('assessmentable_id', $topic->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$assessment) {

            return [
                'type' => 'topic',
                'status' => 'not_found',
                'assessment_id' => null,
                'title' => null,
                'reason' => 'Topic assessment not found.',
            ];
        }

        /*
            |--------------------------------------------------------------------------
            | Contents
            |--------------------------------------------------------------------------
            */

        $contentIds = TopicContent::where(
            'topic_id',
            $topic->id
        )
            ->where('status', true)
            ->whereNull('deleted_at')
            ->where('publish_status', 'published')
            ->pluck('id');

        $totalContents = $contentIds->count();

        $readContents = UserContentProgress::where(
            'user_id',
            $userId
        )
            ->whereIn(
                'topic_content_id',
                $contentIds
            )
            ->where('is_read', true)
            ->count();

        /*
            |--------------------------------------------------------------------------
            | Unlock
            |--------------------------------------------------------------------------
            */

        $isUnlocked =
            $totalContents > 0
            &&
            $readContents >= $totalContents;

        /*
            |--------------------------------------------------------------------------
            | Last Attempt
            |--------------------------------------------------------------------------
            */

        $lastAttempt = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where(
                'assessment_id',
                $assessment->id
            )
            ->latest('submitted_at')
            ->first();

        /*
            |--------------------------------------------------------------------------
            | LOCKED
            |--------------------------------------------------------------------------
            */

        if (!$isUnlocked) {

            return [

                'type' => 'topic',

                'status' => 'locked',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Complete all topic lessons first.',

                'progress' => [

                    'completed' => $readContents,

                    'total' => $totalContents,

                    'remaining' => max(
                        0,
                        $totalContents - $readContents
                    ),

                ],

                'last_attempt' => null,

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | READY
            |--------------------------------------------------------------------------
            */

        if (!$lastAttempt) {

            return [

                'type' => 'topic',

                'status' => 'ready',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Ready for quiz.',

                'progress' => [

                    'completed' => $readContents,

                    'total' => $totalContents,

                    'remaining' => 0,

                ],

                'last_attempt' => null,

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | FAILED
            |--------------------------------------------------------------------------
            */

        if ($lastAttempt->status == 'failed') {

            return [

                'type' => 'topic',

                'status' => 'failed',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Retry the quiz.',

                'progress' => [

                    'completed' => $readContents,

                    'total' => $totalContents,

                    'remaining' => 0,

                ],

                'last_attempt' => [

                    'id' => $lastAttempt->id,

                    'status' => $lastAttempt->status,

                    'score' => $lastAttempt->score,
                    'total_score'   =>  $assessment->total_marks,

                    'percentage' => $lastAttempt->percentage,

                    'submitted_at' => $lastAttempt->submitted_at,

                    'time_taken' => $lastAttempt->time_taken,

                ],

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | PASSED
            |--------------------------------------------------------------------------
            */

        return [

            'type' => 'topic',

            'status' => 'passed',

            'assessment_id' => $assessment->id,

            'title' => $assessment->title,

            'reason' => 'Topic quiz completed.',

            'progress' => [

                'completed' => $readContents,

                'total' => $totalContents,

                'remaining' => 0,

            ],

            'last_attempt' => [

                'id' => $lastAttempt->id,

                'status' => $lastAttempt->status,

                'score' => $lastAttempt->score,

                'percentage' => $lastAttempt->percentage,

                'submitted_at' => $lastAttempt->submitted_at,

                'time_taken' => $lastAttempt->time_taken,

            ],

        ];
    }

    private function getCurrentModuleAssessment(
        int $userId,
        UserProgress $current
    ) {

        $module = $current->topic->module;

        /*
            |--------------------------------------------------------------------------
            | Module Assessment
            |--------------------------------------------------------------------------
            */

        $assessment = Assessment::where('type', 'module')
            ->where('assessmentable_id', $module->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$assessment) {

            return [
                'type' => 'module',
                'status' => 'not_found',
                'assessment_id' => null,
                'title' => null,
                'reason' => 'Module assessment not found.',
            ];
        }

        /*
            |--------------------------------------------------------------------------
            | All Active Topics Of Module
            |--------------------------------------------------------------------------
            */

        $topicIds = $module->chapters()
            ->with([
                'topics' => function ($q) {

                    $q->where('status', true)
                        ->whereNull('deleted_at');
                }
            ])
            ->get()
            ->flatMap->topics
            ->pluck('id')
            ->values();

        $totalTopics = $topicIds->count();

        /*
            |--------------------------------------------------------------------------
            | Passed Topic Assessments
            |--------------------------------------------------------------------------
            */

        $passedTopics = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where('status', 'passed')

            ->whereHas(
                'assessment',
                function ($q) use ($topicIds) {

                    $q->where('type', 'topic')
                        ->whereIn(
                            'assessmentable_id',
                            $topicIds
                        );
                }
            )

            ->with('assessment:id,assessmentable_id')

            ->get()

            ->pluck('assessment.assessmentable_id')

            ->unique()

            ->count();

        /*
            |--------------------------------------------------------------------------
            | Unlock
            |--------------------------------------------------------------------------
            */

        $isUnlocked =
            $totalTopics > 0
            &&
            $passedTopics >= $totalTopics;

        /*
            |--------------------------------------------------------------------------
            | Last Module Attempt
            |--------------------------------------------------------------------------
            */

        $lastAttempt = AssessmentAttempt::where(
            'user_id',
            $userId
        )

            ->where(
                'assessment_id',
                $assessment->id
            )

            ->latest('submitted_at')

            ->first();

        /*
            |--------------------------------------------------------------------------
            | LOCKED
            |--------------------------------------------------------------------------
            */

        if (!$isUnlocked) {

            return [

                'type' => 'module',

                'status' => 'locked',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Pass all topic quizzes to unlock module exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => max(
                        0,
                        $totalTopics - $passedTopics
                    ),

                ],

                'last_attempt' => null,

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | READY
            |--------------------------------------------------------------------------
            */

        if (!$lastAttempt) {

            return [

                'type' => 'module',

                'status' => 'ready',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Ready for module exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => 0,

                ],

                'last_attempt' => null,

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | FAILED
            |--------------------------------------------------------------------------
            */

        if ($lastAttempt->status == 'failed') {

            return [

                'type' => 'module',

                'status' => 'failed',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Retry module exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => 0,

                ],

                'last_attempt' => [

                    'id' => $lastAttempt->id,

                    'status' => $lastAttempt->status,

                    'score' => $lastAttempt->score,

                    'percentage' => $lastAttempt->percentage,

                    'submitted_at' => $lastAttempt->submitted_at,

                    'time_taken' => $lastAttempt->time_taken,

                ],

            ];
        }

        /*
            |--------------------------------------------------------------------------
            | PASSED
            |--------------------------------------------------------------------------
            */

        return [

            'type' => 'module',

            'status' => 'passed',

            'assessment_id' => $assessment->id,

            'title' => $assessment->title,

            'reason' => 'Module exam completed.',

            'progress' => [

                'completed' => $passedTopics,

                'total' => $totalTopics,

                'remaining' => 0,

            ],

            'last_attempt' => [

                'id' => $lastAttempt->id,

                'status' => $lastAttempt->status,

                'score' => $lastAttempt->score,

                'percentage' => $lastAttempt->percentage,

                'submitted_at' => $lastAttempt->submitted_at,

                'time_taken' => $lastAttempt->time_taken,

            ],

        ];
    }

    private function getCurrentChapterAssessment(
        int $userId,
        UserProgress $current
    ) {

        $chapter = $current->topic->chapter;

        /*
                |--------------------------------------------------------------------------
                | Chapter Assessment
                |--------------------------------------------------------------------------
                */

        $assessment = Assessment::where('type', 'chapter')
            ->where('assessmentable_id', $chapter->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $assessment) {

            return [
                'type' => 'chapter',
                'status' => 'not_found',
                'assessment_id' => null,
                'title' => null,
                'reason' => 'Chapter assessment not found.',
            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Active Topics
                |--------------------------------------------------------------------------
                */

        $topicIds = $chapter->topics()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        $totalTopics = $topicIds->count();

        /*
                |--------------------------------------------------------------------------
                | Passed Topic Quizzes
                |--------------------------------------------------------------------------
                */

        $passedTopics = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) use ($topicIds) {

                $q->where('type', 'topic')
                    ->whereIn(
                        'assessmentable_id',
                        $topicIds
                    );
            })
            ->with('assessment:id,assessmentable_id')
            ->get()
            ->pluck('assessment.assessmentable_id')
            ->unique()
            ->count();

        $isUnlocked =
            $totalTopics > 0
            &&
            $passedTopics == $totalTopics;

        /*
                |--------------------------------------------------------------------------
                | Last Attempt
                |--------------------------------------------------------------------------
                */

        $lastAttempt = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where(
                'assessment_id',
                $assessment->id
            )
            ->latest('submitted_at')
            ->first();

        /*
                |--------------------------------------------------------------------------
                | Locked
                |--------------------------------------------------------------------------
                */

        if (! $isUnlocked) {

            return [

                'type' => 'chapter',

                'status' => 'locked',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Pass all topic quizzes to unlock chapter exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => max(
                        0,
                        $totalTopics - $passedTopics
                    ),

                ],

                'last_attempt' => null,

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Ready
                |--------------------------------------------------------------------------
                */

        if (! $lastAttempt) {

            return [

                'type' => 'chapter',

                'status' => 'ready',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Ready for chapter exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => 0,

                ],

                'last_attempt' => null,

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Failed
                |--------------------------------------------------------------------------
                */

        if ($lastAttempt->status === 'failed') {

            return [

                'type' => 'chapter',

                'status' => 'failed',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Retry chapter exam.',

                'progress' => [

                    'completed' => $passedTopics,

                    'total' => $totalTopics,

                    'remaining' => 0,

                ],

                'last_attempt' => [

                    'id' => $lastAttempt->id,

                    'status' => $lastAttempt->status,

                    'score' => $lastAttempt->score,

                    'percentage' => $lastAttempt->percentage,

                    'submitted_at' => $lastAttempt->submitted_at,

                    'time_taken' => $lastAttempt->time_taken,

                ],

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Passed
                |--------------------------------------------------------------------------
                */

        return [

            'type' => 'chapter',

            'status' => 'passed',

            'assessment_id' => $assessment->id,

            'title' => $assessment->title,

            'reason' => 'Chapter exam completed.',

            'progress' => [

                'completed' => $passedTopics,

                'total' => $totalTopics,

                'remaining' => 0,

            ],

            'last_attempt' => [

                'id' => $lastAttempt->id,

                'status' => $lastAttempt->status,

                'score' => $lastAttempt->score,

                'percentage' => $lastAttempt->percentage,

                'submitted_at' => $lastAttempt->submitted_at,

                'time_taken' => $lastAttempt->time_taken,

            ],

        ];
    }

    private function getCurrentLevelAssessment(
        int $userId,
        UserProgress $current
    ) {

        $level = $current->topic->level;

        /*
                |--------------------------------------------------------------------------
                | Level Assessment
                |--------------------------------------------------------------------------
                */

        $assessment = Assessment::where('type', 'level')
            ->where('assessmentable_id', $level->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $assessment) {

            return [
                'type' => 'level',
                'status' => 'not_found',
                'assessment_id' => null,
                'title' => null,
                'reason' => 'Level assessment not found.',
            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Active Modules
                |--------------------------------------------------------------------------
                */

        $moduleIds = $level->modules()
            ->where('status', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        $totalModules = $moduleIds->count();

        /*
                |--------------------------------------------------------------------------
                | Passed Module Exams
                |--------------------------------------------------------------------------
                */

        $passedModules = AssessmentAttempt::where('user_id', $userId)
            ->where('status', 'passed')
            ->whereHas('assessment', function ($q) use ($moduleIds) {

                $q->where('type', 'module')
                    ->whereIn(
                        'assessmentable_id',
                        $moduleIds
                    );
            })
            ->with('assessment:id,assessmentable_id')
            ->get()
            ->pluck('assessment.assessmentable_id')
            ->unique()
            ->count();

        $isUnlocked =
            $totalModules > 0
            &&
            $passedModules == $totalModules;

        /*
                |--------------------------------------------------------------------------
                | Last Attempt
                |--------------------------------------------------------------------------
                */

        $lastAttempt = AssessmentAttempt::where(
            'user_id',
            $userId
        )
            ->where(
                'assessment_id',
                $assessment->id
            )
            ->latest('submitted_at')
            ->first();

        /*
                |--------------------------------------------------------------------------
                | Locked
                |--------------------------------------------------------------------------
                */

        if (! $isUnlocked) {

            return [

                'type' => 'level',

                'status' => 'locked',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Pass all module exams to unlock level exam.',

                'progress' => [

                    'completed' => $passedModules,

                    'total' => $totalModules,

                    'remaining' => max(
                        0,
                        $totalModules - $passedModules
                    ),

                ],

                'last_attempt' => null,

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Ready
                |--------------------------------------------------------------------------
                */

        if (! $lastAttempt) {

            return [

                'type' => 'level',

                'status' => 'ready',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Ready for level exam.',

                'progress' => [

                    'completed' => $passedModules,

                    'total' => $totalModules,

                    'remaining' => 0,

                ],

                'last_attempt' => null,

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Failed
                |--------------------------------------------------------------------------
                */

        if ($lastAttempt->status === 'failed') {

            return [

                'type' => 'level',

                'status' => 'failed',

                'assessment_id' => $assessment->id,

                'title' => $assessment->title,

                'reason' => 'Retry level exam.',

                'progress' => [

                    'completed' => $passedModules,

                    'total' => $totalModules,

                    'remaining' => 0,

                ],

                'last_attempt' => [

                    'id' => $lastAttempt->id,

                    'status' => $lastAttempt->status,

                    'score' => $lastAttempt->score,

                    'percentage' => $lastAttempt->percentage,

                    'submitted_at' => $lastAttempt->submitted_at,

                    'time_taken' => $lastAttempt->time_taken,

                ],

            ];
        }

        /*
                |--------------------------------------------------------------------------
                | Passed
                |--------------------------------------------------------------------------
                */

        return [

            'type' => 'level',

            'status' => 'passed',

            'assessment_id' => $assessment->id,

            'title' => $assessment->title,

            'reason' => 'Level exam completed.',

            'progress' => [

                'completed' => $passedModules,

                'total' => $totalModules,

                'remaining' => 0,

            ],

            'last_attempt' => [

                'id' => $lastAttempt->id,

                'status' => $lastAttempt->status,

                'score' => $lastAttempt->score,

                'percentage' => $lastAttempt->percentage,

                'submitted_at' => $lastAttempt->submitted_at,

                'time_taken' => $lastAttempt->time_taken,

            ],

        ];
    }
}
