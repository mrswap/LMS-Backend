<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use App\Models\TopicContent;

class ContentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 🌐 Resolve Language
    |--------------------------------------------------------------------------
    */
    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    /*
    |--------------------------------------------------------------------------
    | 📚 Topic Content (Trainee)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request, $topic_id)
    {
        $userId = auth()->id();
        $lang = $this->resolveLanguage($request);

        /*
        |--------------------------------------------------------------------------
        | 🔒 LOCK CHECK
        |--------------------------------------------------------------------------
        */
        $progress = UserProgress::where('user_id', $userId)
            ->where('topic_id', $topic_id)
            ->first();

        if (!$progress || !$progress->is_unlocked) {
            return response()->json([
                'success' => false,
                'message' => 'Topic is locked'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 📦 CONTENT QUERY
        |--------------------------------------------------------------------------
        */
        $query = TopicContent::with('translations')
            ->where('topic_id', $topic_id)
            ->where('status', true)
            ->orderBy('order');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 PAGINATION
        |--------------------------------------------------------------------------
        */
        $limit = (int) $request->get('limit', 5);
        $limit = ($limit > 0 && $limit <= 20) ? $limit : 5;

        $contents = $query->paginate($limit);

        /*
        |--------------------------------------------------------------------------
        | 🔁 TRANSFORM (LANGUAGE BASED)
        |--------------------------------------------------------------------------
        */
        $contents->getCollection()->transform(function ($item) use ($lang) {

            if ($lang === 'en') {

                if ($item->title === 'BASE_RECORD') return null;

                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'content' => $item->type === 'text' ? $item->content : null,
                    'meta' => $item->meta,
                    'order' => $item->order,
                ];
            }

            // 🔹 Translation mode
            $translation = $item->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $item->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'type' => $item->type,
                'title' => $translation->title,
                'content' => $item->type === 'text' ? $translation->content : null,
                'meta' => $item->meta,
                'order' => $item->order,
            ];
        });

        // 🔥 null हटाओ (missing translation case)
        $contents->setCollection(
            $contents->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $contents
        ]);
    }
}
