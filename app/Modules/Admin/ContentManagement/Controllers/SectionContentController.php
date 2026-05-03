<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TopicContent;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Modules\Admin\ContentManagement\Requests\SectionContentRequest;

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
        | CREATE (Single)
        |--------------------------------------------------------------------------
        */
    public function store(SectionContentRequest $request, $topicId)
    {
        $lang = $this->resolveLanguage($request);

        $data = $request->validated();
        $data['topic_id'] = $topicId;
        $data['created_by'] = auth()->id();

        if ($lang === 'en') {
            $content = TopicContent::create($data);
        } else {
            $content = TopicContent::create([
                'topic_id' => $topicId,
                'type' => $data['type'],
                'title' => 'BASE_RECORD',
                'content' => null,
                'meta' => $data['meta'] ?? null,
                'order' => $data['order'] ?? 0,
                'created_by' => auth()->id(),
            ]);

            $content->translations()->create([
                'language_code' => $lang,
                'title' => $data['title'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Created', 'data' => $content]);
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
            'sections.*.order' => 'required|integer',
            'sections.*.media_shortcode' => 'nullable|string',
            'sections.*.meta' => 'nullable|array',
        ]);

        // ❗ Prevent duplicate order
        $orders = collect($request->sections)->pluck('order');
        if ($orders->duplicates()->isNotEmpty()) {
            return response()->json([
                'message' => 'Duplicate order values not allowed'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $created = [];

            foreach ($request->sections as $section) {

                // Normalize media meta
                if ($section['type'] === 'media') {
                    $section['meta'] = [
                        'shortcode' => $section['media_shortcode']
                            ?? ($section['meta']['shortcode'] ?? null)
                    ];
                }

                $baseData = [
                    'topic_id' => $topicId,
                    'type' => $section['type'],
                    'order' => $section['order'],
                    'meta' => $section['meta'] ?? null,
                    'created_by' => auth()->id(),
                ];

                if ($lang === 'en') {
                    $baseData['title'] = $section['title'] ?? null;
                    $baseData['content'] = $section['content'] ?? null;

                    $content = TopicContent::create($baseData);
                } else {
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
                'message' => 'Bulk content created successfully',
                'count' => count($created),
                'data' => $created
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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
            ->with('translations')
            ->orderBy('order')
            ->get();

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

                // 👇 important for frontend handling
                'media_shortcode' => $item->meta['shortcode'] ?? null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'topic_id' => (int)$topicId,
            'count' => $data->count(),
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
        $lang = $this->resolveLanguage($request);

        $request->validate([
            'sections' => 'required|array|min:1',
            'sections.*.id' => [
                'required',
                'integer',
                Rule::exists('topic_contents', 'id')->where(
                    fn($q) => $q->where('topic_id', $topicId)
                )
            ],
            'sections.*.type' => 'required|in:text,media,h5p,quiz',
            'sections.*.title' => 'nullable|string',
            'sections.*.content' => 'nullable|string',
            'sections.*.order' => 'required|integer',
            'sections.*.media_shortcode' => 'nullable|string',
            'sections.*.meta' => 'nullable|array',
        ]);

        // ❗ Prevent duplicate order
        $orders = collect($request->sections)->pluck('order');
        if ($orders->duplicates()->isNotEmpty()) {
            return response()->json([
                'message' => 'Duplicate order values not allowed'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updated = [];

            foreach ($request->sections as $section) {

                $content = TopicContent::where('topic_id', $topicId)
                    ->findOrFail($section['id']);

                // Normalize media meta
                if ($section['type'] === 'media') {
                    $section['meta'] = [
                        'shortcode' => $section['media_shortcode']
                            ?? ($section['meta']['shortcode'] ?? null)
                    ];
                }

                $baseData = [
                    'type' => $section['type'],
                    'order' => $section['order'],
                    'meta' => $section['meta'] ?? null,
                ];

                if ($lang === 'en') {

                    $baseData['title'] = $section['title'] ?? null;
                    $baseData['content'] = $section['content'] ?? null;

                    $content->update($baseData);
                } else {

                    // update base (keep placeholder)
                    $content->update($baseData);

                    $translation = $content->translations()
                        ->where('language_code', $lang)
                        ->first();

                    if ($translation) {
                        $translation->update([
                            'title' => $section['title'] ?? null,
                            'content' => $section['content'] ?? null,
                        ]);
                    } else {
                        $content->translations()->create([
                            'language_code' => $lang,
                            'title' => $section['title'] ?? null,
                            'content' => $section['content'] ?? null,
                        ]);
                    }
                }

                $updated[] = $content;
            }

            DB::commit();

            return response()->json([
                'message' => 'Bulk content updated successfully',
                'count' => count($updated),
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'topic_id' => $item->topic_id,
                'type' => $item->type,
                'title' => $title,
                'content' => $item->type === 'text' ? $content : null,
                'meta' => $item->meta,
                'order' => $item->order,
                'status' => (bool)$item->status,
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
                    'content' => $content
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

        $content = TopicContent::where('topic_id', $topicId)->findOrFail($id);
        $data = $request->validated();

        if ($lang === 'en') {
            $content->update($data);
        } else {
            $translation = $content->translations()
                ->where('language_code', $lang)
                ->first();

            if ($translation) {
                $translation->update($data);
            } else {
                $content->translations()->create([
                    'language_code' => $lang,
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
            }
        }

        return response()->json(['message' => 'Updated']);
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
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('topic_contents', 'id')->where(
                    fn($q) =>
                    $q->where('topic_id', $topicId)
                )
            ],
            'items.*.order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            TopicContent::where('topic_id', $topicId)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Order updated']);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($topicId, $id)
    {
        $content = TopicContent::where('topic_id', $topicId)->findOrFail($id);

        $content->status = !$content->status;
        $content->save();

        return response()->json([
            'message' => 'Status updated',
            'status' => $content->status
        ]);
    }
}
