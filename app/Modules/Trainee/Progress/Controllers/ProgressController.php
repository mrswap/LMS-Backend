<?php

namespace App\Modules\Trainee\Progress\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use App\Models\Topic;

class ProgressController extends Controller
{
    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $lang = $this->resolveLanguage($request);

        $progress = UserProgress::where('user_id', $userId)
            ->get()
            ->keyBy('topic_id');

        /*
    |--------------------------------------------------------------------------
    | LEVEL → MODULES ONLY
    |--------------------------------------------------------------------------
    */
        if ($request->level_id) {

            $modules = Module::with('chapters.topics')
                ->where('level_id', $request->level_id)
                ->get();

            $data = $modules->map(function ($module) use ($progress, $lang) {

                // 🔥 module unlocked if ANY topic unlocked inside
                $isUnlocked = $module->chapters->flatMap->topics->contains(function ($topic) use ($progress) {
                    return ($progress[$topic->id]->is_unlocked ?? false);
                });

                $isCompleted = $module->chapters->flatMap->topics->every(function ($topic) use ($progress) {
                    return ($progress[$topic->id]->is_completed ?? false);
                });

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'thumbnail' => $module->thumbnail,

                    'is_unlocked' => $isUnlocked,
                    'is_completed' => $isCompleted,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | MODULE → CHAPTERS ONLY
    |--------------------------------------------------------------------------
    */
        if ($request->module_id) {

            $chapters = Chapter::with('topics')
                ->where('module_id', $request->module_id)
                ->get();

            $data = $chapters->map(function ($chapter) use ($progress) {

                $isUnlocked = $chapter->topics->contains(function ($topic) use ($progress) {
                    return ($progress[$topic->id]->is_unlocked ?? false);
                });

                $isCompleted = $chapter->topics->every(function ($topic) use ($progress) {
                    return ($progress[$topic->id]->is_completed ?? false);
                });

                return [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'description' => $chapter->description,
                    'thumbnail' => $chapter->thumbnail,

                    'is_unlocked' => $isUnlocked,
                    'is_completed' => $isCompleted,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | CHAPTER → TOPICS ONLY
    |--------------------------------------------------------------------------
    */
        if ($request->chapter_id) {

            $topics = Topic::where('chapter_id', $request->chapter_id)->get();

            $data = $topics->map(function ($topic) use ($progress) {

                $p = $progress[$topic->id] ?? null;

                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'thumbnail' => $topic->thumbnail,

                    'is_unlocked' => $p?->is_unlocked ?? false,
                    'is_completed' => $p?->is_completed ?? false,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid parameter'
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | MAPPERS
    |--------------------------------------------------------------------------
    */

    private function mapLevel($level, $progress, $lang)
    {
        $t = $this->getTranslated($level, $lang);

        return [
            'id' => $level->id,
            'title' => $t['title'],
            'description' => $t['description'],
            'thumbnail' => $level->thumbnail,

            'modules' => $level->modules->map(
                fn($module) =>
                $this->mapModule($module, $progress, $lang)
            )
        ];
    }

    private function mapModule($module, $progress, $lang)
    {
        $t = $this->getTranslated($module, $lang);

        return [
            'id' => $module->id,
            'title' => $t['title'],
            'description' => $t['description'],
            'thumbnail' => $module->thumbnail,

            'chapters' => $module->chapters->map(
                fn($chapter) =>
                $this->mapChapter($chapter, $progress, $lang)
            )
        ];
    }

    private function mapChapter($chapter, $progress, $lang)
    {
        $t = $this->getTranslated($chapter, $lang);

        return [
            'id' => $chapter->id,
            'title' => $t['title'],
            'description' => $t['description'],
            'thumbnail' => $chapter->thumbnail,

            'topics' => $chapter->topics->map(
                fn($topic) =>
                $this->mapTopic($topic, $progress, $lang)
            )
        ];
    }

    private function mapTopic($topic, $progress, $lang)
    {
        $p = $progress[$topic->id] ?? null;

        $t = $this->getTranslated($topic, $lang);

        return [
            'id' => $topic->id,
            'title' => $t['title'],
            'description' => $t['description'],
            'thumbnail' => $topic->thumbnail,
            'estimated_duration' => $topic->estimated_duration,

            'is_unlocked' => $p?->is_unlocked ?? false,
            'is_completed' => $p?->is_completed ?? false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSLATION HELPER
    |--------------------------------------------------------------------------
    */

    private function getTranslated($model, $lang)
    {
        $translation = method_exists($model, 'getTranslation')
            ? $model->getTranslation($lang)
            : null;

        return [
            'title' => $translation->title ?? $model->title,
            'description' => $translation->description ?? $model->description,
        ];
    }
}
