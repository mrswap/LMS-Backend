<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use App\Models\UserContentProgress;
use App\Models\Media;
use App\Models\Question;
use App\Models\Topic;

use App\Models\TopicContent;
use App\Services\AuditService;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;


class ContentController extends Controller {
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

    private function resolveLanguage(Request $request): string {
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
    public function index(Request $request, $topic_id) {
        $userId = auth()->id();

        /*
    |--------------------------------------------------------------------------
    | MANUAL TRANSLATION TOGGLE
    |--------------------------------------------------------------------------
    | false = Always English
    | true  = resolveLanguage() will be executed
    |--------------------------------------------------------------------------
    */
        $useTranslations = false;

        $lang = $useTranslations
            ? $this->resolveLanguage($request)
            : 'en';

        $topic = \App\Models\Topic::with([
            'chapter.module.level.program',
            'translations'
        ])->findOrFail($topic_id);

        /*
    |--------------------------------------------------------------------------
    | TOPIC ACCESS
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
    | TOTAL CONTENTS
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

        $limit = (
            $limit > 0 &&
            $limit <= 20
        )
            ? $limit
            : 5;

        $contents = $query->paginate($limit);

        /*
    |--------------------------------------------------------------------------
    | USER CONTENT PROGRESS
    |--------------------------------------------------------------------------
    */

        $userContentProgress = \App\Models\UserContentProgress::where(
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
                $useTranslations,
                $userContentProgress
            ) {
                $progress = $userContentProgress[$item->id] ?? null;

                $isRead = $progress?->is_read ?? false;

                $readAt = $progress?->read_at ?? null;

                /*
            |--------------------------------------------------------------------------
            | ENGLISH CONTENT
            |--------------------------------------------------------------------------
            |
            | If translation toggle is OFF, English content will always return.
            |
            */

                if (!$useTranslations || $lang === 'en') {
                    if ($item->title === 'BASE_RECORD') {
                        return null;
                    }

                    return [
                        'id' => $item->id,

                        'type' => $item->type,

                        'title' => $item->title,

                        'content' => $item->type === 'text'
                            ? $item->content
                            : null,

                        'meta' => $item->meta,

                        'order' => $item->order,

                        /*
                    |--------------------------------------------------------------------------
                    | ENGLISH AUDIO
                    |--------------------------------------------------------------------------
                    */

                        'audio_url' => $item->audio_url,

                        'audio_content' => $item->audio_url,

                        'audio_generated_at' => $item->audio_generated_at,

                        'audio_provider' => $item->audio_provider,

                        'audio_path' => $item->audio_path,

                        'language_code' => 'en',

                        'is_read' => $isRead,

                        'read_at' => $readAt,
                    ];
                }

                /*
            |--------------------------------------------------------------------------
            | TRANSLATED CONTENT
            |--------------------------------------------------------------------------
            */

                $translation = $item->translations
                    ->where('language_code', $lang)
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | Translation Missing
            |--------------------------------------------------------------------------
            |
            | Existing behavior maintained: if translation is missing,
            | this content will not be returned.
            |
            */

                if (!$translation) {
                    return null;
                }

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

                    /*
                |--------------------------------------------------------------------------
                | TRANSLATION AUDIO
                |--------------------------------------------------------------------------
                */

                    'audio_url' => $translation->audio_url,

                    'audio_content' => $translation->audio_url,

                    'audio_generated_at' => $translation->audio_generated_at,

                    'audio_provider' => $translation->audio_provider,

                    'audio_path' => $translation->audio_path,

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
            $attempt = AssessmentAttempt::where(
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
                    'status' => $attempt->status,

                    'score' => $attempt->score,

                    'percentage' => $attempt->percentage,

                    'attempt_id' => $attempt->id,
                ];
            }

            $passedAttempt = AssessmentAttempt::where(
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

        $isQuizAvailable = $isAllRead && $assessment;

        $isCompleted = $passedAttempt
            ? true
            : false;

        /*
    |--------------------------------------------------------------------------
    | CONTEXT
    |--------------------------------------------------------------------------
    */

        $context = [
            'type' => 'topic',

            'is_all_read' => $isAllRead,

            'is_quiz_available' => (bool) $isQuizAvailable,

            'is_completed' => $isCompleted,

            'progress' => [
                'total_contents' => $totalContents,

                'read_contents' => $readContents,

                'progress_percent' => $totalContents > 0
                    ? round(
                        (
                            $readContents / $totalContents
                        ) * 100,
                        2
                    )
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

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,

            'context' => $context,

            'data' => $contents,

            'assessment_status' => array_merge(
                (array) $assessmentStatus,
                [
                    'assessment' => $assessment
                        ? [
                            'id' => $assessment->id,

                            'title' => $assessment->title,

                            'type' => $assessment->type,

                            'duration' => $assessment->duration,

                            'passing_score' => $assessment->passing_score,
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

    public function single(Request $request, $topic_id, $content_id) {
        AuditService::log(
            'content_viewed',
            'User viewed a content item',
            [
                'content_id' => $content_id,
                'topic_id' => $topic_id,
            ]
        );

        $userId = auth()->id();

        /*
    |--------------------------------------------------------------------------
    | LANGUAGE
    |--------------------------------------------------------------------------
    | Text will always be English.
    | Language is only used for fetching translated audio.
    |--------------------------------------------------------------------------
    */
        $lang = $this->resolveLanguage($request);

        /*
    |--------------------------------------------------------------------------
    | Get Topic
    |--------------------------------------------------------------------------
    */

        $topic = Topic::with([
            'chapter.module.level.program',
            'translations',
        ])->findOrFail($topic_id);

        /*
    |--------------------------------------------------------------------------
    | Get All Contents Of Current Topic
    |--------------------------------------------------------------------------
    */

        $contents = TopicContent::with('translations')
            ->where('topic_id', $topic_id)
            ->where('status', true)
            ->where(function ($query) {
                $query->where('publish_status', 'published')
                    ->orWhereNull('publish_status');
            })
            ->orderBy('order', 'asc')
            ->get();

        if ($contents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No content found',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Find Current Content Index
    |--------------------------------------------------------------------------
    */

        $currentIndex = $contents->search(
            fn($content) => (int) $content->id === (int) $content_id
        );

        if ($currentIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found in this topic',
            ], 404);
        }

        $current = $contents[$currentIndex];

        $previous = $currentIndex > 0
            ? $contents[$currentIndex - 1]
            : null;

        $next = $currentIndex < ($contents->count() - 1)
            ? $contents[$currentIndex + 1]
            : null;

        /*
    |--------------------------------------------------------------------------
    | Current Content Progress
    |--------------------------------------------------------------------------
    */

        $currentProgress = UserContentProgress::where('user_id', $userId)
            ->where('topic_content_id', $current->id)
            ->first();

        $isRead = (bool) ($currentProgress?->is_read ?? false);

        $readAt = $currentProgress?->read_at ?? null;

        /*
    |--------------------------------------------------------------------------
    | Resolve Media
    |--------------------------------------------------------------------------
    */

        $resolvedMedia = null;

        if (
            $current->type === 'media' &&
            !empty(data_get($current->meta, 'shortcode'))
        ) {
            $resolvedMedia = Media::where(
                'shortcode',
                data_get($current->meta, 'shortcode')
            )->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Find Requested Language Audio Only
    |--------------------------------------------------------------------------
    */

        $audioTranslation = null;

        if ($lang !== 'en') {
            $audioTranslation = $current->translations
                ->where('language_code', $lang)
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Audio Selection
    |--------------------------------------------------------------------------
    | Requested language audio first.
    | If unavailable, fallback to English audio.
    |--------------------------------------------------------------------------
    */

        $selectedAudioUrl = null;
        $selectedAudioPath = null;
        $selectedAudioGeneratedAt = null;
        $selectedAudioProvider = null;
        $selectedAudioLanguage = 'en';

        if (
            $audioTranslation &&
            (
                !empty($audioTranslation->audio_url) ||
                !empty($audioTranslation->audio_path)
            )
        ) {
            $selectedAudioUrl = $audioTranslation->audio_url;
            $selectedAudioPath = $audioTranslation->audio_path;
            $selectedAudioGeneratedAt = $audioTranslation->audio_generated_at;
            $selectedAudioProvider = $audioTranslation->audio_provider;
            $selectedAudioLanguage = $lang;
        } else {
            $selectedAudioUrl = $current->audio_url;
            $selectedAudioPath = $current->audio_path;
            $selectedAudioGeneratedAt = $current->audio_generated_at;
            $selectedAudioProvider = $current->audio_provider;
            $selectedAudioLanguage = 'en';
        }

        $selectedAudio = $selectedAudioUrl
            ?? $selectedAudioPath
            ?? null;

        /*
    |--------------------------------------------------------------------------
    | Current Content Data
    |--------------------------------------------------------------------------
    | Text is ALWAYS English.
    | Only audio changes according to X-Lang.
    |--------------------------------------------------------------------------
    */

        $currentData = [
            'id' => $current->id,
            'topic_id' => $current->topic_id,

            // Always English
            'title' => $current->title,
            'slug' => $current->slug ?? null,
            'type' => $current->type,
            'content' => $current->content,
            'body' => $current->content,

            // Audio according to requested language
            'audio_content' => $selectedAudio,
            'audio_url' => $selectedAudioUrl,
            'audio_path' => $selectedAudioPath,
            'audio_generated_at' => $selectedAudioGeneratedAt,
            'audio_provider' => $selectedAudioProvider,
            'audio_language_code' => $selectedAudioLanguage,

            'pdf_url' => $current->pdf_url ?? null,
            'image_url' => $current->image_url ?? null,

            'is_read' => $isRead,
            'read_at' => $readAt,

            'last_updated_at' => $current->updated_at,
            'updated_at' => $current->updated_at,
            'created_at' => $current->created_at,

            'meta' => array_merge(
                is_array($current->meta)
                    ? $current->meta
                    : [],
                [
                    'type' => $current->type,
                    'full_url' => $resolvedMedia?->full_url,
                ]
            ),

            'media' => $resolvedMedia
                ? [
                    'id' => $resolvedMedia->id,
                    'type' => $resolvedMedia->type,
                    'name' => $resolvedMedia->title
                        ?? $resolvedMedia->name
                        ?? null,
                    'full_url' => $resolvedMedia->full_url,
                    'thumbnail' => $resolvedMedia->thumbnail ?? null,
                    'size' => $resolvedMedia->size ?? null,
                    'duration' => $resolvedMedia->duration ?? null,
                ]
                : null,
        ];

        /*
    |--------------------------------------------------------------------------
    | Topic Data
    |--------------------------------------------------------------------------
    | Topic title and description always English.
    |--------------------------------------------------------------------------
    */

        $topicContentIds = TopicContent::where('topic_id', $topic->id)
            ->where('status', true)
            ->pluck('id');

        $totalTopicContents = $topicContentIds->count();

        $completedTopicContents = UserContentProgress::where('user_id', $userId)
            ->whereIn('topic_content_id', $topicContentIds)
            ->where('is_read', true)
            ->count();

        $topicProgress = $totalTopicContents > 0
            ? round(
                ($completedTopicContents / $totalTopicContents) * 100
            )
            : 0;

        $topicData = [
            'id' => $topic->id,

            // Always English
            'title' => $topic->title,
            'description' => $topic->description,

            'estimated_duration' => $topic->estimated_duration,
            'total_contents' => $totalTopicContents,
            'completed_contents' => $completedTopicContents,
            'progress' => $topicProgress,
        ];

        /*
    |--------------------------------------------------------------------------
    | Current Topic Contents
    |--------------------------------------------------------------------------
    | Titles always English.
    |--------------------------------------------------------------------------
    */

        $currentTopicContents = $contents
            ->map(function ($content) use ($userId) {
                $progress = UserContentProgress::where('user_id', $userId)
                    ->where('topic_content_id', $content->id)
                    ->first();

                return [
                    'id' => $content->id,

                    // Always English
                    'title' => $content->title,

                    'type' => $content->type,

                    'is_read' => (bool) (
                        $progress?->is_read ?? false
                    ),
                ];
            })
            ->values()
            ->toArray();

        /*
    |--------------------------------------------------------------------------
    | Chapter Topics
    |--------------------------------------------------------------------------
    | Topic titles always English.
    |--------------------------------------------------------------------------
    | topics table has no order column, so order by id.
    |--------------------------------------------------------------------------
    */

        $chapterTopics = Topic::with('translations')
            ->where('chapter_id', $topic->chapter_id)
            ->where('status', true)
            ->where(function ($query) {
                $query->where('publish_status', 'published')
                    ->orWhereNull('publish_status');
            })
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($chapterTopic) use ($topic, $userId) {

                /*
            |--------------------------------------------------------------------------
            | First Content Of Topic
            |--------------------------------------------------------------------------
            */

                $firstContent = TopicContent::where(
                    'topic_id',
                    $chapterTopic->id
                )
                    ->where('status', true)
                    ->where(function ($query) {
                        $query->where('publish_status', 'published')
                            ->orWhereNull('publish_status');
                    })
                    ->orderBy('order', 'asc')
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | Topic Completion
            |--------------------------------------------------------------------------
            */

                $chapterTopicContentIds = TopicContent::where(
                    'topic_id',
                    $chapterTopic->id
                )
                    ->where('status', true)
                    ->pluck('id');

                $totalContents = $chapterTopicContentIds->count();

                $completedContents = UserContentProgress::where(
                    'user_id',
                    $userId
                )
                    ->whereIn(
                        'topic_content_id',
                        $chapterTopicContentIds
                    )
                    ->where('is_read', true)
                    ->count();

                $isCompleted = $totalContents > 0
                    && $completedContents >= $totalContents;

                /*
            |--------------------------------------------------------------------------
            | Unlock Logic
            |--------------------------------------------------------------------------
            */

                $isUnlocked = true;

                return [
                    'id' => $chapterTopic->id,

                    // Always English
                    'title' => $chapterTopic->title,

                    'is_current' => (int) $chapterTopic->id
                        === (int) $topic->id,

                    'is_unlocked' => $isUnlocked,

                    'is_completed' => $isCompleted,

                    'first_content_id' => $firstContent?->id,
                ];
            })
            ->values()
            ->toArray();

        /*
    |--------------------------------------------------------------------------
    | Assessment
    |--------------------------------------------------------------------------
    */

        $assessmentData = null;

        $assessment = Assessment::where(
            'assessmentable_id',
            $topic->id
        )
            ->where('assessmentable_type', Topic::class)
            ->where('type', 'topic')
            ->where('status', true)
            ->first();

        if ($assessment) {
            $totalQuestions = AssessmentQuestion::where(
                'assessment_id',
                $assessment->id
            )->count();

            $assessmentData = [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'status' => 'ready',
                'total_questions' => $totalQuestions,
                'passing_marks' => $assessment->passing_score,
                'obtained_marks' => null,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Final Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Content fetched successfully',

            'data' => [
                'current' => $currentData,

                'topic' => $topicData,

                'navigation' => [
                    'has_previous' => $previous !== null,
                    'previous_content_id' => $previous?->id,

                    'has_next' => $next !== null,
                    'next_content_id' => $next?->id,
                ],

                'learning_navigation' => [
                    'current_topic_contents' => $currentTopicContents,
                    'chapter_topics' => $chapterTopics,
                    'assessment' => $assessmentData,
                ],
            ],
        ]);
    }
}
