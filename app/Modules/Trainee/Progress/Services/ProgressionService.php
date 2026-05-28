<?php

namespace App\Modules\Trainee\Progress\Services;

use App\Models\Chapter;
use App\Models\Level;
use App\Models\Module;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserProgress;
use App\Services\AuditService;
use App\Services\CertificationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class ProgressionService
{
    /*
    |--------------------------------------------------------------------------
    | TOPIC COMPLETION
    |--------------------------------------------------------------------------
    */

    public function handleTopicCompletion(
        $userId,
        Topic $topic
    ) {

        DB::transaction(function () use (
            $userId,
            $topic
        ) {

            $user = User::find($userId);

            /*
            |--------------------------------------------------------------------------
            | COMPLETE TOPIC
            |--------------------------------------------------------------------------
            */

            UserProgress::updateOrCreate(

                [
                    'user_id' => $userId,
                    'topic_id' => $topic->id,
                ],

                [
                    'program_id' => $topic->program_id,
                    'level_id' => $topic->level_id,
                    'module_id' => $topic->module_id,
                    'chapter_id' => $topic->chapter_id,

                    'is_unlocked' => true,
                    'is_completed' => true,

                    'completed_at' => now(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            AuditService::log(
                'lesson_completed',
                'User completed topic',
                [
                    'topic_id' => $topic->id,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | USER NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if ($user) {

                app(NotificationService::class)->send(
                    $user,
                    'LESSON_COMPLETED',
                    [
                        'title' => 'Topic Completed',

                        'message' => 'You completed a topic successfully',

                        'screen' => 'TopicDetails',

                        'id' => $topic->id,

                        'meta' => [
                            'topic_id' => $topic->id,
                            'topic_title' => $topic->title,
                        ],
                    ],
                    ['db', 'push']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | NEXT TOPIC
            |--------------------------------------------------------------------------
            */
            $nextTopic = Topic::where(
                'chapter_id',
                $topic->chapter_id
            )
                ->where('id', '>', $topic->id)
                ->orderBy('id')
                ->first();
            /*
            |--------------------------------------------------------------------------
            | UNLOCK NEXT TOPIC
            |--------------------------------------------------------------------------
            */

            if ($nextTopic) {

                UserProgress::firstOrCreate(

                    [
                        'user_id' => $userId,
                        'topic_id' => $nextTopic->id,
                    ],

                    [
                        'program_id' => $nextTopic->program_id,
                        'level_id' => $nextTopic->level_id,
                        'module_id' => $nextTopic->module_id,
                        'chapter_id' => $nextTopic->chapter_id,

                        'is_unlocked' => true,
                        'is_completed' => false,
                    ]
                );

                AuditService::log(
                    'lesson_unlocked',
                    'Next topic unlocked',
                    [
                        'topic_id' => $nextTopic->id,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | USER NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if ($user) {

                    app(NotificationService::class)->send(
                        $user,
                        'LESSON_UNLOCKED',
                        [
                            'title' => 'New Topic Unlocked',

                            'message' => 'Next topic unlocked successfully',

                            'screen' => 'TopicDetails',

                            'id' => $nextTopic->id,

                            'meta' => [
                                'topic_id' => $nextTopic->id,
                                'topic_title' => $nextTopic->title,
                            ],
                        ],
                        ['db', 'push']
                    );
                }

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | LAST TOPIC OF CHAPTER
            |--------------------------------------------------------------------------
            */

            $this->handleChapterCompletion(
                $userId,
                $topic->chapter_id
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CHAPTER COMPLETION
    |--------------------------------------------------------------------------
    */

    private function handleChapterCompletion(
        $userId,
        $chapterId
    ) {

        $chapter = Chapter::find($chapterId);

        if (! $chapter) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL TOPICS
        |--------------------------------------------------------------------------
        */

        $totalTopics = Topic::where(
            'chapter_id',
            $chapterId
        )->count();

        /*
        |--------------------------------------------------------------------------
        | COMPLETED TOPICS
        |--------------------------------------------------------------------------
        */

        $completedTopics = UserProgress::where(
            'user_id',
            $userId
        )
            ->where('chapter_id', $chapterId)
            ->whereNotNull('topic_id')
            ->where('is_completed', true)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | CHAPTER NOT COMPLETED
        |--------------------------------------------------------------------------
        */

        if (
            $totalTopics <= 0 ||
            $totalTopics != $completedTopics
        ) {
            return;
        }

        $user = User::find($userId);

        /*
        |--------------------------------------------------------------------------
        | AUDIT
        |--------------------------------------------------------------------------
        */

        AuditService::log(
            'chapter_completed',
            'User completed chapter',
            [
                'chapter_id' => $chapter->id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | USER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        if ($user) {

            app(NotificationService::class)->send(
                $user,
                'CHAPTER_COMPLETED',
                [
                    'title' => 'Chapter Completed',

                    'message' => 'You completed a chapter successfully',

                    'screen' => 'ChapterDetails',

                    'id' => $chapter->id,

                    'meta' => [
                        'chapter_id' => $chapter->id,
                        'chapter_title' => $chapter->title,
                    ],
                ],
                ['db', 'push']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NEXT CHAPTER
        |--------------------------------------------------------------------------
        */
        $nextChapter = Chapter::where(
            'module_id',
            $chapter->module_id
        )
            ->where('id', '>', $chapter->id)
            ->orderBy('id')
            ->first();
        /*
        |--------------------------------------------------------------------------
        | UNLOCK NEXT CHAPTER
        |--------------------------------------------------------------------------
        */

        if ($nextChapter) {

            $firstTopic = Topic::where(
                'chapter_id',
                $nextChapter->id
            )
                ->orderBy('id')
                ->first();

            if ($firstTopic) {

                UserProgress::firstOrCreate(

                    [
                        'user_id' => $userId,
                        'topic_id' => $firstTopic->id,
                    ],

                    [
                        'program_id' => $firstTopic->program_id,
                        'level_id' => $firstTopic->level_id,
                        'module_id' => $firstTopic->module_id,
                        'chapter_id' => $firstTopic->chapter_id,

                        'is_unlocked' => true,
                        'is_completed' => false,
                    ]
                );

                AuditService::log(
                    'chapter_unlocked',
                    'Next chapter unlocked',
                    [
                        'chapter_id' => $nextChapter->id,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | USER NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if ($user) {

                    app(NotificationService::class)->send(
                        $user,
                        'CHAPTER_UNLOCKED',
                        [
                            'title' => 'New Chapter Unlocked',

                            'message' => 'Next chapter unlocked successfully',

                            'screen' => 'ChapterDetails',

                            'id' => $nextChapter->id,

                            'meta' => [
                                'chapter_id' => $nextChapter->id,
                                'chapter_title' => $nextChapter->title,
                            ],
                        ],
                        ['db', 'push']
                    );
                }
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE ASSESSMENT CONFIG
        |--------------------------------------------------------------------------
        */

        $moduleAssessmentRequired = config(
            'assessment.progression.assessment_required_for.module.enabled',
            false
        );

        /*
        |--------------------------------------------------------------------------
        | MODULE EXAM REQUIRED
        |--------------------------------------------------------------------------
        */

        if ($moduleAssessmentRequired) {

            $this->unlockModuleExam(
                $userId,
                $chapter->module_id
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | AUTO COMPLETE MODULE
        |--------------------------------------------------------------------------
        */

        $this->completeModuleWithoutAssessment(
            $userId,
            $chapter->module_id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MODULE EXAM UNLOCK
    |--------------------------------------------------------------------------
    */

    private function unlockModuleExam(
        $userId,
        $moduleId
    ) {
        $module = Module::find($moduleId);

        if (! $module) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ALL MODULE TOPICS
        |--------------------------------------------------------------------------
        */

        $totalTopics = Topic::where(
            'module_id',
            $moduleId
        )->count();

        $completedTopics = UserProgress::where(
            'user_id',
            $userId
        )
            ->where('module_id', $moduleId)
            ->whereNotNull('topic_id')
            ->where('is_completed', true)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | NOT ELIGIBLE
        |--------------------------------------------------------------------------
        */

        if (
            $totalTopics <= 0 ||
            $totalTopics != $completedTopics
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | EXAM ENTRY
        |--------------------------------------------------------------------------
        */

        UserProgress::updateOrCreate(

            [
                'user_id' => $userId,
                'level_id' => $module->level_id,
                'module_id' => $moduleId,
                'chapter_id' => null,
                'topic_id' => null,
            ],

            [
                'program_id' => $module->program_id,

                'is_unlocked' => true,
                'is_completed' => false,
            ]
        );

        AuditService::log(
            'module_exam_unlocked',
            'Module exam unlocked',
            [
                'module_id' => $module->id,
            ]
        );
    }

    private function completeModuleWithoutAssessment(
        $userId,
        $moduleId
    ) {

        $module = Module::find($moduleId);

        if (! $module) {
            return;
        }

        $this->completeModule(
            $userId,
            $module
        );

        $this->unlockNextModule(
            $userId,
            $module
        );

        $this->checkLevelCompletion(
            $userId,
            $module->level_id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ASSESSMENT PASS
    |--------------------------------------------------------------------------
    */

    public function handleAssessmentPass(
        $userId,
        $assessment,
        $attempt
    ) {

        $certificate = null;

        /*
        |--------------------------------------------------------------------------
        | ACTIVE EXAM TYPES
        |--------------------------------------------------------------------------
        */

        $examTypes = config(
            'assessment.exam.types',
            []
        );

        /*
        |--------------------------------------------------------------------------
        | NOT EXAM TYPE
        |--------------------------------------------------------------------------
        */

        if (
            ! in_array(
                $assessment->type,
                $examTypes,
                true
            )
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | CHAPTER EXAM
        |--------------------------------------------------------------------------
        */

        if ($assessment->type === 'chapter') {

            $chapter = Chapter::find(
                $assessment->assessmentable_id
            );

            if (! $chapter) {
                return null;
            }

            DB::transaction(function () use (
                $userId,
                $chapter,
                $attempt,
                &$certificate
            ) {

                /*
                |--------------------------------------------------------------------------
                | COMPLETE CHAPTER
                |--------------------------------------------------------------------------
                */

                UserProgress::updateOrCreate(

                    [
                        'user_id' => $userId,
                        'chapter_id' => $chapter->id,
                        'topic_id' => null,
                    ],

                    [
                        'program_id' => $chapter->program_id,
                        'level_id' => $chapter->level_id,
                        'module_id' => $chapter->module_id,

                        'is_unlocked' => true,
                        'is_completed' => true,

                        'completed_at' => now(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | CERTIFICATE
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        'chapter',
                        config(
                            'assessment.certification.enabled_for_assessment_types',
                            []
                        ),
                        true
                    )
                ) {

                    $certificate = app(
                        CertificationService::class
                    )->generate(

                        auth()->user(),

                        $chapter,

                        $attempt,

                        'chapter'
                    );
                }

                AuditService::log(
                    'chapter_exam_completed',
                    'User completed chapter exam',
                    [
                        'chapter_id' => $chapter->id,
                    ]
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE EXAM
        |--------------------------------------------------------------------------
        */ elseif ($assessment->type === 'module') {

            $module = Module::find(
                $assessment->assessmentable_id
            );

            if (! $module) {
                return null;
            }

            DB::transaction(function () use (
                $userId,
                $module,
                $attempt,
                &$certificate
            ) {

                /*
            |--------------------------------------------------------------------------
            | COMPLETE MODULE
            |--------------------------------------------------------------------------
            */

                $this->completeModule(
                    $userId,
                    $module
                );

                /*
                |--------------------------------------------------------------------------
                | CERTIFICATE
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        'module',
                        config(
                            'assessment.certification.enabled_for_assessment_types',
                            []
                        ),
                        true
                    )
                ) {

                    $certificate = app(
                        CertificationService::class
                    )->generate(

                        auth()->user(),

                        $module,

                        $attempt,

                        'module'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | NEXT MODULE
                |--------------------------------------------------------------------------
                */

                $this->unlockNextModule(
                    $userId,
                    $module
                );

                /*
                |--------------------------------------------------------------------------
                | LEVEL CHECK
                |--------------------------------------------------------------------------
                */

                $this->checkLevelCompletion(
                    $userId,
                    $module->level_id
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | LEVEL EXAM
        |--------------------------------------------------------------------------
        */ elseif ($assessment->type === 'level') {

            $level = Level::find(
                $assessment->assessmentable_id
            );

            if (! $level) {
                return null;
            }

            DB::transaction(function () use (
                $userId,
                $level,
                $attempt,
                &$certificate
            ) {

                /*
                |--------------------------------------------------------------------------
                | COMPLETE LEVEL
                |--------------------------------------------------------------------------
                */

                UserProgress::updateOrCreate(

                    [
                        'user_id' => $userId,
                        'level_id' => $level->id,
                        'module_id' => null,
                        'topic_id' => null,
                    ],

                    [
                        'program_id' => $level->program_id,

                        'is_unlocked' => true,
                        'is_completed' => true,

                        'completed_at' => now(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | CERTIFICATE
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        'level',
                        config(
                            'assessment.certification.enabled_for_assessment_types',
                            []
                        ),
                        true
                    )
                ) {

                    $certificate = app(
                        CertificationService::class
                    )->generate(

                        auth()->user(),

                        $level,

                        $attempt,

                        'level'
                    );
                }

                AuditService::log(
                    'level_exam_completed',
                    'User completed level exam',
                    [
                        'level_id' => $level->id,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | NEXT LEVEL
                |--------------------------------------------------------------------------
                */

                $this->unlockNextLevel(
                    $userId,
                    $level->id
                );
            });
        }

        return $certificate;
    }
    /*
    |--------------------------------------------------------------------------
    | COMPLETE MODULE
    |--------------------------------------------------------------------------
    */

    private function completeModule(
        $userId,
        Module $module
    ) {
        $user = User::find($userId);

        /*
        |--------------------------------------------------------------------------
        | COMPLETE MODULE ENTRY
        |--------------------------------------------------------------------------
        */

        UserProgress::updateOrCreate(

            [
                'user_id' => $userId,
                'level_id' => $module->level_id,
                'module_id' => $module->id,
                'chapter_id' => null,
                'topic_id' => null,
            ],

            [
                'program_id' => $module->program_id,

                'is_unlocked' => true,
                'is_completed' => true,

                'completed_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | AUDIT
        |--------------------------------------------------------------------------
        */

        AuditService::log(
            'module_completed',
            'User completed module exam',
            [
                'module_id' => $module->id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | USER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        if ($user) {

            app(NotificationService::class)->send(
                $user,
                'MODULE_COMPLETED',
                [
                    'title' => 'Module Completed',

                    'message' => 'You completed module assessment successfully',

                    'screen' => 'ModuleDetails',

                    'id' => $module->id,

                    'meta' => [
                        'module_id' => $module->id,
                        'module_title' => $module->title,
                    ],
                ],
                ['db', 'push']
            );
        }
    }
    /*
        |--------------------------------------------------------------------------
        | NEXT MODULE
        |--------------------------------------------------------------------------
        */

    private function unlockNextModule(
        $userId,
        Module $currentModule
    ) {

        $nextModule = Module::where(
            'level_id',
            $currentModule->level_id
        )
            ->where('id', '>', $currentModule->id)
            ->orderBy('id')
            ->first();


        if (! $nextModule) {
            return;
        }

        $firstTopic = Topic::where(
            'module_id',
            $nextModule->id
        )
            ->orderBy('id')
            ->first();

        if (! $firstTopic) {
            return;
        }

        UserProgress::firstOrCreate(

            [
                'user_id' => $userId,
                'topic_id' => $firstTopic->id,
            ],

            [
                'program_id' => $firstTopic->program_id,
                'level_id' => $firstTopic->level_id,
                'module_id' => $firstTopic->module_id,
                'chapter_id' => $firstTopic->chapter_id,

                'is_unlocked' => true,
                'is_completed' => false,
            ]
        );

        AuditService::log(
            'module_unlocked',
            'User unlocked next module',
            [
                'module_id' => $nextModule->id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | USER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $user = User::find($userId);

        if ($user) {

            app(NotificationService::class)->send(
                $user,
                'MODULE_UNLOCKED',
                [
                    'title' => 'New Module Unlocked',

                    'message' => 'Next module unlocked successfully',

                    'screen' => 'ModuleDetails',

                    'id' => $nextModule->id,

                    'meta' => [
                        'module_id' => $nextModule->id,
                        'module_title' => $nextModule->title,
                    ],
                ],
                ['db', 'push']
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LEVEL COMPLETION CHECK
    |--------------------------------------------------------------------------
    */

    private function checkLevelCompletion(
        $userId,
        $levelId
    ) {

        $totalModules = Module::where(
            'level_id',
            $levelId
        )->count();

        $completedModules = UserProgress::where('user_id',  $userId)
            ->where('level_id', $levelId)
            ->whereNull('chapter_id')
            ->whereNull('topic_id')
            ->whereNotNull('module_id')
            ->where('is_completed', true)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | LEVEL NOT COMPLETED
        |--------------------------------------------------------------------------
        */

        if (
            $totalModules <= 0 ||
            $totalModules != $completedModules
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | STORE LEVEL COMPLETION
        |--------------------------------------------------------------------------
        */

        UserProgress::updateOrCreate(

            [
                'user_id' => $userId,
                'level_id' => $levelId,
                'module_id' => null,
                'topic_id' => null,
            ],

            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        AuditService::log(
            'level_completed',
            'User completed level',
            [
                'level_id' => $levelId,
            ]
        );

        $user = User::find($userId);

        $level = Level::find($levelId);

        /*
        |--------------------------------------------------------------------------
        | USER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        if ($user && $level) {

            app(NotificationService::class)->send(
                $user,
                'LEVEL_COMPLETED',
                [
                    'title' => 'Level Completed',

                    'message' => 'You completed a level successfully',

                    'screen' => 'LevelDetails',

                    'id' => $level->id,

                    'meta' => [
                        'level_id' => $level->id,
                        'level_title' => $level->title,
                    ],
                ],
                ['db', 'push']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NEXT LEVEL
        |--------------------------------------------------------------------------
        */

        $this->unlockNextLevel(
            $userId,
            $levelId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NEXT LEVEL
    |--------------------------------------------------------------------------
    */

    private function unlockNextLevel(
        $userId,
        $currentLevelId
    ) {

        $currentLevel = Level::find(
            $currentLevelId
        );

        if (! $currentLevel) {
            return;
        }

        
        $nextLevel = Level::where(
            'program_id',
            $currentLevel->program_id
        )
            ->where('id', '>', $currentLevel->id)
            ->orderBy('id')
            ->first();



        if (! $nextLevel) {
            return;
        }

        $firstModule = Module::where(
            'level_id',
            $nextLevel->id
        )
            ->orderBy('id')
            ->first();

        if (! $firstModule) {
            return;
        }

        $firstTopic = Topic::where(
            'module_id',
            $firstModule->id
        )
            ->orderBy('id')
            ->first();

        if (! $firstTopic) {
            return;
        }

        UserProgress::firstOrCreate(

            [
                'user_id' => $userId,
                'topic_id' => $firstTopic->id,
            ],

            [
                'program_id' => $firstTopic->program_id,
                'level_id' => $firstTopic->level_id,
                'module_id' => $firstTopic->module_id,
                'chapter_id' => $firstTopic->chapter_id,

                'is_unlocked' => true,
                'is_completed' => false,
            ]
        );

        AuditService::log(
            'level_unlocked',
            'User unlocked next level',
            [
                'level_id' => $nextLevel->id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | USER NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $user = User::find($userId);

        if ($user) {

            app(NotificationService::class)->send(
                $user,
                'LEVEL_UNLOCKED',
                [
                    'title' => 'New Level Unlocked',

                    'message' => 'Next level unlocked successfully',

                    'screen' => 'LevelDetails',

                    'id' => $nextLevel->id,

                    'meta' => [
                        'level_id' => $nextLevel->id,
                        'level_title' => $nextLevel->title,
                    ],
                ],
                ['db', 'push']
            );
        }
    }
}
