<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TopicContent;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Modules\Admin\ContentManagement\Requests\SectionContentRequest;
use App\Models\UserProgress;
use App\Services\AuditService;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class SectionContentController extends Controller
{
    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    /*
    |--------------------------------------------------------------------------
    | SYSTEM USER CHECK
    |--------------------------------------------------------------------------
    */

    private function isSystemUser(): bool
    {
        return auth()->user()?->isSystemUser() ?? false;
    }


    /*
    |--------------------------------------------------------------------------
    | DEFAULT GOVERNANCE VALUES
    |--------------------------------------------------------------------------
    */

    private function governanceDefaults(): array
    {
        $isSystemUser = $this->isSystemUser();

        return [
            'status' => $isSystemUser,

            'publish_status' => $isSystemUser
                ? TopicContent::PUBLISH_PUBLISHED
                : TopicContent::PUBLISH_DRAFT,
        ];
    }


    public function store(SectionContentRequest $request, $topicId)
    {

        $lang = $this->resolveLanguage($request);

        $data = $request->validated();

        $this->validateMediaShortcode($data);

        $baseData = [
            'topic_id' => $topicId,
            'type' => $data['type'],
            'meta' => $data['meta'] ?? null,
            'order' => $this->resolveSafeOrder(
                $topicId,
                $data['order'] ?? null
            ),
            'created_by' => auth()->id(),

            ...$this->governanceDefaults(),
        ];

        if ($lang === 'en') {

            $content = TopicContent::create([
                ...$baseData,
                'title' => $data['title'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        } else {

            $content = TopicContent::create([
                ...$baseData,
                'title' => 'BASE_RECORD',
                'content' => null,
            ]);

            $content->translations()->create([
                'language_code' => $lang,
                'title' => $data['title'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Created',
            'data' => $content
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | BULK STORE
    |--------------------------------------------------------------------------
    */

    public function bulkStore(Request $request, $topicId)
    {
        $lang = $this->resolveLanguage($request);

        $request->validate([
            'sections' => 'required|array|min:1',
            'sections.*.type' => 'required|in:text,media,h5p,quiz',
            'sections.*.title' => 'nullable|string',
            'sections.*.content' => 'nullable|string',
            'sections.*.order' => 'nullable|integer|min:1',
            'sections.*.media_shortcode' => 'nullable|string',
            'sections.*.meta' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {

            $created = [];

            /*
            |--------------------------------------------------------------------------
            | Current Max Order
            |--------------------------------------------------------------------------
            */
            $currentMaxOrder = TopicContent::where('topic_id', $topicId)
                ->max('order') ?? 0;

            /*
            |--------------------------------------------------------------------------
            | Used Orders Tracker
            |--------------------------------------------------------------------------
            */
            $usedOrders = TopicContent::where('topic_id', $topicId)
                ->pluck('order')
                ->toArray();

            foreach ($request->sections as $section) {

                $this->validateMediaShortcode($section);
                /*
                |--------------------------------------------------------------------------
                | Requested Order
                |--------------------------------------------------------------------------
                */
                $requestedOrder = isset($section['order'])
                    ? (int) $section['order']
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Auto Resolve Order Conflict
                |--------------------------------------------------------------------------
                |
                | If order already exists:
                | assign next available order
                |
                */
                if (
                    !$requestedOrder ||
                    in_array($requestedOrder, $usedOrders)
                ) {

                    $currentMaxOrder++;

                    $finalOrder = $currentMaxOrder;
                } else {

                    $finalOrder = $requestedOrder;

                    if ($finalOrder > $currentMaxOrder) {
                        $currentMaxOrder = $finalOrder;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Mark Order As Used
                |--------------------------------------------------------------------------
                */
                $usedOrders[] = $finalOrder;

                /*
                |--------------------------------------------------------------------------
                | Media Meta Handling
                |--------------------------------------------------------------------------
                */
                if ($section['type'] === 'media') {

                    $section['meta'] = [
                        'shortcode' => $section['media_shortcode']
                            ?? ($section['meta']['shortcode'] ?? null)
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Base Data
                |--------------------------------------------------------------------------
                */
                $baseData = [
                    'topic_id' => $topicId,
                    'type' => $section['type'],
                    'order' => $finalOrder,
                    'meta' => $section['meta'] ?? null,
                    'created_by' => auth()->id(),

                    ...$this->governanceDefaults(),
                ];

                /*
                |--------------------------------------------------------------------------
                | English Content
                |--------------------------------------------------------------------------
                */
                if ($lang === 'en') {

                    $content = TopicContent::create([
                        ...$baseData,
                        'title' => $section['title'] ?? null,
                        'content' => $section['content'] ?? null,
                    ]);
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Multilingual Content
                    |--------------------------------------------------------------------------
                    */
                    $content = TopicContent::create([
                        ...$baseData,
                        'title' => 'BASE_RECORD',
                        'content' => null,
                    ]);

                    $content->translations()->create([
                        'language_code' => $lang,
                        'title' => $section['title'] ?? null,
                        'content' => $section['content'] ?? null,
                    ]);
                }

                $created[] = $content;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk content created successfully',
                'count' => count($created),
                'data' => $created
            ]);
        } catch (ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())
                    ->flatten()
                    ->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BULK EDIT (Get all contents for update)
    |--------------------------------------------------------------------------
    */
    public function bulkEdit(Request $request, $topicId)
    {
        $lang = $this->resolveLanguage($request);

        $contents = TopicContent::where('topic_id', $topicId)
            ->with([
                'translations',
                'topic.program:id,title',
                'topic.level:id,title',
                'topic.module:id,title',
                'topic.chapter:id,title',
            ])
            ->orderBy('order')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | FETCH TOPIC DIRECTLY
        |--------------------------------------------------------------------------
        */

        $topic = \App\Models\Topic::with([
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
        ])
            ->find($topicId);
        $data = $contents->map(function ($item) use ($lang) {

            $translation = $item->translations
                ->where('language_code', $lang)
                ->first();

            return [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $translation->title ?? $item->title,
                'content' => $translation->content ?? $item->content,
                'meta' => $item->meta,
                'order' => $item->order,
                'status' => (bool)$item->status,
                'publish_status' => $item->publish_status,

                'audio_url' => $item->audio_url,
                'audio_generated_at' => $item->audio_generated_at,

                'media_shortcode' => $item->meta['shortcode'] ?? null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'topic_id' => (int)$topicId,
            'count' => $data->count(),

            // 🔥 FULL HIERARCHY
            'topic' => $topic ? [
                'id' => $topic->id,
                'title' => $topic->title,

                'program' => [
                    'id' => $topic->program->id ?? null,
                    'title' => $topic->program->title ?? null,
                ],
                'level' => [
                    'id' => $topic->level->id ?? null,
                    'title' => $topic->level->title ?? null,
                ],
                'module' => [
                    'id' => $topic->module->id ?? null,
                    'title' => $topic->module->title ?? null,
                ],
                'chapter' => [
                    'id' => $topic->chapter->id ?? null,
                    'title' => $topic->chapter->title ?? null,
                ],
            ] : null,

            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BULK UPDATE
    |--------------------------------------------------------------------------
    */

    public function bulkUpdate(Request $request, $topicId)
    {
        Log::info('================ BULK UPDATE START ================');

        Log::info('Incoming Bulk Update Request', [
            'topic_id' => $topicId,
            'request_size_bytes' => strlen($request->getContent()),
            'sections_count' => count($request->sections ?? []),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        try {

            $lang = $this->resolveLanguage($request);

            $isSystemUser = $this->isSystemUser();

            Log::info('Bulk Update Context', [
                'language' => $lang,
                'is_system_user' => $isSystemUser,
            ]);

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $request->validate([
                'sections' => 'required|array|min:1',
                'sections.*.id' => 'nullable|integer',
                'sections.*.type' => 'required|in:text,media,h5p,quiz',
                'sections.*.title' => 'nullable|string',
                'sections.*.content' => 'nullable|string',
                'sections.*.order' => 'required|integer',
                'sections.*.media_shortcode' => 'nullable|string',
                'sections.*.meta' => 'nullable|array',
                'sections.*.is_deleted' => 'nullable|boolean',
                'sections.*.is_new' => 'nullable|boolean',
            ]);

            Log::info('Validation Passed');

            /*
            |--------------------------------------------------------------------------
            | DUPLICATE ORDER CHECK
            |--------------------------------------------------------------------------
            */

            $orders = collect($request->sections)->pluck('order');

            if ($orders->duplicates()->isNotEmpty()) {

                Log::warning('Duplicate Orders Found', [
                    'orders' => $orders,
                    'duplicates' => $orders->duplicates(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate order values not allowed'
                ], 422);
            }

            DB::beginTransaction();

            Log::info('DB Transaction Started');

            $result = [];

            $ids = collect(
                $request->sections
            )->pluck('id')->filter();

            Log::info('Fetching Existing Contents', [
                'ids' => $ids,
            ]);

            $contents = TopicContent::withTrashed()
                ->where('topic_id', $topicId)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            Log::info('Existing Contents Loaded', [
                'loaded_count' => $contents->count(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | LOOP
            |--------------------------------------------------------------------------
            */

            foreach ($request->sections as $index => $section) {

                Log::info('------------------------------------------------');

                Log::info('Processing Section', [
                    'index' => $index,
                    'id' => $section['id'] ?? null,
                    'type' => $section['type'] ?? null,
                    'order' => $section['order'] ?? null,
                    'is_new' => $section['is_new'] ?? false,
                    'is_deleted' => $section['is_deleted'] ?? false,
                    'content_length' => strlen($section['content'] ?? ''),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | MEDIA META
                    |--------------------------------------------------------------------------
                    */
                    $this->validateMediaShortcode($section);

                    if ($section['type'] === 'media') {

                        $section['meta'] = [
                            'shortcode'
                            => $section['media_shortcode']
                                ?? ($section['meta']['shortcode'] ?? null)
                        ];

                        Log::info('Media Meta Prepared', [
                            'meta' => $section['meta'],
                        ]);
                    }

                    $resolvedOrder = $this->resolveSafeOrder(
                        $topicId,
                        $section['order'] ?? null,
                        $section['id'] ?? null
                    );
                    /*
                    |--------------------------------------------------------------------------
                    | BASE DATA
                    |--------------------------------------------------------------------------
                    */

                    $baseData = [
                        'topic_id' => $topicId,
                        'type' => $section['type'],
                        'order' => $resolvedOrder,
                        'meta' => $section['meta'] ?? null,
                        'created_by' => auth()->id(),
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE NEW
                    |--------------------------------------------------------------------------
                    */

                    if (
                        empty($section['id'])
                        || !empty($section['is_new'])
                    ) {

                        Log::info('Creating New Content');

                        $baseData = [
                            ...$baseData,
                            ...$this->governanceDefaults(),
                        ];

                        if ($lang === 'en') {

                            Log::info('Creating EN Content');

                            $content = TopicContent::create([
                                ...$baseData,
                                'title' => $section['title'] ?? null,
                                'content' => $section['content'] ?? null,
                            ]);
                        } else {

                            Log::info('Creating Multilingual Base Record');

                            $content = TopicContent::create([
                                ...$baseData,
                                'title' => 'BASE_RECORD',
                                'content' => null,
                            ]);

                            Log::info('Creating Translation');

                            $content->translations()->create([
                                'language_code' => $lang,
                                'title' => $section['title'] ?? null,
                                'content' => $section['content'] ?? null,
                            ]);
                        }

                        Log::info('New Content Created', [
                            'content_id' => $content->id,
                        ]);

                        $result[] = $content;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | EXISTING
                    |--------------------------------------------------------------------------
                    */

                    Log::info('Finding Existing Content');

                    $content = $contents[$section['id']] ?? null;

                    if (!$content) {

                        Log::warning('Content Not Found', [
                            'id' => $section['id']
                        ]);

                        continue;
                    }

                    Log::info('Existing Content Loaded', [
                        'content_id' => $content->id,
                        'trashed' => $content->trashed(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | DELETE
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($section['is_deleted'])) {

                        Log::info('Deleting Content', [
                            'content_id' => $content->id
                        ]);

                        if (!$content->trashed()) {
                            $content->delete();
                        }

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | RESTORE
                    |--------------------------------------------------------------------------
                    */

                    if ($content->trashed()) {

                        Log::info('Restoring Content', [
                            'content_id' => $content->id
                        ]);

                        $content->restore();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SYSTEM USER CONTROLS
                    |--------------------------------------------------------------------------
                    */

                    if ($isSystemUser) {

                        Log::info('Applying System User Controls');

                        if (isset($section['status'])) {

                            $baseData['status']
                                = (bool) $section['status'];
                        }

                        if (!empty($section['publish_status'])) {

                            $allowedStatuses = [
                                TopicContent::PUBLISH_DRAFT,
                                TopicContent::PUBLISH_PUBLISHED,
                                TopicContent::PUBLISH_UNPUBLISHED,
                            ];

                            if (
                                in_array(
                                    $section['publish_status'],
                                    $allowedStatuses
                                )
                            ) {

                                $baseData['publish_status']
                                    = $section['publish_status'];
                            }
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE
                    |--------------------------------------------------------------------------
                    */

                    Log::info('Updating Content', [
                        'content_id' => $content->id,
                    ]);

                    if ($lang === 'en') {

                        $content->update([
                            ...$baseData,
                            'title' => $section['title'] ?? null,
                            'content' => $section['content'] ?? null,
                        ]);
                    } else {

                        $content->update($baseData);

                        $translation = $content->translations()
                            ->where('language_code', $lang)
                            ->first();

                        if ($translation) {

                            Log::info('Updating Translation');

                            $translation->update([
                                'title' => $section['title'] ?? null,
                                'content' => $section['content'] ?? null,
                            ]);
                        } else {

                            Log::info('Creating Translation');

                            $content->translations()->create([
                                'language_code' => $lang,
                                'title' => $section['title'] ?? null,
                                'content' => $section['content'] ?? null,
                            ]);
                        }

                        Log::info('Touching Content');

                        $content->touch();
                    }

                    Log::info('Content Updated Successfully', [
                        'content_id' => $content->id,
                    ]);

                    $result[] = $content;
                } catch (\Throwable $sectionError) {

                    Log::error('SECTION FAILED', [
                        'index' => $index,
                        'id' => $section['id'] ?? null,
                        'message' => $sectionError->getMessage(),
                        'line' => $sectionError->getLine(),
                        'file' => $sectionError->getFile(),
                        'trace' => $sectionError->getTraceAsString(),
                    ]);

                    throw $sectionError;
                }
            }

            /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

            DB::commit();

            Log::info('DB Transaction Committed');

            Log::info('================ BULK UPDATE SUCCESS ================', [
                'result_count' => count($result),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk operation successful',
                'count' => count($result),
                'data' => $result
            ]);
        } catch (ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())
                    ->flatten()
                    ->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('================ BULK UPDATE FAILED ================', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LIST (Admin)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request, $topicId = null)
    {
        $lang = $this->resolveLanguage($request);

        $query = TopicContent::with([
            'translations',
            'topic.program:id,title',
            'topic.level:id,title',
            'topic.module:id,title',
            'topic.chapter:id,title',
            'creator:id,name',

        ]);

        if ($topicId) {
            $query->where('topic_id', $topicId);
        }

        if ($request->filled('program_id')) {
            $query->whereHas(
                'topic',
                fn($q) =>
                $q->where('program_id', $request->program_id)
            );
        }

        if ($request->filled('level_id')) {
            $query->whereHas(
                'topic',
                fn($q) =>
                $q->where('level_id', $request->level_id)
            );
        }

        if ($request->filled('module_id')) {
            $query->whereHas(
                'topic',
                fn($q) =>
                $q->where('module_id', $request->module_id)
            );
        }

        if ($request->filled('chapter_id')) {
            $query->whereHas(
                'topic',
                fn($q) =>
                $q->where('chapter_id', $request->chapter_id)
            );
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            if ($request->status !== 'all') {
                $query->where('status', (bool)$request->status);
            }
        } else {
            $query->where('status', true);
        }

        /*
        |-----------------------------
        | PUBLISH STATUS
        |-----------------------------
        */
        if ($request->has('publish_status')) {

            if ($request->publish_status !== 'all') {

                $query->where(
                    'publish_status',
                    $request->publish_status
                );
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;

            if ($lang === 'en') {
                $query->where(
                    fn($q) =>
                    $q->where('title', 'like', "%$search%")
                        ->orWhere('content', 'like', "%$search%")
                );
            } else {
                $query->whereHas(
                    'translations',
                    fn($q) =>
                    $q->where('language_code', $lang)
                        ->where(
                            fn($q2) =>
                            $q2->where('title', 'like', "%$search%")
                                ->orWhere('content', 'like', "%$search%")
                        )
                );
            }
        }

        $query->orderBy('topic_id')->orderBy('order');

        $limit = (int)$request->get('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        $contents = $query->paginate($limit);

        $contents->getCollection()->transform(function ($item) use ($lang) {

            $translation = $item->translations
                ->where('language_code', $lang)
                ->first();

            $title = $translation->title ?? $item->title;
            $content = $translation->content ?? $item->content;

            if ($title === 'BASE_RECORD') return null;

            return [
                'id' => $item->id,
                'topic_id' => $item->topic_id,
                'type' => $item->type,
                'title' => $title,
                'content' => $item->type === 'text' ? $content : null,
                'meta' => $item->meta,
                'order' => $item->order,
                'status' => (bool)$item->status,
                'publish_status' => $item->publish_status,
                'audio_url' => $item->audio_url,
                'audio_generated_at' => $item->audio_generated_at,
                'creator' => [
                    'id' => $item->creator->id ?? null,
                    'name' => $item->creator->name ?? null,
                ],


                'topic' => [
                    'id' => $item->topic->id ?? null,
                    'title' => $item->topic->title ?? null,
                    'program' => [
                        'id' => $item->topic->program->id ?? null,
                        'title' => $item->topic->program->title ?? null,
                    ],
                    'level' => [
                        'id' => $item->topic->level->id ?? null,
                        'title' => $item->topic->level->title ?? null,
                    ],
                    'module' => [
                        'id' => $item->topic->module->id ?? null,
                        'title' => $item->topic->module->title ?? null,
                    ],
                    'chapter' => [
                        'id' => $item->topic->chapter->id ?? null,
                        'title' => $item->topic->chapter->title ?? null,
                    ],
                ],
            ];
        });

        $contents->setCollection(
            $contents->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $contents
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, $topicId, $id)
    {
        $lang = $this->resolveLanguage($request);

        $item = TopicContent::with([
            'translations',
            'topic.program:id,title',
            'topic.level:id,title',
            'topic.module:id,title',
            'topic.chapter:id,title',
        ])
            ->where('topic_id', $topicId)
            ->findOrFail($id);

        $translation = $item->translations
            ->where('language_code', $lang)
            ->first();

        $title = $translation->title ?? $item->title;
        $content = $translation->content ?? $item->content;

        if ($title === 'BASE_RECORD') {
            return response()->json([
                'success' => false,
                'message' => 'Content not available'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 🎬 RESOLVE MEDIA
        |--------------------------------------------------------------------------
        */
        $resolvedMedia = null;

        if ($item->type === 'media' && !empty($item->meta['shortcode'])) {

            $resolvedMedia = \App\Models\Media::where('shortcode', $item->meta['shortcode'])->first();
        }

        return response()->json([
            'success' => true,

            'data' => [
                'id' => $item->id,
                'topic_id' => $item->topic_id,
                'type' => $item->type,
                'title' => $title,

                // TEXT CONTENT ONLY
                'content' => $content,

                // MEDIA CONTENT
                // MEDIA CONTENT
                'media' => $resolvedMedia ? [
                    'id' => $resolvedMedia->id,
                    'title' => $resolvedMedia->title,

                    // ✅ USE CONTENT AS DESCRIPTION
                    'content' => $content,

                    'description' => $resolvedMedia->description,
                    'type' => $resolvedMedia->type,
                    'shortcode' => $resolvedMedia->shortcode,
                    'file' => $resolvedMedia->file,
                    'external_url' => $resolvedMedia->external_url,
                    'full_url' => $resolvedMedia->full_url,
                ] : null,

                'meta' => array_merge(
                    $item->meta ?? [],
                    $resolvedMedia ? [
                        'full_url' => $resolvedMedia->full_url,
                        'file' => $resolvedMedia->file,
                        'type' => $resolvedMedia->type,
                    ] : []
                ),

                'order' => $item->order,
                'status' => (bool)$item->status,
                'publish_status' => $item->publish_status,
                'audio_url' => $item->audio_url,
                'audio_generated_at' => $item->audio_generated_at,
                'topic' => [
                    'id' => $item->topic->id ?? null,
                    'title' => $item->topic->title ?? null,

                    'program' => [
                        'id' => $item->topic->program->id ?? null,
                        'title' => $item->topic->program->title ?? null,
                    ],

                    'level' => [
                        'id' => $item->topic->level->id ?? null,
                        'title' => $item->topic->level->title ?? null,
                    ],

                    'module' => [
                        'id' => $item->topic->module->id ?? null,
                        'title' => $item->topic->module->title ?? null,
                    ],

                    'chapter' => [
                        'id' => $item->topic->chapter->id ?? null,
                        'title' => $item->topic->chapter->title ?? null,
                    ],
                ],
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FULL (Frontend API)
    |--------------------------------------------------------------------------
    */
    public function full(Request $request, $topicId)
    {
        $lang = $this->resolveLanguage($request);

        $contents = TopicContent::where('topic_id', $topicId)
            ->where('status', true)
            ->with('translations')
            ->orderBy('order')
            ->get();

        // 🔥 FIX: preload media (no N+1)
        $shortcodes = $contents->pluck('meta.shortcode')->filter()->unique();

        $mediaMap = Media::whereIn('shortcode', $shortcodes)
            ->get()
            ->keyBy('shortcode');

        $data = $contents->map(function ($item) use ($lang, $mediaMap) {

            $translation = $item->translations
                ->where('language_code', $lang)
                ->first();

            $title = $translation->title ?? $item->title;
            $content = $translation->content ?? $item->content;

            if ($title === 'BASE_RECORD') return null;

            if ($item->type === 'media') {
                $shortcode = $item->meta['shortcode'] ?? null;
                $media = $mediaMap[$shortcode] ?? null;

                if (!$media) return null;

                return [
                    'type' => 'media',
                    'title' => $title,
                    'data' => $media
                ];
            }

            if ($item->type === 'text') {
                if (!$content || trim(strip_tags($content)) === '') return null;

                return [
                    'type' => 'text',
                    'title' => $title,
                    'content' => $content,
                    'audio_url' => $item->audio_url,
                    'audio_generated_at' => $item->audio_generated_at,
                ];
            }

            return null;
        })->filter()->values();

        return response()->json($data);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(SectionContentRequest $request, $topicId, $id)
    {
        $lang = $this->resolveLanguage($request);

        $content = TopicContent::where('topic_id', $topicId)
            ->findOrFail($id);

        $data = $request->validated();

        $this->validateMediaShortcode($data);

        if ($lang === 'en') {

            $content->update([
                ...$data,

                'order' => $this->resolveSafeOrder(
                    $topicId,
                    $data['order'] ?? null,
                    $content->id
                ),

                'created_by' => auth()->id(),
            ]);
        } else {

            $translation = $content->translations()
                ->where('language_code', $lang)
                ->first();

            if ($translation) {

                $translation->update([
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
            } else {

                $content->translations()->create([
                    'language_code' => $lang,
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
            }

            /*
            |---------------------------------------------------
            | IMPORTANT
            |---------------------------------------------------
            | Trigger TopicContent saved event
            | So TTS + AI Context regenerate works
            */
            $content->touch();
        }

        return response()->json([
            'success' => true,
            'message' => 'Updated',
            'data' => $content->fresh([
                'translations'
            ])
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($topicId, $id)
    {
        $content = TopicContent::where('topic_id', $topicId)->findOrFail($id);
        $content->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | REORDER
    |--------------------------------------------------------------------------
    */
    public function reorder(Request $request, $topicId)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($request, $topicId) {

            /*
        |--------------------------------------------------------------------------
        | TEMP SHIFT
        |--------------------------------------------------------------------------
        */

            foreach ($request->items as $item) {

                TopicContent::where('topic_id', $topicId)
                    ->where('id', $item['id'])
                    ->update([
                        'order' => 100000 + $item['order']
                    ]);
            }

            /*
        |--------------------------------------------------------------------------
        | FINAL ORDER
        |--------------------------------------------------------------------------
        */

            foreach ($request->items as $item) {

                TopicContent::where('topic_id', $topicId)
                    ->where('id', $item['id'])
                    ->update([
                        'order' => $item['order']
                    ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Order updated'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($topicId, $id)
    {
        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER VALIDATION
        |--------------------------------------------------------------------------
        */

        if (!$this->isSystemUser()) {

            return response()->json([

                'success' => false,

                'message'
                => 'Only system users can change status'

            ], 403);
        }

        $content = TopicContent::where(
            'topic_id',
            $topicId
        )->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | TOGGLE STATUS
        |--------------------------------------------------------------------------
        */

        $newStatus = !$content->status;

        /*
        |--------------------------------------------------------------------------
        | AUTO PUBLISH STATUS HANDLING
        |--------------------------------------------------------------------------
        */

        $publishStatus = $newStatus
            ? TopicContent::PUBLISH_PUBLISHED
            : TopicContent::PUBLISH_UNPUBLISHED;

        $content->update([

            'status'
            => $newStatus,

            'publish_status'
            => $publishStatus,
        ]);

        return response()->json([

            'success' => true,

            'message'
            => 'Status updated',

            'data' => [

                'id'
                => $content->id,

                'status'
                => (bool) $content->status,

                'publish_status'
                => $content->publish_status,
            ]
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

            $resolvedMedia = \App\Models\Media::where(
                'shortcode',
                $current->meta['shortcode']
            )->first();
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
                'audio_url' => $current->audio_url,
                'audio_generated_at' => $current->audio_generated_at,
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
                'audio_url' => $current->audio_url,
                'audio_generated_at' => $current->audio_generated_at,
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


    /*
    |--------------------------------------------------------------------------
    | RESOLVE SAFE ORDER
    |--------------------------------------------------------------------------
    */
    private function resolveSafeOrder(int $topicId, ?int $requestedOrder = null, ?int $ignoreId = null): int
    {

        $query = TopicContent::where('topic_id', $topicId);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $usedOrders = $query
            ->pluck('order')
            ->toArray();

        $maxOrder = empty($usedOrders)
            ? 0
            : max($usedOrders);

        /*
        |--------------------------------------------------------------------------
        | AUTO ORDER
        |--------------------------------------------------------------------------
        */

        if (
            !$requestedOrder
            || in_array($requestedOrder, $usedOrders)
        ) {

            return $maxOrder + 1;
        }

        return $requestedOrder;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE MEDIA SHORTCODE
    |--------------------------------------------------------------------------
    */

    private function validateMediaShortcode(array $section): void
    {
        if (($section['type'] ?? null) !== 'media') {
            return;
        }

        $shortcode = $section['media_shortcode']
            ?? ($section['meta']['shortcode'] ?? null);

        if (!$shortcode) {

            throw ValidationException::withMessages([
                'media_shortcode' => [
                    'Media shortcode is required'
                ]
            ]);
        }

        $exists = Media::where(
            'shortcode',
            $shortcode
        )->exists();

        if (!$exists) {

            throw ValidationException::withMessages([
                'media_shortcode' => [
                    "Media not found for shortcode: {$shortcode}"
                ]
            ]);
        }
    }
}
