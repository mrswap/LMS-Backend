<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TopicContent;
use App\Models\Media;
use Illuminate\Http\Request;
use App\Modules\Admin\ContentManagement\Requests\SectionContentRequest;

class SectionContentController extends Controller
{
    // CREATE
    public function store(SectionContentRequest $request, $topicId)
    {
        $lang = $request->header('Accept-Language', 'en');

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

    // LIST
    public function index($topicId)
    {
        return TopicContent::where('topic_id', $topicId)
            ->orderBy('order')
            ->get();
    }

    public function show($topicId, $id)
    {
        return TopicContent::where('topic_id', $topicId)
            ->with('translations')
            ->findOrFail($id);
    }

    // FULL RENDER (🔥 MAIN API)
    public function full(Request $request, $topicId)
    {
        $lang = $request->header('Accept-Language', 'en');

        $contents = TopicContent::where('topic_id', $topicId)
            ->where('status', true) // only active
            ->with('translations')
            ->orderBy('order')
            ->get();

        $data = $contents->map(function ($item) use ($lang) {

            // 🔹 Translation resolve
            $translation = $item->translations
                ->where('language_code', $lang)
                ->first();

            $title = $translation->title ?? $item->title;
            $content = $translation->content ?? $item->content;

            // ❌ SKIP BASE_RECORD
            if ($title === 'BASE_RECORD') {
                return null;
            }

            // 🔹 MEDIA
            if ($item->type === 'media') {
                $shortcode = $item->meta['shortcode'] ?? null;

                if (!$shortcode) {
                    return null;
                }

                $media = Media::where('shortcode', $shortcode)->first();

                // ❌ skip if media not found
                if (!$media) {
                    return null;
                }

                return [
                    'type' => 'media',
                    'title' => $title,
                    'data' => $media
                ];
            }

            // 🔹 TEXT
            if ($item->type === 'text') {

                // ❌ skip empty content
                if (!$content || trim(strip_tags($content)) === '') {
                    return null;
                }

                return [
                    'type' => 'text',
                    'title' => $title,
                    'content' => $content
                ];
            }

            // 🔹 fallback (future types)
            return null;
        })->filter()->values(); // 🔥 removes null + reindex

        return response()->json($data);
    }

    // UPDATE
    public function update(SectionContentRequest $request, $topicId, $id)
    {
        $lang = $request->header('Accept-Language', 'en');

        $content = TopicContent::where('topic_id', $topicId)->findOrFail($id);

        $data = $request->validated();

        if ($lang === 'en') {
            $content->update($data);
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
        }

        return response()->json(['message' => 'Updated']);
    }

    // DELETE
    public function destroy($topicId, $id)
    {
        $content = TopicContent::where('topic_id', $topicId)->findOrFail($id);
        $content->delete();

        return response()->json(['message' => 'Deleted']);
    }

    // REORDER
    public function reorder(Request $request, $topicId)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:topic_contents,id',
            'items.*.order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {

            TopicContent::where('topic_id', $topicId)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json([
            'message' => 'Order updated successfully'
        ]);
    }

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
