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



    private function checkTopicAccess(
        int $userId,
        int $topicId
    ): array {
        $topic = Topic::find($topicId);

        if (!$topic) {
            return [
                'allowed' => false,
                'progress' => null,
            ];
        }

        $progress = UserProgress::where(
            'user_id',
            $userId
        )
            ->where(
                'topic_id',
                $topicId
            )
            ->first();

        /*
    |--------------------------------------------------------------------------
    | FIRST TOPIC
    |--------------------------------------------------------------------------
    */

        $previousTopic = Topic::where(
            'chapter_id',
            $topic->chapter_id
        )
            ->where('status', true)
            ->where(function ($query) {
                $query->where('publish_status', 'published')
                    ->orWhereNull('publish_status');
            })
            ->where(
                'id',
                '<',
                $topicId
            )
            ->orderBy('id', 'desc')
            ->first();

        if (!$previousTopic) {
            return [
                'allowed' => true,
                'progress' => $progress,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | CHECK PREVIOUS TOPIC
    |--------------------------------------------------------------------------
    */

        $previousProgress = UserProgress::where(
            'user_id',
            $userId
        )
            ->where(
                'topic_id',
                $previousTopic->id
            )
            ->first();

        if (!$previousProgress || !$previousProgress->is_completed) {
            return [
                'allowed' => false,
                'progress' => $progress,
            ];
        }

        return [
            'allowed' => true,
            'progress' => $progress,
        ];
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
    | CHECK TOPIC ACCESS
    |--------------------------------------------------------------------------
    */

        $topicAccess = $this->checkTopicAccess(
            $userId,
            $topic_id
        );

        if (!$topicAccess['allowed']) {
            return response()->json([
                'success' => false,
                'message' => 'Topic is locked',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | LANGUAGE
    |--------------------------------------------------------------------------
    */

        $lang = $this->resolveLanguage($request);

        /*
    |--------------------------------------------------------------------------
    | GET TOPIC
    |--------------------------------------------------------------------------
    */

        $topic = Topic::with([
            'chapter.module.level.program',
            'translations',
        ])->findOrFail($topic_id);

        /*
    |--------------------------------------------------------------------------
    | GET TOPIC CONTENTS
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
    | FIND CURRENT CONTENT
    |--------------------------------------------------------------------------
    */

        $currentIndex = $contents->search(
            fn($content) =>
            (int) $content->id === (int) $content_id
        );

        if ($currentIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found in this topic',
            ], 404);
        }

        $current = $contents[$currentIndex];

        /*
    |--------------------------------------------------------------------------
    | PREVIOUS / NEXT CONTENT
    |--------------------------------------------------------------------------
    */

        $previous = $currentIndex > 0
            ? $contents[$currentIndex - 1]
            : null;

        $next = $currentIndex < ($contents->count() - 1)
            ? $contents[$currentIndex + 1]
            : null;

        /*
    |--------------------------------------------------------------------------
    | CURRENT CONTENT PROGRESS
    |--------------------------------------------------------------------------
    */

        $currentProgress = UserContentProgress::where(
            'user_id',
            $userId
        )
            ->where(
                'topic_content_id',
                $current->id
            )
            ->first();

        $isRead = (bool) (
            $currentProgress?->is_read ?? false
        );

        $readAt = $currentProgress?->read_at;

        /*
    |--------------------------------------------------------------------------
    | RESOLVE MEDIA
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
    | AUDIO
    |--------------------------------------------------------------------------
    */

        $audioTranslation = null;

        if ($lang !== 'en') {
            $audioTranslation = $current->translations
                ->where('language_code', $lang)
                ->first();
        }

        $audioUrl = null;
        $audioPath = null;
        $audioGeneratedAt = null;
        $audioProvider = null;
        $audioLanguage = 'en';

        if (
            $audioTranslation &&
            (
                !empty($audioTranslation->audio_url) ||
                !empty($audioTranslation->audio_path)
            )
        ) {
            $audioUrl = $audioTranslation->audio_url;
            $audioPath = $audioTranslation->audio_path;
            $audioGeneratedAt = $audioTranslation->audio_generated_at;
            $audioProvider = $audioTranslation->audio_provider;
            $audioLanguage = $lang;
        } else {
            $audioUrl = $current->audio_url;
            $audioPath = $current->audio_path;
            $audioGeneratedAt = $current->audio_generated_at;
            $audioProvider = $current->audio_provider;
        }

        $audioContent = $audioUrl ?? $audioPath;

        /*
    |--------------------------------------------------------------------------
    | CURRENT CONTENT DATA
    |--------------------------------------------------------------------------
    */

        $currentData = [
            'id' => $current->id,
            'topic_id' => $current->topic_id,
            'title' => $current->title,
            'slug' => $current->slug ?? null,
            'type' => $current->type,

            // Always English content
            'content' => $current->content,
            'body' => $current->content,

            // Audio according to requested language
            'audio_content' => $audioContent,
            'audio_url' => $audioUrl,
            'audio_path' => $audioPath,
            'audio_generated_at' => $audioGeneratedAt,
            'audio_provider' => $audioProvider,
            'audio_language_code' => $audioLanguage,

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
    | TOPIC PROGRESS
    |--------------------------------------------------------------------------
    */

        $topicContentIds = $contents->pluck('id');

        $totalContents = $topicContentIds->count();

        $readContents = UserContentProgress::where(
            'user_id',
            $userId
        )
            ->whereIn(
                'topic_content_id',
                $topicContentIds
            )
            ->where(
                'is_read',
                true
            )
            ->count();

        $topicProgress = $totalContents > 0
            ? round(
                ($readContents / $totalContents) * 100
            )
            : 0;

        /*
    |--------------------------------------------------------------------------
    | ALL CONTENTS READ
    |--------------------------------------------------------------------------
    */

        $isAllRead = $totalContents > 0
            ? $totalContents === $readContents
            : true;

        /*
    |--------------------------------------------------------------------------
    | TOPIC COMPLETION
    |--------------------------------------------------------------------------
    */

        $isCompleted = (bool) (
            $topicAccess['progress']?->is_completed ?? false
        );

        $topicData = [
            'id' => $topic->id,
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_duration' => $topic->estimated_duration,

            'total_contents' => $totalContents,
            'completed_contents' => $readContents,
            'progress' => $topicProgress,

            'is_all_read' => $isAllRead,
            'is_completed' => $isCompleted,
        ];

        /*
    |--------------------------------------------------------------------------
    | CURRENT TOPIC CONTENTS
    |--------------------------------------------------------------------------
    */

        $contentProgress = UserContentProgress::where(
            'user_id',
            $userId
        )
            ->whereIn(
                'topic_content_id',
                $topicContentIds
            )
            ->get()
            ->keyBy('topic_content_id');

        $currentTopicContents = $contents
            ->map(function ($content) use ($contentProgress) {

                $progress = $contentProgress->get(
                    $content->id
                );

                return [
                    'id' => $content->id,
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
    | CHAPTER TOPICS
    |--------------------------------------------------------------------------
    */

        $chapterTopics = $this->getChapterTopics(
            $topic,
            $userId
        );

        /*
    |--------------------------------------------------------------------------
    | ASSESSMENT
    |--------------------------------------------------------------------------
    */


        $isAllRead = $totalContents > 0
            ? $totalContents === $readContents
            : true;

        $assessmentData = $this->getTopicAssessment(
            $topic_id,
            $userId,
            $isAllRead
        );

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Content fetched successfully',

            'data' => [

                /*
            |--------------------------------------------------------------------------
            | CURRENT CONTENT
            |--------------------------------------------------------------------------
            */

                'current' => $currentData,

                /*
            |--------------------------------------------------------------------------
            | TOPIC
            |--------------------------------------------------------------------------
            */

                'topic' => $topicData,

                /*
            |--------------------------------------------------------------------------
            | CONTENT NAVIGATION
            |--------------------------------------------------------------------------
            */

                'navigation' => [
                    'has_previous' => $previous !== null,
                    'previous_content_id' => $previous?->id,

                    'has_next' => $next !== null,
                    'next_content_id' => $next?->id,
                ],

                /*
            |--------------------------------------------------------------------------
            | LEARNING NAVIGATION
            |--------------------------------------------------------------------------
            */

                'learning_navigation' => [

                    'current_topic_contents' => $currentTopicContents,

                    'chapter_topics' => $chapterTopics,

                    'assessment' => $assessmentData,
                ],
            ],
        ]);
    }

    private function getTopicAssessment(
        int $topicId,
        int $userId,
        bool $isAllRead
    ): ?array {
        /*
    |--------------------------------------------------------------------------
    | GET ASSESSMENT
    |--------------------------------------------------------------------------
    */

        $assessment = Assessment::where(
            'assessmentable_id',
            $topicId
        )
            ->where(
                'assessmentable_type',
                Topic::class
            )
            ->where(
                'type',
                'topic'
            )
            ->where(
                'status',
                true
            )
            ->first();

        if (!$assessment) {
            return null;
        }

        /*
    |--------------------------------------------------------------------------
    | TOTAL QUESTIONS
    |--------------------------------------------------------------------------
    */

        $totalQuestions = AssessmentQuestion::where(
            'assessment_id',
            $assessment->id
        )->count();

        /*
    |--------------------------------------------------------------------------
    | LATEST COMPLETED ATTEMPT
    |--------------------------------------------------------------------------
    */

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
                    'failed',
                ]
            )
            ->latest('id')
            ->first();

        /*
    |--------------------------------------------------------------------------
    | DETERMINE STATUS
    |--------------------------------------------------------------------------
    */

        if ($attempt) {

            $status = $attempt->status;
        } elseif ($isAllRead) {

            /*
         * All topic contents are read.
         * Quiz is ready to start.
         */

            $status = 'ready';
        } else {

            /*
         * Content is still pending.
         * Quiz is not ready yet.
         */

            $status = 'locked';
        }

        /*
    |--------------------------------------------------------------------------
    | COMPLETED
    |--------------------------------------------------------------------------
    */

        $isCompleted = $attempt &&
            $attempt->status === 'passed';

        /*
    |--------------------------------------------------------------------------
    | QUIZ AVAILABLE
    |--------------------------------------------------------------------------
    */

        $isQuizAvailable = $isAllRead &&
            !$isCompleted;

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return [
            'id' => $assessment->id,

            'title' => $assessment->title,

            'status' => $status,

            'total_questions' => $totalQuestions,

            'passing_marks' => $assessment->passing_score,

            'obtained_marks' => $attempt?->score,

            'percentage' => $attempt?->percentage,

            'attempt_id' => $attempt?->id,

            'is_quiz_available' => (bool) $isQuizAvailable,

            'is_completed' => (bool) $isCompleted,
        ];
    }


    private function getChapterTopics(
        Topic $topic,
        int $userId
    ): array {
        /*
    |--------------------------------------------------------------------------
    | GET CHAPTER TOPICS
    |--------------------------------------------------------------------------
    */

        $topics = Topic::where(
            'chapter_id',
            $topic->chapter_id
        )
            ->where(
                'status',
                true
            )
            ->where(function ($query) {
                $query->where(
                    'publish_status',
                    'published'
                )
                    ->orWhereNull('publish_status');
            })
            ->orderBy(
                'id',
                'asc'
            )
            ->get();

        /*
    |--------------------------------------------------------------------------
    | GET USER PROGRESS
    |--------------------------------------------------------------------------
    */

        $topicIds = $topics->pluck('id');

        $progresses = UserProgress::where(
            'user_id',
            $userId
        )
            ->whereIn(
                'topic_id',
                $topicIds
            )
            ->get()
            ->keyBy('topic_id');

        /*
    |--------------------------------------------------------------------------
    | BUILD TOPIC LIST
    |--------------------------------------------------------------------------
    */

        $previousCompleted = true;

        return $topics
            ->map(function ($chapterTopic) use (
                $topic,
                $progresses,
                &$previousCompleted
            ) {

                $progress = $progresses->get(
                    $chapterTopic->id
                );

                $isCompleted = (bool) (
                    $progress?->is_completed ?? false
                );

                /*
            |--------------------------------------------------------------------------
            | UNLOCK LOGIC
            |--------------------------------------------------------------------------
            |
            | Topic 1:
            | previousCompleted = true
            | => unlocked
            |
            | Topic 2:
            | previousCompleted = Topic 1 completed
            |
            | Topic 3:
            | previousCompleted = Topic 2 completed
            |
            */

                $isUnlocked = $previousCompleted;

                /*
            | Current topic becomes the previous
            | topic for the next iteration.
            */

                $previousCompleted = $isCompleted;

                /*
            |--------------------------------------------------------------------------
            | FIRST CONTENT
            |--------------------------------------------------------------------------
            */

                $firstContent = TopicContent::where(
                    'topic_id',
                    $chapterTopic->id
                )
                    ->where(
                        'status',
                        true
                    )
                    ->where(function ($query) {
                        $query->where(
                            'publish_status',
                            'published'
                        )
                            ->orWhereNull('publish_status');
                    })
                    ->orderBy(
                        'order',
                        'asc'
                    )
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

                return [
                    'id' => $chapterTopic->id,

                    
                    'title' => $chapterTopic->title,

                    'is_current' => (
                        (int) $chapterTopic->id ===
                        (int) $topic->id
                    ),

                    'is_unlocked' => $isUnlocked,

                    'is_completed' => $isCompleted,

                    'first_content_id' => $firstContent?->id,
                ];
            })
            ->values()
            ->toArray();
    }
}
