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
            ->where('status', true)
            ->with([

                'topic:id,title,module_id,level_id,program_id,chapter_id,created_by,status',

                'topic.module:id,title,status',

                'topic.level:id,title,status',

                'topic.program:id,title,status',

                'topic.chapter:id,title,status',

                'translations:id,topic_content_id,language_code',

                'topic.creator:id,name'
            ]);

        /*
        |--------------------------------------------------
        | 🔍 FILTERS
        |--------------------------------------------------
        */

        if ($request->filled('program_id')) {

            $query->whereHas('topic.program', function ($q) use ($request) {

                $q->where('id', $request->program_id)
                    ->where('status', true);
            });
        }

        if ($request->filled('level_id')) {

            $query->whereHas('topic.level', function ($q) use ($request) {

                $q->where('id', $request->level_id)
                    ->where('status', true);
            });
        }

        if ($request->filled('module_id')) {

            $query->whereHas('topic.module', function ($q) use ($request) {

                $q->where('id', $request->module_id)
                    ->where('status', true);
            });
        }

        if ($request->filled('chapter_id')) {

            $query->whereHas('topic.chapter', function ($q) use ($request) {

                $q->where('id', $request->chapter_id)
                    ->where('status', true);
            });
        }

        if ($request->filled('topic_id')) {

            $query->whereHas('topic', function ($q) use ($request) {

                $q->where('id', $request->topic_id)
                    ->where('status', true);
            });
        }

        if ($request->filled('status')) {

            $query->where(
                'publish_status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------
        | 🔽 SORTING
        |--------------------------------------------------
        */

        $sortBy = $request->get(
            'sort_by',
            'updated_at'
        );

        $sortOrder = $request->get(
            'sort_order',
            'desc'
        );

        $query->orderBy(
            $sortBy,
            $sortOrder
        );

        /*
        |--------------------------------------------------
        | 📄 PAGINATION
        |--------------------------------------------------
        */

        $results = $query->paginate($perPage);

        /*
        |--------------------------------------------------
        | 🎯 TRANSFORM
        |--------------------------------------------------
        */

        $results->getCollection()->transform(function (
            $item
        ) {

            /*
            |--------------------------------------------------
            | 🌐 LANGUAGES
            |--------------------------------------------------
            */

            $languages = $item->translations
                ->pluck('language_code')
                ->unique()
                ->values()
                ->toArray();

            return [

                /*
                |--------------------------------------------------
                | HIERARCHY
                |--------------------------------------------------
                */

                'program' => $item->topic?->program?->title,

                'level' => $item->topic?->level?->title,

                'module' => $item->topic?->module?->title,

                'chapter' => $item->topic?->chapter?->title,

                'topic' => $item->topic?->title,

                /*
                |--------------------------------------------------
                | CONTENT
                |--------------------------------------------------
                */

                'lesson_name' => $item->title,

                'languages' => $languages,

                /*
                |--------------------------------------------------
                | STATUS
                |--------------------------------------------------
                */

                'content_status' => match ($item->publish_status) {

                    'published' => 'Published',

                    'draft' => 'Draft',

                    'unpublished' => 'Unpublished',

                    default => 'Draft'
                },

                /*
                |--------------------------------------------------
                | USERS
                |--------------------------------------------------
                */

                'uploaded_by' => $item->topic?->creator?->name,

                /*
                |--------------------------------------------------
                | FUTURE PLACEHOLDERS
                |--------------------------------------------------
                */

                'approved_by' => null,

                /*
                |--------------------------------------------------
                | DATES
                |--------------------------------------------------
                */

                'publish_date' => $item->created_at,

                'last_updated' => $item->updated_at,
            ];
        });

        return $results;
    }
}
