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
    | RESOLVE LANGUAGE
    |--------------------------------------------------------------------------
    |
    | Priority:
    |
    | 1. X-Lang header
    | 2. ?lang=
    | 3. Accept-Language
    | 4. en
    |
    | Supported examples:
    |
    | X-Lang: eng
    | X-Lang: english
    | X-Lang: en
    |
    | X-Lang: hindi
    | X-Lang: hin
    | X-Lang: hi
    |
    | X-Lang: punjabi
    | X-Lang: panjabi
    | X-Lang: pun
    | X-Lang: pa
    |
    */

    private function resolveLanguage(Request $request): string
    {
        /*
        |--------------------------------------------------------------------------
        | X-Lang
        |--------------------------------------------------------------------------
        */

        $xLang = strtolower(
            trim(
                (string) $request->header('X-Lang', '')
            )
        );

        if ($xLang !== '') {

            return match ($xLang) {

                'eng',
                'english',
                'en' => 'en',

                'hindi',
                'hin',
                'hi' => 'hi',

                'punjabi',
                'panjabi',
                'pun',
                'pa' => 'pa',

                default => $xLang,
            };
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY / ACCEPT-LANGUAGE
        |--------------------------------------------------------------------------
        */

        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';

        /*
        |--------------------------------------------------------------------------
        | NORMALIZE
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | hi-IN → hi
        | en-US → en
        |
        */

        $lang = strtolower(
            trim(
                explode(',', $lang)[0]
            )
        );

        $lang = explode('-', $lang)[0];

        return match ($lang) {

            'eng',
            'english' => 'en',

            'hindi',
            'hin' => 'hi',

            'punjabi',
            'panjabi',
            'pun' => 'pa',

            default => $lang,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | 📚 TOPIC CONTENT
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

        /*
        |--------------------------------------------------------------------------
        | TOPIC ACCESS
        |--------------------------------------------------------------------------
        */

        $progress = UserProgress::where(
            'user_id',
            $userId
        )
            ->where(
                'topic_id',
                $topic_id
            )
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
            $query->where(
                'type',
                $request->type
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL CONTENTS
        |--------------------------------------------------------------------------
        */

        $allTopicContentIds = TopicContent::where(
            'topic_id',
            $topic_id
        )
            ->where('status', true)
            ->where('publish_status', 'published')
            ->pluck('id');

        $totalContents = $allTopicContentIds->count();

        $readContents = \App\Models\UserContentProgress::where(
            'user_id',
            $userId
        )
            ->whereIn(
                'topic_content_id',
                $allTopicContentIds
            )
            ->where(
                'is_read',
                true
            )
            ->count();

        $isAllRead = $totalContents > 0
            ? $totalContents === $readContents
            : true;

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $limit = (int) $request->get(
            'limit',
            5
        );

        $limit = (
            $limit > 0
            && $limit <= 20
        )
            ? $limit
            : 5;

        $contents = $query->paginate($limit);

        /*
        |--------------------------------------------------------------------------
        | USER CONTENT PROGRESS
        |--------------------------------------------------------------------------
        */

        $userContentProgress =
            \App\Models\UserContentProgress::where(
                'user_id',
                $userId
            )
                ->whereIn(
                    'topic_content_id',
                    $contents->pluck('id')
                )
                ->get()
                ->keyBy('topic_content_id');

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM CONTENTS
        |--------------------------------------------------------------------------
        */

        $contents->getCollection()->transform(
            function ($item) use (
                $lang,
                $userContentProgress
            ) {

                $progress =
                    $userContentProgress[$item->id]
                    ?? null;

                $isRead =
                    $progress?->is_read
                    ?? false;

                $readAt =
                    $progress?->read_at
                    ?? null;

                /*
                |--------------------------------------------------------------------------
                | ENGLISH
                |--------------------------------------------------------------------------
                */

                if ($lang === 'en') {

                    if (
                        $item->title === 'BASE_RECORD'
                    ) {
                        return null;
                    }

                    return [
                        'id' => $item->id,

                        'type' => $item->type,

                        'title' => $item->title,

                        'content' =>
                            $item->type === 'text'
                                ? $item->content
                                : null,

                        'meta' => $item->meta,

                        'order' => $item->order,

                        /*
                        |----------------------------------------------------------
                        | ENGLISH AUDIO
                        |----------------------------------------------------------
                        */

                        'audio_url' =>
                            $item->audio_url,

                        'audio_content' =>
                            $item->audio_url,

                        'audio_generated_at' =>
                            $item->audio_generated_at,

                        'audio_provider' =>
                            $item->audio_provider,

                        'audio_path' =>
                            $item->audio_path,

                        'language_code' => 'en',

                        'is_read' => $isRead,

                        'read_at' => $readAt,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | OTHER LANGUAGES
                |--------------------------------------------------------------------------
                */

                $translation =
                    $item->translations
                        ->where(
                            'language_code',
                            $lang
                        )
                        ->first();

                if (!$translation) {
                    return null;
                }

                return [
                    'id' => $item->id,

                    'translation_id' =>
                        $translation->id,

                    'language_code' => $lang,

                    'type' => $item->type,

                    'title' =>
                        $translation->title,

                    'content' =>
                        $item->type === 'text'
                            ? $translation->content
                            : null,

                    'meta' => $item->meta,

                    'order' => $item->order,

                    /*
                    |--------------------------------------------------------------
                    | TRANSLATION AUDIO
                    |--------------------------------------------------------------
                    */

                    'audio_url' =>
                        $translation->audio_url,

                    'audio_content' =>
                        $translation->audio_url,

                    'audio_generated_at' =>
                        $translation->audio_generated_at,

                    'audio_provider' =>
                        $translation->audio_provider,

                    'audio_path' =>
                        $translation->audio_path,

                    'is_read' => $isRead,

                    'read_at' => $readAt,
                ];
            }
        );

        $contents->setCollection(
            $contents
                ->getCollection()
                ->filter()
                ->values()
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

        $assessment = Assessment::where(
            'assessmentable_id',
            $topic_id
        )
            ->where(
                'assessmentable_type',
                'App\Models\Topic'
            )
            ->where(
                'status',
                true
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | ATTEMPT
        |--------------------------------------------------------------------------
        */

        $passedAttempt = null;

        if ($assessment) {

            $attempt =
                AssessmentAttempt::where(
                    'user_id',
                    $userId
                )
                    ->where(
                        'assessment_id',
                        $assessment->id
                    )
                    ->whereIn(
                        'status',
                        [
                            'passed',
                            'failed'
                        ]
                    )
                    ->latest()
                    ->first();

            if ($attempt) {

                $assessmentStatus = [
                    'status' =>
                        $attempt->status,

                    'score' =>
                        $attempt->score,

                    'percentage' =>
                        $attempt->percentage,

                    'attempt_id' =>
                        $attempt->id,
                ];
            }

            $passedAttempt =
                AssessmentAttempt::where(
                    'user_id',
                    $userId
                )
                    ->where(
                        'assessment_id',
                        $assessment->id
                    )
                    ->where(
                        'status',
                        'passed'
                    )
                    ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | FLAGS
        |--------------------------------------------------------------------------
        */

        $isQuizAvailable =
            $isAllRead
            && $assessment;

        $isCompleted =
            $passedAttempt
                ? true
                : false;

        /*
        |--------------------------------------------------------------------------
        | CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = [

            'type' => 'topic',

            'is_all_read' =>
                $isAllRead,

            'is_quiz_available' =>
                (bool) $isQuizAvailable,

            'is_completed' =>
                $isCompleted,

            'progress' => [

                'total_contents' =>
                    $totalContents,

                'read_contents' =>
                    $readContents,

                'progress_percent' =>
                    $totalContents > 0
                        ? round(
                            (
                                $readContents
                                / $totalContents
                            ) * 100,
                            2
                        )
                        : 0,
            ],

            'topic' => [
                'id' => $topic->id,

                'title' => $topic->title,

                'estimated_duration' =>
                    $topic->estimated_duration,
            ],

            'chapter' => [
                'id' =>
                    $topic->chapter->id
                    ?? null,

                'title' =>
                    $topic->chapter->title
                    ?? null,
            ],

            'module' => [
                'id' =>
                    $topic->chapter->module->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->title
                    ?? null,
            ],

            'level' => [
                'id' =>
                    $topic->chapter->module->level->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->level->title
                    ?? null,
            ],

            'program' => [
                'id' =>
                    $topic->chapter->module->level->program->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->level->program->title
                    ?? null,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'context' => $context,

            'data' => $contents,

            'assessment_status' =>
                array_merge(
                    (array) $assessmentStatus,
                    [
                        'assessment' =>
                            $assessment
                                ? [
                                    'id' =>
                                        $assessment->id,

                                    'title' =>
                                        $assessment->title,

                                    'type' =>
                                        $assessment->type,

                                    'duration' =>
                                        $assessment->duration,

                                    'passing_score' =>
                                        $assessment->passing_score,
                                ]
                                : null,
                    ]
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE CONTENT
    |--------------------------------------------------------------------------
    */

    public function single(
        Request $request,
        $topic_id,
        $content_id
    ) {

        AuditService::log(
            'content_viewed',
            'User viewed a content item',
            [
                'content_id' => $content_id
            ]
        );

        $userId = auth()->id();

        $lang = $this->resolveLanguage(
            $request
        );

        /*
        |--------------------------------------------------------------------------
        | TOPIC ACCESS
        |--------------------------------------------------------------------------
        */

        $progress = UserProgress::where(
            'user_id',
            $userId
        )
            ->where(
                'topic_id',
                $topic_id
            )
            ->first();

        if (
            !$progress
            || !$progress->is_unlocked
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Topic is locked'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | TOPIC
        |--------------------------------------------------------------------------
        */

        $topic =
            \App\Models\Topic::with(
                'chapter.module.level.program'
            )
                ->findOrFail($topic_id);

        /*
        |--------------------------------------------------------------------------
        | CONTENTS
        |--------------------------------------------------------------------------
        */

        $contents = TopicContent::with(
            'translations'
        )
            ->where(
                'topic_id',
                $topic_id
            )
            ->where(
                'status',
                true
            )
            ->orderBy('order')
            ->get();

        if ($contents->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' => 'No content found'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT CONTENT
        |--------------------------------------------------------------------------
        */

        $currentIndex = $contents->search(
            fn ($c) =>
                $c->id == $content_id
        );

        if ($currentIndex === false) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Content not found in this topic'
            ], 404);
        }

        $current =
            $contents[$currentIndex];

        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        $previous =
            $contents[$currentIndex - 1]
            ?? null;

        $next =
            $contents[$currentIndex + 1]
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | USER CONTENT PROGRESS
        |--------------------------------------------------------------------------
        */

        $userProgress =
            \App\Models\UserContentProgress::where(
                'user_id',
                $userId
            )
                ->where(
                    'topic_content_id',
                    $current->id
                )
                ->first();

        $isRead =
            $userProgress?->is_read
            ?? false;

        $readAt =
            $userProgress?->read_at
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | MEDIA
        |--------------------------------------------------------------------------
        */

        $resolvedMedia = null;

        if (
            $current->type === 'media'
            && !empty(
                $current->meta['shortcode']
            )
        ) {

            $resolvedMedia =
                \App\Models\Media::where(
                    'shortcode',
                    $current->meta['shortcode']
                )->first();
        }

        /*
        |--------------------------------------------------------------------------
        | LANGUAGE RESOLUTION
        |--------------------------------------------------------------------------
        */

        $translation = null;

        /*
        |--------------------------------------------------------------------------
        | ENGLISH
        |--------------------------------------------------------------------------
        */

        if ($lang === 'en') {

            if (
                $current->title ===
                'BASE_RECORD'
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Content not available'
                ], 404);
            }

            $title =
                $current->title;

            $content =
                $current->content;

            /*
            |------------------------------------------------------------------
            | ENGLISH AUDIO
            |------------------------------------------------------------------
            */

            $audioUrl =
                $current->audio_url;

            $audioGeneratedAt =
                $current->audio_generated_at;

            $audioProvider =
                $current->audio_provider;

            $audioPath =
                $current->audio_path;

            $translationId = null;
        }

        /*
        |--------------------------------------------------------------------------
        | OTHER LANGUAGES
        |--------------------------------------------------------------------------
        */

        else {

            $translation =
                $current->translations
                    ->where(
                        'language_code',
                        $lang
                    )
                    ->first();

            if (!$translation) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Translation not available'
                ], 404);
            }

            $title =
                $translation->title;

            $content =
                $translation->content;

            /*
            |------------------------------------------------------------------
            | TRANSLATION AUDIO
            |------------------------------------------------------------------
            */

            $audioUrl =
                $translation->audio_url;

            $audioGeneratedAt =
                $translation->audio_generated_at;

            $audioProvider =
                $translation->audio_provider;

            $audioPath =
                $translation->audio_path;

            $translationId =
                $translation->id;
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT DATA
        |--------------------------------------------------------------------------
        */

        $data = [

            'id' =>
                $current->id,

            'translation_id' =>
                $translationId,

            'language_code' =>
                $lang,

            'type' =>
                $current->type,

            'title' =>
                $title,

            'content' =>
                $content,

            /*
            |--------------------------------------------------------------------------
            | TTS
            |--------------------------------------------------------------------------
            */

            'audio_url' =>
                $audioUrl,

            'audio_content' =>
                $audioUrl,

            'audio_generated_at' =>
                $audioGeneratedAt,

            'audio_provider' =>
                $audioProvider,

            'audio_path' =>
                $audioPath,

            /*
            |--------------------------------------------------------------------------
            | MEDIA
            |--------------------------------------------------------------------------
            */

            'media' =>
                $resolvedMedia
                    ? [
                        'id' =>
                            $resolvedMedia->id,

                        'title' =>
                            $resolvedMedia->title,

                        'description' =>
                            $resolvedMedia->description,

                        'type' =>
                            $resolvedMedia->type,

                        'shortcode' =>
                            $resolvedMedia->shortcode,

                        'file' =>
                            $resolvedMedia->file,

                        'external_url' =>
                            $resolvedMedia->external_url,

                        'full_url' =>
                            $resolvedMedia->full_url,
                    ]
                    : null,

            /*
            |--------------------------------------------------------------------------
            | META
            |--------------------------------------------------------------------------
            */

            'meta' =>
                array_merge(
                    $current->meta ?? [],
                    $resolvedMedia
                        ? [
                            'full_url' =>
                                $resolvedMedia->full_url,

                            'file' =>
                                $resolvedMedia->file,

                            'type' =>
                                $resolvedMedia->type,
                        ]
                        : []
                ),

            /*
            |--------------------------------------------------------------------------
            | ORDER / PROGRESS
            |--------------------------------------------------------------------------
            */

            'order' =>
                $current->order,

            'is_read' =>
                $isRead,

            'read_at' =>
                $readAt,
        ];

        /*
        |--------------------------------------------------------------------------
        | TOPIC TRANSLATION
        |--------------------------------------------------------------------------
        */

        $topicTranslation =
            method_exists(
                $topic,
                'getTranslation'
            )
                ? $topic->getTranslation(
                    $lang
                )
                : null;

        $topicData = [

            'id' =>
                $topic->id,

            'title' =>
                $topicTranslation->title
                ?? $topic->title,

            'description' =>
                $topicTranslation->description
                ?? $topic->description,

            'thumbnail' =>
                $topic->thumbnail,

            'estimated_duration' =>
                $topic->estimated_duration,
        ];

        /*
        |--------------------------------------------------------------------------
        | CONTEXT
        |--------------------------------------------------------------------------
        */

        $context = [

            'chapter' => [
                'id' =>
                    $topic->chapter->id
                    ?? null,

                'title' =>
                    $topic->chapter->title
                    ?? null,
            ],

            'module' => [
                'id' =>
                    $topic->chapter->module->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->title
                    ?? null,
            ],

            'level' => [
                'id' =>
                    $topic->chapter->module->level->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->level->title
                    ?? null,
            ],

            'program' => [
                'id' =>
                    $topic->chapter->module->level->program->id
                    ?? null,

                'title' =>
                    $topic->chapter->module->level->program->title
                    ?? null,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'data' => [

                'topic' =>
                    $topicData,

                'context' =>
                    $context,

                'current' =>
                    $data,

                'navigation' => [

                    'previous_content_id' =>
                        $previous?->id,

                    'next_content_id' =>
                        $next?->id,

                    'has_previous' =>
                        $previous !== null,

                    'has_next' =>
                        $next !== null,
                ]
            ]
        ]);
    }
}

