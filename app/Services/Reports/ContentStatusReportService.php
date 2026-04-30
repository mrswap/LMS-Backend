<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use App\Models\TopicContent;

class ContentStatusReportService
{
    public function getReport(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = TopicContent::query()
            ->with([
                'topic:id,title,module_id,level_id,program_id,chapter_id',
                'topic.module:id,title',
                'topic.level:id,title',
                'topic.program:id,title',
                'topic.chapter:id,title',
                'translations:id,topic_content_id,language_code',
                'topic.creator:id,name'
            ]);

        /*
        |-----------------------------------------
        | 🔍 FILTERS
        |-----------------------------------------
        */

        if ($request->filled('program_id')) {
            $query->whereHas('topic', function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->filled('level_id')) {
            $query->whereHas('topic', function ($q) use ($request) {
                $q->where('level_id', $request->level_id);
            });
        }

        if ($request->filled('module_id')) {
            $query->whereHas('topic', function ($q) use ($request) {
                $q->where('module_id', $request->module_id);
            });
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /*
        |-----------------------------------------
        | 🔽 SORTING
        |-----------------------------------------
        */

        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        /*
        |-----------------------------------------
        | 📄 PAGINATION
        |-----------------------------------------
        */

        $results = $query->paginate($perPage);

        /*
        |-----------------------------------------
        | 🎯 TRANSFORM
        |-----------------------------------------
        */

        $results->getCollection()->transform(function ($item) {

            // language list
            $languages = $item->translations->pluck('language_code')->toArray();

            return [
                'program' => $item->topic?->program?->title,
                'level' => $item->topic?->level?->title,
                'module' => $item->topic?->module?->title,
                'chapter' => $item->topic?->chapter?->title,
                'topic' => $item->topic?->title,

                'lesson_name' => $item->title,

                'languages' => $languages,

                'content_status' => $item->status ? 'Published' : 'Draft',

                'uploaded_by' => $item->topic?->creator?->name,

                // placeholders (future upgrade)
                'approved_by' => null,
                'publish_date' => $item->created_at,
                'last_updated' => $item->updated_at,
            ];
        });

        return $results;
    }
}
