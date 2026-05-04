<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use App\Models\TopicContent;
use App\Services\AuditService;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;


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
    | 📦 LOAD TOPIC WITH FULL HIERARCHY
    |--------------------------------------------------------------------------
    */
        $topic = \App\Models\Topic::with([
            'chapter.module.level.program',
            'translations'
        ])->findOrFail($topic_id);

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

        $limit = (int) $request->get('limit', 5);
        $limit = ($limit > 0 && $limit <= 20) ? $limit : 5;

        $contents = $query->paginate($limit);

        /*
    |--------------------------------------------------------------------------
    | 🧠 USER CONTENT PROGRESS
    |--------------------------------------------------------------------------
    */
        $userContentProgress = \App\Models\UserContentProgress::where('user_id', $userId)
            ->whereIn('topic_content_id', $contents->pluck('id'))
            ->get()
            ->keyBy('topic_content_id');

        /*
    |--------------------------------------------------------------------------
    | 🔁 TRANSFORM CONTENT
    |--------------------------------------------------------------------------
    */
        $contents->getCollection()->transform(function ($item) use ($lang, $userContentProgress) {

            $progress = $userContentProgress[$item->id] ?? null;

            $isRead = $progress?->is_read ?? false;
            $readAt = $progress?->read_at ?? null;

            if ($lang === 'en') {

                if ($item->title === 'BASE_RECORD') return null;

                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'content' => $item->type === 'text' ? $item->content : null,
                    'meta' => $item->meta,
                    'order' => $item->order,
                    'is_read' => $isRead,
                    'read_at' => $readAt,
                ];
            }

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
                'is_read' => $isRead,
                'read_at' => $readAt,
            ];
        });

        $contents->setCollection(
            $contents->getCollection()->filter()->values()
        );

        /*
    |--------------------------------------------------------------------------
    | 🎯 ASSESSMENT STATUS
    |--------------------------------------------------------------------------
    */
        $assessmentStatus = [
            'status' => 'not_attempted',
            'score' => null,
            'percentage' => null,
            'attempt_id' => null,
        ];

        $assessment = Assessment::where('assessmentable_id', $topic_id)
            ->where('assessmentable_type', \App\Models\Topic::class)
            ->first();

        if ($assessment) {
            $attempt = AssessmentAttempt::where('user_id', $userId)
                ->where('assessment_id', $assessment->id)
                ->whereIn('status', ['passed', 'failed'])
                ->latest()
                ->first();

            if ($attempt) {
                $assessmentStatus = [
                    'status' => $attempt->status,
                    'score' => $attempt->score,
                    'percentage' => $attempt->percentage,
                    'attempt_id' => $attempt->id,
                ];
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 🌐 CONTEXT (HIERARCHY)
    |--------------------------------------------------------------------------
    */
        $context = [
            'type' => 'topic',

            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'estimated_duration' => $topic->estimated_duration,
            ],

            'chapter' => [
                'id' => $topic->chapter->id ?? null,
                'title' => $topic->chapter->title ?? null,
            ],

            'module' => [
                'id' => $topic->chapter->module->id ?? null,
                'title' => $topic->chapter->module->title ?? null,
            ],

            'level' => [
                'id' => $topic->chapter->module->level->id ?? null,
                'title' => $topic->chapter->module->level->title ?? null,
            ],

            'program' => [
                'id' => $topic->chapter->module->level->program->id ?? null,
                'title' => $topic->chapter->module->level->program->title ?? null,
            ],
        ];

        /*
    |--------------------------------------------------------------------------
    | 📤 RESPONSE
    |--------------------------------------------------------------------------
    */
        return response()->json([
            'success' => true,
            'context' => $context,
            'data' => $contents,
            'assessment_status' => $assessmentStatus
        ]);
    }

    public function single(Request $request, $topic_id, $content_id)
    {
        AuditService::log('content_viewed', 'User viewed a content item', ['content_id' => $content_id]);

        $userId = auth()->id();
        $lang = $this->resolveLanguage($request);

        /*
        | 🔒 LOCK CHECK
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
        | 📦 LOAD TOPIC WITH FULL RELATION
        */
        $topic = \App\Models\Topic::with('chapter.module.level.program')
            ->findOrFail($topic_id);

        /*
        | 📦 ALL CONTENTS
        */
        $contents = TopicContent::with('translations')
            ->where('topic_id', $topic_id)
            ->where('status', true)
            ->orderBy('order')
            ->get();

        if ($contents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No content found'
            ], 404);
        }

        /*
        | 📍 FIND CURRENT
        */
        $currentIndex = $contents->search(fn($c) => $c->id == $content_id);

        if ($currentIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found in this topic'
            ], 404);
        }

        $current = $contents[$currentIndex];

        /*
        | 🔁 NAVIGATION
        */
        $previous = $contents[$currentIndex - 1] ?? null;
        $next = $contents[$currentIndex + 1] ?? null;

        /*
        | 🧠 USER READ STATUS
        */
        $userProgress = \App\Models\UserContentProgress::where('user_id', $userId)
            ->where('topic_content_id', $current->id)
            ->first();

        $isRead = $userProgress?->is_read ?? false;
        $readAt = $userProgress?->read_at ?? null;

        /*
        | 🌐 LANGUAGE TRANSFORM
        */
        if ($lang === 'en') {

            if ($current->title === 'BASE_RECORD') {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not available'
                ], 404);
            }

            $data = [
                'id' => $current->id,
                'type' => $current->type,
                'title' => $current->title,
                'content' => $current->type === 'text' ? $current->content : null,
                'meta' => $current->meta,
                'order' => $current->order,
                'is_read' => $isRead,
                'read_at' => $readAt,
            ];
        } else {

            $translation = $current->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Translation not available'
                ], 404);
            }

            $data = [
                'id' => $current->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'type' => $current->type,
                'title' => $translation->title,
                'content' => $current->type === 'text' ? $translation->content : null,
                'meta' => $current->meta,
                'order' => $current->order,
                'is_read' => $isRead,
                'read_at' => $readAt,
            ];
        }

        /*
        | 🌐 TOPIC TRANSLATION
        */
        $topicTranslation = method_exists($topic, 'getTranslation')
            ? $topic->getTranslation($lang)
            : null;

        $topicData = [
            'id' => $topic->id,
            'title' => $topicTranslation->title ?? $topic->title,
            'description' => $topicTranslation->description ?? $topic->description,
            'thumbnail' => $topic->thumbnail,
            'estimated_duration' => $topic->estimated_duration,
        ];

        /*
        | 🌐 CONTEXT (HIERARCHY)
        */
        $context = [
            'chapter' => [
                'id' => $topic->chapter->id ?? null,
                'title' => $topic->chapter->title ?? null,
            ],
            'module' => [
                'id' => $topic->chapter->module->id ?? null,
                'title' => $topic->chapter->module->title ?? null,
            ],
            'level' => [
                'id' => $topic->chapter->module->level->id ?? null,
                'title' => $topic->chapter->module->level->title ?? null,
            ],
            'program' => [
                'id' => $topic->chapter->module->level->program->id ?? null,
                'title' => $topic->chapter->module->level->program->title ?? null,
            ],
        ];

        /*
        | 📤 RESPONSE
        */
        return response()->json([
            'success' => true,
            'data' => [
                'topic' => $topicData,

                // ✅ ADDED CONTEXT HERE
                'context' => $context,

                'current' => $data,

                'navigation' => [
                    'previous_content_id' => $previous?->id,
                    'next_content_id' => $next?->id,
                    'has_previous' => $previous !== null,
                    'has_next' => $next !== null,
                ]
            ]
        ]);
    }

    
}
