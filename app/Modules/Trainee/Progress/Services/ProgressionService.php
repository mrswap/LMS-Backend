<?php

namespace App\Modules\Trainee\Progress\Services;

use App\Models\UserProgress;
use App\Models\Topic;
use App\Models\Chapter;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;

class ProgressionService
{
    public function handleTopicCompletion($userId, Topic $topic)
    {
        DB::transaction(function () use ($userId, $topic) {

            /*
        |--------------------------------------------------
        | ✅ 1. COMPLETE CURRENT TOPIC (SAFE)
        |--------------------------------------------------
        */
            $progress = UserProgress::where('user_id', $userId)
                ->where('topic_id', $topic->id)
                ->first();

            if (!$progress) {

                UserProgress::create([
                    'user_id' => $userId,
                    'program_id' => $topic->program_id,
                    'level_id' => $topic->level_id,
                    'module_id' => $topic->module_id,
                    'chapter_id' => $topic->chapter_id,
                    'topic_id' => $topic->id,
                    'is_unlocked' => true,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
            } else {

                if (!$progress->is_completed) {
                    $progress->update([
                        'is_completed' => true,
                        'completed_at' => now(),
                    ]);
                }
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
                $this->handleChapterCompletion($userId, $topic->chapter_id);
            }
        });
    }
    private function handleChapterCompletion($userId, $chapterId)
    {
        $total = Topic::where('chapter_id', $chapterId)->count();

        $completed = UserProgress::where('user_id', $userId)
            ->where('chapter_id', $chapterId)
            ->whereNotNull('topic_id') // 🔥 ADD THIS
            ->where('is_completed', true)
            ->count();

        if ($total > 0 && $total == $completed) {

            $chapter = Chapter::find($chapterId);


            // 🔓 unlock next chapter
            $nextChapter = Chapter::where('module_id', $chapter->module_id)
                ->where('id', '>', $chapter->id)
                ->orderBy('id')
                ->first();

            if ($nextChapter) {
                $firstTopic = Topic::where('chapter_id', $nextChapter->id)->orderBy('id')->first();

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
                }

                AuditService::log('chapter_unlocked', 'User unlocked the next chapter', ['chapter_id' => $nextChapter->id ?? null]);
            }

            $this->handleModuleCompletion($userId, $chapter->module_id);
        }
    }

    private function handleModuleCompletion($userId, $moduleId)
    {
        // 🔹 Get all chapters
        $chapters = Chapter::where('module_id', $moduleId)->pluck('id');

        $totalChapters = $chapters->count();
        $completedChapters = 0;

        foreach ($chapters as $chapterId) {

            // 🔹 Total topics in chapter
            $totalTopics = Topic::where('chapter_id', $chapterId)->pluck('id');

            // 🔹 Completed topics (STRICT MATCH)
            $completedTopicIds = UserProgress::where('user_id', $userId)
                ->whereIn('topic_id', $totalTopics)
                ->whereNotNull('topic_id') // 🔥 ADD THIS
                ->where('is_completed', true)
                ->pluck('topic_id')
                ->unique();

            // ✅ Compare by IDs (NOT count blindly)
            if ($totalTopics->count() > 0 && $totalTopics->count() === $completedTopicIds->count()) {
                $completedChapters++;
            }
        }

        /*
        |--------------------------------------------------
        | ✅ MODULE COMPLETED
        |--------------------------------------------------
        */
        if ($totalChapters > 0 && $totalChapters === $completedChapters) {

            $module = Module::find($moduleId);

            if (!$module) return;


            /*
        |--------------------------------------------
        | 🔥 STORE MODULE COMPLETION (IMPORTANT)
        |--------------------------------------------
        */
            UserProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'module_id' => $moduleId,
                    'topic_id' => null // 🔥 important
                ],
                [
                    'program_id' => $module->program_id ?? null,
                    'level_id' => $module->level_id,
                    'is_completed' => true,
                    'completed_at' => now(),
                ]
            );

            /*
        |--------------------------------------------
        | 🔓 UNLOCK NEXT MODULE
        |--------------------------------------------
        */
            $nextModule = Module::where('level_id', $module->level_id)
                ->where('id', '>', $module->id)
                ->orderBy('id')
                ->first();

            if ($nextModule) {

                AuditService::log(
                    'module_unlocked',
                    'User unlocked next module',
                    ['module_id' => $nextModule->id]
                );

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
                }

                AuditService::log('module_unlocked', 'User unlocked next module', ['module_id' => $nextModule->id ?? null]);
            }

            /*
            |--------------------------------------------
            | 🔥 LEVEL CHECK TRIGGER
            |--------------------------------------------
            */
            //$this->handleLevelCompletion($userId, $module->level_id);
        }
    }
    private function handleLevelCompletion($userId, $levelId)
    {
        $modules = Module::where('level_id', $levelId)->pluck('id');

        $totalModules = $modules->count();
        $completedModules = 0;

        foreach ($modules as $moduleId) {

            $chapters = Chapter::where('module_id', $moduleId)->pluck('id');

            $totalChapters = $chapters->count();
            $completedChapters = 0;

            foreach ($chapters as $chapterId) {

                $totalTopics = Topic::where('chapter_id', $chapterId)->count();

                $completedTopics = UserProgress::where('user_id', $userId)
                    ->where('chapter_id', $chapterId)
                    ->where('is_completed', true)
                    ->count();

                if ($totalTopics > 0 && $totalTopics == $completedTopics) {
                    $completedChapters++;
                }
            }

            if ($totalChapters > 0 && $totalChapters == $completedChapters) {
                $completedModules++;
            }
        }

        /*
        |---------------------------------------
        | ✅ LEVEL COMPLETED
        |---------------------------------------
        */
        if ($totalModules > 0 && $totalModules == $completedModules) {

            $level = \App\Models\Level::find($levelId);

            \App\Services\AuditService::log(
                'level_completed',
                'User completed a level',
                ['level_id' => $level->id]
            );

            // 🔓 unlock next level
            $nextLevel = \App\Models\Level::where('program_id', $level->program_id)
                ->where('id', '>', $level->id)
                ->orderBy('id')
                ->first();

            if ($nextLevel) {

                \App\Services\AuditService::log(
                    'level_unlocked',
                    'User unlocked next level',
                    ['level_id' => $nextLevel->id]
                );

                $firstModule = Module::where('level_id', $nextLevel->id)->orderBy('id')->first();

                if ($firstModule) {

                    $firstTopic = Topic::where('module_id', $firstModule->id)->orderBy('id')->first();

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
                    }
                }
            }
        }
    }

    public function handleLevelExamPass($userId, $currentLevel)
    {
        $nextLevel = \App\Models\Level::where('program_id', $currentLevel->program_id)
            ->where('id', '>', $currentLevel->id)
            ->orderBy('id')
            ->first();

        if (!$nextLevel) return;

        \App\Services\AuditService::log(
            'level_unlocked',
            'User unlocked next level',
            ['level_id' => $nextLevel->id]
        );

        // 🔹 first module
        $firstModule = \App\Models\Module::where('level_id', $nextLevel->id)
            ->orderBy('id')
            ->first();

        if (!$firstModule) return;

        // 🔹 first chapter
        $firstChapter = \App\Models\Chapter::where('module_id', $firstModule->id)
            ->orderBy('id')
            ->first();

        if (!$firstChapter) return;

        // 🔹 first topic
        $firstTopic = \App\Models\Topic::where('chapter_id', $firstChapter->id)
            ->orderBy('id')
            ->first();

        if (!$firstTopic) return;

        // 🔥 UNLOCK ENTRY
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
    }
}
