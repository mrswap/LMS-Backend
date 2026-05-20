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

        $topic = \App\Models\Topic::with([
            'chapter.module.level.program',
            'translations'
        ])->findOrFail($topic_id);

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
    | CONTENT QUERY
    |--------------------------------------------------------------------------
    */
        $query = TopicContent::with('translations')
            ->where('topic_id', $topic_id)
            ->where('status', true)
            ->where('publish_status', 'published')
            ->orderBy('order');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        /*
    |--------------------------------------------------------------------------
    | TOTAL CONTENTS (ALL)
    |--------------------------------------------------------------------------
    */
        $allTopicContentIds = TopicContent::where('topic_id', $topic_id)
            ->where('status', true)
            ->where('publish_status', 'published')
            ->pluck('id');

        $totalContents = $allTopicContentIds->count();

        $readContents = \App\Models\UserContentProgress::where('user_id', $userId)
            ->whereIn('topic_content_id', $allTopicContentIds)
            ->where('is_read', true)
            ->count();

        $isAllRead = $totalContents > 0
            ? $totalContents === $readContents
            : true;

        /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */
        $limit = (int) $request->get('limit', 5);
        $limit = ($limit > 0 && $limit <= 20) ? $limit : 5;

        $contents = $query->paginate($limit);

        /*
    |--------------------------------------------------------------------------
    | USER CONTENT PROGRESS
    |--------------------------------------------------------------------------
    */
        $userContentProgress = \App\Models\UserContentProgress::where('user_id', $userId)
            ->whereIn('topic_content_id', $contents->pluck('id'))
            ->get()
            ->keyBy('topic_content_id');

        /*
    |--------------------------------------------------------------------------
    | TRANSFORM CONTENTS
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
                'content' => $item->type === 'text'
                    ? $translation->content
                    : null,
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
    | ASSESSMENT
    |--------------------------------------------------------------------------
    */
        $assessmentStatus = [
            'status' => 'not_attempted',
            'score' => null,
            'percentage' => null,
            'attempt_id' => null,
        ];

        $assessment = Assessment::where('assessmentable_id', $topic_id)
            ->where('assessmentable_type', 'App\Models\Topic')
            ->where('status', true)
            ->first();

        /*
    |--------------------------------------------------------------------------
    | PASSED ATTEMPT
    |--------------------------------------------------------------------------
    */
        $passedAttempt = null;

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

            $passedAttempt = AssessmentAttempt::where('user_id', $userId)
                ->where('assessment_id', $assessment->id)
                ->where('status', 'passed')
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | FLAGS
    |--------------------------------------------------------------------------
    */

        // quiz tab/button available only after all content read
        $isQuizAvailable = $isAllRead && $assessment;

        // topic completed only if assessment passed
        $isCompleted = $passedAttempt ? true : false;

        /*
    |--------------------------------------------------------------------------
    | CONTEXT
    |--------------------------------------------------------------------------
    */
        $context = [
            'type' => 'topic',

            // 🔥 NEW FLAGS
            'is_all_read' => $isAllRead,
            'is_quiz_available' => (bool) $isQuizAvailable,
            'is_completed' => $isCompleted,

            // 🔥 OPTIONAL PROGRESS
            'progress' => [
                'total_contents' => $totalContents,
                'read_contents' => $readContents,
                'progress_percent' => $totalContents > 0
                    ? round(($readContents / $totalContents) * 100, 2)
                    : 0,
            ],

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

        return response()->json([
            'success' => true,

            'context' => $context,

            'data' => $contents,

            'assessment_status' => array_merge(
                (array) $assessmentStatus,
                [
                    'assessment' => $assessment ? [
                        'id' => $assessment->id,
                        'title' => $assessment->title,
                        'type' => $assessment->type,
                        'duration' => $assessment->duration,
                        'passing_score' => $assessment->passing_score,
                    ] : null,
                ]
            ),
        ]);
    }

    public function single(Request $request, $topic_id, $content_id)
    {
        AuditService::log(
            'content_viewed',
            'User viewed a content item',
            ['content_id' => $content_id]
        );

        $userId = auth()->id();
        $lang = $this->resolveLanguage($request);

        $progress = UserProgress::where('user_id', $userId)
            ->where('topic_id', $topic_id)
            ->first();

        if (!$progress || !$progress->is_unlocked) {
            return response()->json([
                'success' => false,
                'message' => 'Topic is locked'
            ], 403);
        }

        $topic = \App\Models\Topic::with('chapter.module.level.program')
            ->findOrFail($topic_id);

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

        $currentIndex = $contents->search(
            fn($c) => $c->id == $content_id
        );

        if ($currentIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found in this topic'
            ], 404);
        }

        $current = $contents[$currentIndex];

        $previous = $contents[$currentIndex - 1] ?? null;
        $next = $contents[$currentIndex + 1] ?? null;

        $userProgress = \App\Models\UserContentProgress::where(
            'user_id',
            $userId
        )
            ->where('topic_content_id', $current->id)
            ->first();

        $isRead = $userProgress?->is_read ?? false;
        $readAt = $userProgress?->read_at ?? null;

        $resolvedMedia = null;

        if (
            $current->type === 'media'
            && !empty($current->meta['shortcode'])
        ) {

            $resolvedMedia = \App\Models\Media::where('shortcode', $current->meta['shortcode'])->first();
        }

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

                'content' => $current->type === 'text'
                    ? $current->content
                    : $current->content,

                'media' => $resolvedMedia ? [
                    'id' => $resolvedMedia->id,
                    'title' => $resolvedMedia->title,
                    'description' => $resolvedMedia->description,
                    'type' => $resolvedMedia->type,
                    'shortcode' => $resolvedMedia->shortcode,
                    'file' => $resolvedMedia->file,
                    'external_url' => $resolvedMedia->external_url,
                    'full_url' => $resolvedMedia->full_url,
                ] : null,

                'meta' => array_merge(
                    $current->meta ?? [],
                    $resolvedMedia ? [
                        'full_url' => $resolvedMedia->full_url,
                        'file' => $resolvedMedia->file,
                        'type' => $resolvedMedia->type,
                    ] : []
                ),

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

                'content' => $current->type === 'text'
                    ? $translation->content
                    : $translation->content,

                'media' => $resolvedMedia ? [
                    'id' => $resolvedMedia->id,
                    'title' => $resolvedMedia->title,
                    'description' => $resolvedMedia->description,
                    'type' => $resolvedMedia->type,
                    'shortcode' => $resolvedMedia->shortcode,
                    'file' => $resolvedMedia->file,
                    'external_url' => $resolvedMedia->external_url,
                    'full_url' => $resolvedMedia->full_url,
                ] : null,

                'meta' => array_merge(
                    $current->meta ?? [],
                    $resolvedMedia ? [
                        'full_url' => $resolvedMedia->full_url,
                        'file' => $resolvedMedia->file,
                        'type' => $resolvedMedia->type,
                    ] : []
                ),

                'order' => $current->order,
                'is_read' => $isRead,
                'read_at' => $readAt,
            ];
        }

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

        return response()->json([
            'success' => true,
            'data' => [
                'topic' => $topicData,

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
