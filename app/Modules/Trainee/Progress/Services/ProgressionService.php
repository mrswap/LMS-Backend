<?php

namespace App\Modules\Trainee\Progress\Services;

use App\Models\UserProgress;
use App\Models\Topic;
use App\Models\Chapter;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class ProgressionService
{
    public function handleTopicCompletion($userId, Topic $topic)
    {
        DB::transaction(function () use ($userId, $topic) {

            // ✅ 1. Mark topic completed
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

            // ✅ 2. Unlock next topic
            $nextTopic = Topic::where('chapter_id', $topic->chapter_id)
                ->where('id', '>', $topic->id)
                ->orderBy('id')
                ->first();

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
            }

            // ✅ 3. Check chapter completion
            $this->handleChapterCompletion($userId, $topic->chapter_id);
        });
    }

    private function handleChapterCompletion($userId, $chapterId)
    {
        $total = Topic::where('chapter_id', $chapterId)->count();

        $completed = UserProgress::where('user_id', $userId)
            ->where('chapter_id', $chapterId)
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
            }

            $this->handleModuleCompletion($userId, $chapter->module_id);
        }
    }

    private function handleModuleCompletion($userId, $moduleId)
    {
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

            $module = Module::find($moduleId);

            // 🔓 unlock next module
            $nextModule = Module::where('level_id', $module->level_id)
                ->where('id', '>', $module->id)
                ->orderBy('id')
                ->first();

            if ($nextModule) {
                $firstTopic = Topic::where('module_id', $nextModule->id)->orderBy('id')->first();

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
