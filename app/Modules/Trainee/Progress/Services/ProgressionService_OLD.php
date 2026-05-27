<?php

namespace App\Modules\Trainee\Progress\Services;

use App\Models\UserProgress;
use App\Models\Topic;
use App\Models\Chapter;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;
use App\Models\User;
use App\Services\NotificationService;



class ProgressionService
{
    public function handleTopicCompletion($userId, Topic $topic)
    {
        DB::transaction(function () use ($userId, $topic) {

            /*
        |--------------------------------------------------
        | 👤 USER
        |--------------------------------------------------
        */
            $user = User::find($userId);

            /*
        |--------------------------------------------------
        | ✅ 1. COMPLETE CURRENT TOPIC (SAFE)
        |--------------------------------------------------
        */
            $progress = UserProgress::updateOrCreate(
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
        |--------------------------------------------------
        | 🔔 USER NOTIFICATION
        |--------------------------------------------------
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
                            'topic_title' => $topic->title ?? null,
                        ]
                    ]
                );

                /*
            |--------------------------------------------------
            | 🛡 ADMIN NOTIFICATION
            |--------------------------------------------------
            */
                $adminPayload = [
                    'title' => 'Topic Completed',
                    'message' => "{$user->name} completed a topic",

                    'screen' => 'TopicDetails',
                    'id' => $topic->id,

                    'meta' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'topic_id' => $topic->id,
                        'topic_title' => $topic->title ?? null,
                    ]
                ];

                app(NotificationService::class)->sendToRole(
                    'admin',
                    'LESSON_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );

                app(NotificationService::class)->sendToRole(
                    'superadmin',
                    'LESSON_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );
            }

            AuditService::log(
                'lesson_completed',
                'User completed topic',
                ['topic_id' => $topic->id]
            );

            /*
        |--------------------------------------------------
        | ✅ 2. UNLOCK NEXT TOPIC (STRICT)
        |--------------------------------------------------
        */
            $nextTopic = Topic::where('chapter_id', $topic->chapter_id)
                ->where('id', '>', $topic->id)
                ->orderBy('id')
                ->first();

            if ($nextTopic) {

                $exists = UserProgress::where('user_id', $userId)
                    ->where('topic_id', $nextTopic->id)
                    ->exists();

                if (!$exists) {

                    UserProgress::create([
                        'user_id' => $userId,
                        'program_id' => $nextTopic->program_id,
                        'level_id' => $nextTopic->level_id,
                        'module_id' => $nextTopic->module_id,
                        'chapter_id' => $nextTopic->chapter_id,
                        'topic_id' => $nextTopic->id,
                        'is_unlocked' => true,
                        'is_completed' => false,
                    ]);

                    /*
                |--------------------------------------------------
                | 🔔 NEXT TOPIC UNLOCKED
                |--------------------------------------------------
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
                                    'topic_title' => $nextTopic->title ?? null,
                                ]
                            ],
                            ['db', 'push']
                        );
                    }

                    AuditService::log(
                        'lesson_unlocked',
                        'User unlocked next topic',
                        ['topic_id' => $nextTopic->id]
                    );
                }
            } else {

                /*
            |--------------------------------------------------
            | ✅ 3. NO NEXT TOPIC → CHECK CHAPTER
            |--------------------------------------------------
            */
                $this->handleChapterCompletion(
                    $userId,
                    $topic->chapter_id
                );
            }
        });
    }

    private function handleChapterCompletion($userId, $chapterId)
    {
        $total = Topic::where('chapter_id', $chapterId)->count();

        $completed = UserProgress::where('user_id', $userId)
            ->where('chapter_id', $chapterId)
            ->whereNotNull('topic_id') // 🔥 IMPORTANT
            ->where('is_completed', true)
            ->count();

        if ($total > 0 && $total == $completed) {

            /*
        |--------------------------------------------------
        | 👤 USER + CHAPTER
        |--------------------------------------------------
        */
            $user = User::find($userId);

            $chapter = Chapter::find($chapterId);

            if (!$chapter) {
                return;
            }

            /*
        |--------------------------------------------------
        | 🔔 CHAPTER COMPLETED
        |--------------------------------------------------
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
                            'chapter_title' => $chapter->title ?? null,
                        ]
                    ],
                    ['db', 'push']
                );

                /*
            |--------------------------------------------------
            | 🛡 ADMINS
            |--------------------------------------------------
            */
                $adminPayload = [
                    'title' => 'Chapter Completed',
                    'message' => "{$user->name} completed a chapter",

                    'screen' => 'ChapterDetails',
                    'id' => $chapter->id,

                    'meta' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'chapter_id' => $chapter->id,
                        'chapter_title' => $chapter->title ?? null,
                    ]
                ];

                app(NotificationService::class)->sendToRole(
                    'admin',
                    'CHAPTER_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );

                app(NotificationService::class)->sendToRole(
                    'superadmin',
                    'CHAPTER_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );
            }

            /*
        |--------------------------------------------------
        | 🔓 UNLOCK NEXT CHAPTER
        |--------------------------------------------------
        */
            $nextChapter = Chapter::where('module_id', $chapter->module_id)
                ->where('id', '>', $chapter->id)
                ->orderBy('id')
                ->first();

            if ($nextChapter) {

                $firstTopic = Topic::where('chapter_id', $nextChapter->id)
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
                        ]
                    );

                    /*
                |--------------------------------------------------
                | 🔔 NEXT CHAPTER UNLOCKED
                |--------------------------------------------------
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
                                    'chapter_title' => $nextChapter->title ?? null,
                                ]
                            ],
                            ['db', 'push']
                        );
                    }
                }

                AuditService::log(
                    'chapter_unlocked',
                    'User unlocked the next chapter',
                    [
                        'chapter_id' => $nextChapter->id ?? null
                    ]
                );
            }

            /*
        |--------------------------------------------------
        | ✅ CHECK MODULE COMPLETION
        |--------------------------------------------------
        */
            $this->handleModuleCompletion(
                $userId,
                $chapter->module_id
            );
        }
    }

    private function handleModuleCompletion($userId, $moduleId)
    {
        /*
        |--------------------------------------------------
        | 🔹 GET ALL CHAPTERS
        |--------------------------------------------------
        */
        $chapters = Chapter::where('module_id', $moduleId)
            ->pluck('id');

        $totalChapters = $chapters->count();
        $completedChapters = 0;

        foreach ($chapters as $chapterId) {

            /*
            |--------------------------------------------------
            | 🔹 TOTAL TOPICS
            |--------------------------------------------------
            */
            $totalTopics = Topic::where('chapter_id', $chapterId)
                ->pluck('id');

            /*
            |--------------------------------------------------
            | 🔹 COMPLETED TOPICS (STRICT)
            |--------------------------------------------------
            */
            $completedTopicIds = UserProgress::where('user_id', $userId)
                ->whereIn('topic_id', $totalTopics)
                ->whereNotNull('topic_id') // 🔥 IMPORTANT
                ->where('is_completed', true)
                ->pluck('topic_id')
                ->unique();

            /*
            |--------------------------------------------------
            | ✅ CHAPTER COMPLETED
            |--------------------------------------------------
            */
            if (
                $totalTopics->count() > 0
                && $totalTopics->count() === $completedTopicIds->count()
            ) {
                $completedChapters++;
            }
        }

        /*
        |--------------------------------------------------
        | ✅ MODULE COMPLETED
        |--------------------------------------------------
        */
        if (
            $totalChapters > 0
            && $totalChapters === $completedChapters
        ) {

            $module = Module::find($moduleId);

            if (!$module) {
                return;
            }

            /*
            |--------------------------------------------------
            | 👤 USER
            |--------------------------------------------------
            */
            $user = User::find($userId);

            /*
            |--------------------------------------------------
            | 🔥 STORE MODULE COMPLETION
            |--------------------------------------------------
            */
            UserProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'module_id' => $moduleId,
                    'topic_id' => null // 🔥 IMPORTANT
                ],
                [
                    'program_id' => $module->program_id ?? null,
                    'level_id' => $module->level_id,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]
            );

            /*
            |--------------------------------------------------
            | 📝 AUDIT
            |--------------------------------------------------
            */
            AuditService::log(
                'module_completed',
                'User completed module',
                [
                    'module_id' => $module->id
                ]
            );

            /*
            |--------------------------------------------------
            | 🔔 USER NOTIFICATION
            |--------------------------------------------------
            */
            if ($user) {

                app(NotificationService::class)->send(
                    $user,
                    'MODULE_COMPLETED',
                    [
                        'title' => 'Module Completed',
                        'message' => 'You completed a module successfully',

                        'screen' => 'ModuleDetails',
                        'id' => $module->id,

                        'meta' => [
                            'module_id' => $module->id,
                            'module_title' => $module->title ?? null,
                        ]
                    ],
                    ['db', 'push']
                );

                /*
                |--------------------------------------------------
                | 🛡 ADMIN PAYLOAD
                |--------------------------------------------------
                */
                $adminPayload = [
                    'title' => 'Module Completed',
                    'message' => "{$user->name} completed a module",

                    'screen' => 'ModuleDetails',
                    'id' => $module->id,

                    'meta' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,

                        'module_id' => $module->id,
                        'module_title' => $module->title ?? null,
                    ]
                ];

                /*
                |--------------------------------------------------
                | 🛡 ADMINS
                |--------------------------------------------------
                */
                app(NotificationService::class)->sendToRole(
                    'admin',
                    'MODULE_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );

                /*
                |--------------------------------------------------
                | 👑 SUPER ADMINS
                |--------------------------------------------------
                */
                app(NotificationService::class)->sendToRole(
                    'superadmin',
                    'MODULE_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );
            }

            /*
            |--------------------------------------------------
            | 🔓 UNLOCK NEXT MODULE
            |--------------------------------------------------
            */
            $nextModule = Module::where('level_id', $module->level_id)
                ->where('id', '>', $module->id)
                ->orderBy('id')
                ->first();

            if ($nextModule) {

                AuditService::log(
                    'module_unlocked',
                    'User unlocked next module',
                    [
                        'module_id' => $nextModule->id
                    ]
                );

                /*
                |--------------------------------------------------
                | 🔹 FIRST TOPIC OF NEXT MODULE
                |--------------------------------------------------
                */
                $firstTopic = Topic::where('module_id', $nextModule->id)
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
                        ]
                    );

                    /*
                    |--------------------------------------------------
                    | 🔔 NEXT MODULE UNLOCKED
                    |--------------------------------------------------
                    */
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
                                    'module_title' => $nextModule->title ?? null,
                                ]
                            ],
                            ['db', 'push']
                        );
                    }
                }

                AuditService::log(
                    'module_unlocked',
                    'User unlocked next module',
                    [
                        'module_id' => $nextModule->id ?? null
                    ]
                );
            }

            /*
            |--------------------------------------------------
            | 🔥 LEVEL CHECK TRIGGER
            |--------------------------------------------------
            */
            // $this->handleLevelCompletion(
            //     $userId,
            //     $module->level_id
            // );
        }
    }

    private function handleLevelCompletion($userId, $levelId)
    {
        /*
        |--------------------------------------------------
        | 🔹 ALL MODULES
        |--------------------------------------------------
        */
        $modules = Module::where('level_id', $levelId)
            ->pluck('id');

        $totalModules = $modules->count();
        $completedModules = 0;

        foreach ($modules as $moduleId) {

            /*
            |--------------------------------------------------
            | 🔹 CHAPTERS
            |--------------------------------------------------
            */
            $chapters = Chapter::where('module_id', $moduleId)
                ->pluck('id');

            $totalChapters = $chapters->count();
            $completedChapters = 0;

            foreach ($chapters as $chapterId) {

                /*
                |--------------------------------------------------
                | 🔹 TOTAL TOPICS
                |--------------------------------------------------
                */
                $totalTopics = Topic::where('chapter_id', $chapterId)
                    ->pluck('id');

                /*
                |--------------------------------------------------
                | 🔹 COMPLETED TOPICS (STRICT)
                |--------------------------------------------------
                */
                $completedTopicIds = UserProgress::where('user_id', $userId)
                    ->whereIn('topic_id', $totalTopics)
                    ->whereNotNull('topic_id') // 🔥 IMPORTANT
                    ->where('is_completed', true)
                    ->pluck('topic_id')
                    ->unique();

                /*
                |--------------------------------------------------
                | ✅ CHAPTER COMPLETED
                |--------------------------------------------------
                */
                if (
                    $totalTopics->count() > 0
                    && $totalTopics->count() === $completedTopicIds->count()
                ) {
                    $completedChapters++;
                }
            }

            /*
            |--------------------------------------------------
            | ✅ MODULE COMPLETED
            |--------------------------------------------------
            */
            if (
                $totalChapters > 0
                && $totalChapters === $completedChapters
            ) {
                $completedModules++;
            }
        }

        /*
        |--------------------------------------------------
        | ✅ LEVEL COMPLETED
        |--------------------------------------------------
        */
        if (
            $totalModules > 0
            && $totalModules === $completedModules
        ) {

            $level = \App\Models\Level::find($levelId);

            if (!$level) {
                return;
            }

            /*
            |--------------------------------------------------
            | 👤 USER
            |--------------------------------------------------
            */
            $user = User::find($userId);

            /*
            |--------------------------------------------------
            | 📝 AUDIT
            |--------------------------------------------------
            */
            \App\Services\AuditService::log(
                'level_completed',
                'User completed a level',
                [
                    'level_id' => $level->id
                ]
            );

            /*
            |--------------------------------------------------
            | 🔔 USER NOTIFICATION
            |--------------------------------------------------
            */
            if ($user) {

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
                            'level_title' => $level->title ?? null,
                        ]
                    ],
                    ['db', 'push']
                );

                /*
                |--------------------------------------------------
                | 🛡 ADMIN PAYLOAD
                |--------------------------------------------------
                */
                $adminPayload = [
                    'title' => 'Level Completed',
                    'message' => "{$user->name} completed a level",

                    'screen' => 'LevelDetails',
                    'id' => $level->id,

                    'meta' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,

                        'level_id' => $level->id,
                        'level_title' => $level->title ?? null,
                    ]
                ];

                /*
                |--------------------------------------------------
                | 🛡 ADMINS
                |--------------------------------------------------
                */
                app(NotificationService::class)->sendToRole(
                    'admin',
                    'LEVEL_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );

                /*
                |--------------------------------------------------
                | 👑 SUPER ADMINS
                |--------------------------------------------------
                */
                app(NotificationService::class)->sendToRole(
                    'superadmin',
                    'LEVEL_COMPLETED',
                    $adminPayload,
                    ['db', 'push']
                );
            }

            /*
            |--------------------------------------------------
            | 🔓 UNLOCK NEXT LEVEL
            |--------------------------------------------------
            */
            $nextLevel = \App\Models\Level::where('program_id', $level->program_id)
                ->where('id', '>', $level->id)
                ->orderBy('id')
                ->first();

            if ($nextLevel) {

                /*
                |--------------------------------------------------
                | 📝 AUDIT
                |--------------------------------------------------
                */
                \App\Services\AuditService::log(
                    'level_unlocked',
                    'User unlocked next level',
                    [
                        'level_id' => $nextLevel->id
                    ]
                );

                /*
                |--------------------------------------------------
                | 🔹 FIRST MODULE
                |--------------------------------------------------
                */
                $firstModule = Module::where('level_id', $nextLevel->id)
                    ->orderBy('id')
                    ->first();

                if ($firstModule) {

                    /*
                    |--------------------------------------------------
                    | 🔹 FIRST TOPIC
                    |--------------------------------------------------
                    */
                    $firstTopic = Topic::where('module_id', $firstModule->id)
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
                            ]
                        );

                        /*
                        |--------------------------------------------------
                        | 🔔 NEXT LEVEL UNLOCKED
                        |--------------------------------------------------
                        */
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
                                        'level_title' => $nextLevel->title ?? null,
                                    ]
                                ],
                                ['db', 'push']
                            );
                        }
                    }
                }
            }
        }
    }

    public function handleLevelExamPass($userId, $currentLevel)
    {
        /*
        |--------------------------------------------------
        | 🔓 NEXT LEVEL
        |--------------------------------------------------
        */
        $nextLevel = \App\Models\Level::where(
            'program_id',
            $currentLevel->program_id
        )
            ->where('id', '>', $currentLevel->id)
            ->orderBy('id')
            ->first();

        if (!$nextLevel) {
            return;
        }

        /*
        |--------------------------------------------------
        | 👤 USER
        |--------------------------------------------------
        */
        $user = User::find($userId);

        /*
        |--------------------------------------------------
        | 📝 AUDIT
        |--------------------------------------------------
        */
        \App\Services\AuditService::log(
            'level_unlocked',
            'User unlocked next level',
            [
                'level_id' => $nextLevel->id
            ]
        );

        /*
        |--------------------------------------------------
        | 🔹 FIRST MODULE
        |--------------------------------------------------
        */
        $firstModule = \App\Models\Module::where(
            'level_id',
            $nextLevel->id
        )
            ->orderBy('id')
            ->first();

        if (!$firstModule) {
            return;
        }

        /*
        |--------------------------------------------------
        | 🔹 FIRST CHAPTER
        |--------------------------------------------------
        */
        $firstChapter = \App\Models\Chapter::where(
            'module_id',
            $firstModule->id
        )
            ->orderBy('id')
            ->first();

        if (!$firstChapter) {
            return;
        }

        /*
        |--------------------------------------------------
        | 🔹 FIRST TOPIC
        |--------------------------------------------------
        */
        $firstTopic = \App\Models\Topic::where(
            'chapter_id',
            $firstChapter->id
        )
            ->orderBy('id')
            ->first();

        if (!$firstTopic) {
            return;
        }

        /*
        |--------------------------------------------------
        | 🔥 UNLOCK ENTRY
        |--------------------------------------------------
        */
        \App\Models\UserProgress::firstOrCreate(
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

        /*
        |--------------------------------------------------
        | 🔔 USER NOTIFICATION
        |--------------------------------------------------
        */
        if ($user) {

            app(NotificationService::class)->send(
                $user,
                'LEVEL_UNLOCKED',
                [
                    'title' => 'Next Level Unlocked',
                    'message' => 'You unlocked the next level successfully',

                    'screen' => 'LevelDetails',
                    'id' => $nextLevel->id,

                    'meta' => [
                        'level_id' => $nextLevel->id,
                        'level_title' => $nextLevel->title ?? null,
                    ]
                ],
                ['db', 'push']
            );

            /*
            |--------------------------------------------------
            | 🛡 ADMIN PAYLOAD
            |--------------------------------------------------
            */
            $adminPayload = [
                'title' => 'Level Unlocked',
                'message' => "{$user->name} unlocked a new level",

                'screen' => 'LevelDetails',
                'id' => $nextLevel->id,

                'meta' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,

                    'level_id' => $nextLevel->id,
                    'level_title' => $nextLevel->title ?? null,
                ]
            ];

            /*
            |--------------------------------------------------
            | 🛡 ADMINS
            |--------------------------------------------------
            */
            app(NotificationService::class)->sendToRole(
                'admin',
                'LEVEL_UNLOCKED',
                $adminPayload,
                ['db', 'push']
            );

            /*
            |--------------------------------------------------
            | 👑 SUPER ADMINS
            |--------------------------------------------------
            */
            app(NotificationService::class)->sendToRole(
                'superadmin',
                'LEVEL_UNLOCKED',
                $adminPayload,
                ['db', 'push']
            );
        }
    }
}
