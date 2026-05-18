<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TopicContent;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Modules\Admin\ContentManagement\Requests\SectionContentRequest;
use App\Services\AuditService;

class SectionContentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | RESOLVE LANGUAGE
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
    | SYSTEM USER CHECK
    |--------------------------------------------------------------------------
    */

    private function isSystemUser(): bool
    {
        $user = auth()->user();

        $user?->loadMissing('role');

        return (bool) $user?->role?->is_system;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE (Single)
    |--------------------------------------------------------------------------
    */

    public function store(
        SectionContentRequest $request,
        $topicId
    ) {

        $lang = $this->resolveLanguage($request);

        $data = $request->validated();

        $isSystemUser = $this->isSystemUser();

        $baseData = [
            'topic_id' => $topicId,
            'type' => $data['type'],
            'meta' => $data['meta'] ?? null,
            'order' => $data['order'] ?? 0,
            'created_by' => auth()->id(),

            'status' => $isSystemUser
                ? ($data['status'] ?? true)
                : false,

            'publish_status' => $isSystemUser
                ? ($data['publish_status'] ?? 'published')
                : 'draft',
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

            'sections.*.type'
            => 'required|in:text,media,h5p,quiz',

            'sections.*.title'
            => 'nullable|string|max:255',

            'sections.*.content'
            => 'nullable|string',

            'sections.*.order'
            => 'required|integer',

            'sections.*.media_shortcode'
            => 'nullable|string',

            'sections.*.meta'
            => 'nullable|array',

            'sections.*.status'
            => 'nullable|boolean',

            'sections.*.publish_status'
            => 'nullable|in:draft,published,unpublished',
        ]);

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE ORDER CHECK
        |--------------------------------------------------------------------------
        */

        $orders = collect($request->sections)
            ->pluck('order');

        if ($orders->duplicates()->isNotEmpty()) {

            return response()->json([
                'success' => false,
                'message'
                => 'Duplicate order values not allowed'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $created = [];

            $isSystemUser = $this->isSystemUser();

            foreach ($request->sections as $section) {

                /*
                |--------------------------------------------------------------------------
                | NORMALIZE MEDIA
                |--------------------------------------------------------------------------
                */

                if ($section['type'] === 'media') {

                    $section['meta'] = [
                        'shortcode'
                        => $section['media_shortcode']
                            ?? (
                                $section['meta']['shortcode']
                                ?? null
                            )
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | BASE DATA
                |--------------------------------------------------------------------------
                */

                $baseData = [
                    'topic_id' => $topicId,
                    'type' => $section['type'],
                    'order' => $section['order'],
                    'meta' => $section['meta'] ?? null,
                    'created_by' => auth()->id(),

                    'status' => $isSystemUser
                        ? ($section['status'] ?? true)
                        : false,

                    'publish_status' => $isSystemUser
                        ? (
                            $section['publish_status']
                            ?? 'published'
                        )
                        : 'draft',
                ];

                /*
                |--------------------------------------------------------------------------
                | CREATE
                |--------------------------------------------------------------------------
                */

                if ($lang === 'en') {

                    $content = TopicContent::create([
                        ...$baseData,

                        'title'
                        => $section['title'] ?? null,

                        'content'
                        => $section['content'] ?? null,
                    ]);
                } else {

                    $content = TopicContent::create([
                        ...$baseData,
                        'title' => 'BASE_RECORD',
                        'content' => null,
                    ]);

                    $content->translations()->create([
                        'language_code' => $lang,

                        'title'
                        => $section['title'] ?? null,

                        'content'
                        => $section['content'] ?? null,
                    ]);
                }

                $created[] = $content;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message'
                => 'Bulk content created successfully',

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
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        SectionContentRequest $request,
        $topicId,
        $id
    ) {

        $lang = $this->resolveLanguage($request);

        $content = TopicContent::where(
            'topic_id',
            $topicId
        )->findOrFail($id);

        $data = $request->validated();

        $isSystemUser = $this->isSystemUser();

        /*
        |--------------------------------------------------------------------------
        | BASE UPDATE DATA
        |--------------------------------------------------------------------------
        */

        $updateData = [
            'type' => $data['type'] ?? $content->type,
            'meta' => $data['meta'] ?? $content->meta,
            'order' => $data['order'] ?? $content->order,
        ];

        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER ONLY
        |--------------------------------------------------------------------------
        */

        if ($isSystemUser) {

            if (isset($data['status'])) {

                $updateData['status']
                    = (bool) $data['status'];
            }

            if (!empty($data['publish_status'])) {

                $updateData['publish_status']
                    = $data['publish_status'];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | LANGUAGE UPDATE
        |--------------------------------------------------------------------------
        */

        if ($lang === 'en') {

            $content->update([
                ...$updateData,

                'title'
                => $data['title']
                    ?? $content->title,

                'content'
                => $data['content']
                    ?? $content->content,
            ]);
        } else {

            $content->update($updateData);

            $translation = $content->translations()
                ->where('language_code', $lang)
                ->first();

            if ($translation) {

                $translation->update([
                    'title'
                    => $data['title'] ?? null,

                    'content'
                    => $data['content'] ?? null,
                ]);
            } else {

                $content->translations()->create([
                    'language_code' => $lang,

                    'title'
                    => $data['title'] ?? null,

                    'content'
                    => $data['content'] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Updated',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($topicId, $id)
    {
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

        $content->update([
            'status' => !$content->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',

            'data' => [
                'id' => $content->id,
                'status' => (bool) $content->status,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PUBLISH STATUS
    |--------------------------------------------------------------------------
    */

    public function updatePublishStatus(
        Request $request,
        $topicId,
        $id
    ) {

        if (!$this->isSystemUser()) {

            return response()->json([
                'success' => false,
                'message'
                => 'Only system users can change publish status'
            ], 403);
        }

        $validated = $request->validate([
            'publish_status' => [
                'required',
                'in:draft,published,unpublished'
            ]
        ]);

        $content = TopicContent::where(
            'topic_id',
            $topicId
        )->findOrFail($id);

        $content->update([
            'publish_status'
            => $validated['publish_status']
        ]);

        return response()->json([
            'success' => true,

            'data' => [
                'id' => $content->id,

                'publish_status'
                => $content->publish_status,
            ]
        ]);
    }
}
