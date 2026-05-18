<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/topics/';

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
        return auth()->user()?->isSystemUser() ?? false;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $isSystemUser = $this->isSystemUser();

        $query = Topic::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
            'translations'
        ]);

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        if ($request->filled('program_id')) {

            if (!Program::find($request->program_id)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $query->where(
                'program_id',
                $request->program_id
            );
        }

        if ($request->filled('level_id')) {

            if (!Level::find($request->level_id)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Level not found'
                ], 404);
            }

            $query->where(
                'level_id',
                $request->level_id
            );
        }

        if ($request->filled('module_id')) {

            if (!Module::find($request->module_id)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            $query->where(
                'module_id',
                $request->module_id
            );
        }

        if ($request->filled('chapter_id')) {

            if (!Chapter::find($request->chapter_id)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Chapter not found'
                ], 404);
            }

            $query->where(
                'chapter_id',
                $request->chapter_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = $request->search;

            if ($lang === 'en') {

                $query->where(function ($q) use ($search) {

                    $q->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhereHas('chapter', function ($q2) use ($search) {

                            $q2->where(
                                'title',
                                'like',
                                "%{$search}%"
                            );
                        })
                        ->orWhereHas('module', function ($q3) use ($search) {

                            $q3->where(
                                'title',
                                'like',
                                "%{$search}%"
                            );
                        })
                        ->orWhereHas('level', function ($q4) use ($search) {

                            $q4->where(
                                'title',
                                'like',
                                "%{$search}%"
                            );
                        })
                        ->orWhereHas('program', function ($q5) use ($search) {

                            $q5->where(
                                'title',
                                'like',
                                "%{$search}%"
                            );
                        });
                });
            } else {

                $query->whereHas(
                    'translations',
                    function ($q) use ($lang, $search) {

                        $q->where(
                            'language_code',
                            $lang
                        )->where(
                            'title',
                            'like',
                            "%{$search}%"
                        );
                    }
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | BASE RECORD FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $lang === 'en'
            && !$request->filled('search')
        ) {

            $query->where(
                'title',
                '!=',
                'BASE_RECORD'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

        $sortByMap = [
            'createdAt' => 'created_at',
            'title' => 'title',
            'duration' => 'estimated_duration',
        ];

        $sortBy = $request->get(
            'sortBy',
            'createdAt'
        );

        $order = strtolower(
            $request->get('order', 'desc')
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $sortColumn = $sortByMap[$sortBy]
            ?? 'created_at';

        $query->orderBy($sortColumn, $order);

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $limit = (int) $request->get('limit', 10);

        $limit = ($limit > 0 && $limit <= 100)
            ? $limit
            : 10;

        $topics = $query->paginate($limit);

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM
        |--------------------------------------------------------------------------
        */

        $topics->getCollection()->transform(
            function ($topic) use ($lang) {

                if ($lang === 'en') {

                    return [
                        'id' => $topic->id,
                        'language_code' => 'en',
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'thumbnail' => $topic->thumbnail,
                        'estimated_duration'
                        => $topic->estimated_duration,
                        'status' => (bool) $topic->status,
                        'publish_status'
                        => $topic->publish_status,
                        'program' => $topic->program,
                        'level' => $topic->level,
                        'module' => $topic->module,
                        'chapter' => $topic->chapter,
                        'creator' => $topic->creator,
                        'created_at' => $topic->created_at,
                    ];
                }

                $translation = $topic->translations
                    ->where('language_code', $lang)
                    ->first();

                if (!$translation) {
                    return null;
                }

                return [
                    'id' => $topic->id,
                    'translation_id' => $translation->id,
                    'language_code' => $lang,
                    'title' => $translation->title,
                    'description'
                    => $translation->description,
                    'thumbnail' => $topic->thumbnail,
                    'estimated_duration'
                    => $topic->estimated_duration,
                    'status' => (bool) $topic->status,
                    'publish_status'
                    => $topic->publish_status,
                    'program' => $topic->program,
                    'level' => $topic->level,
                    'module' => $topic->module,
                    'chapter' => $topic->chapter,
                    'creator' => $topic->creator,
                    'created_at' => $topic->created_at,
                ];
            }
        );

        $topics->setCollection(
            $topics->getCollection()
                ->filter()
                ->values()
        );

        return response()->json([
            'success' => true,
            'data' => $topics
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration'
            => 'nullable|integer|min:1',
            'thumbnail'
            => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $isSystemUser = $this->isSystemUser();

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY VALIDATION
        |--------------------------------------------------------------------------
        */

        $program = Program::find(
            $validated['program_id']
        );

        $level = Level::find(
            $validated['level_id']
        );

        $module = Module::find(
            $validated['module_id']
        );

        $chapter = Chapter::find(
            $validated['chapter_id']
        );

        if (!$program) {

            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        if (!$level) {

            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        if (!$module) {

            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        if (!$chapter) {

            return response()->json([
                'success' => false,
                'message' => 'Chapter not found'
            ], 404);
        }

        if ($level->program_id != $program->id) {

            return response()->json([
                'success' => false,
                'message'
                => 'Level does not belong to selected program'
            ], 422);
        }

        if (
            $module->level_id != $level->id
            || $module->program_id != $program->id
        ) {

            return response()->json([
                'success' => false,
                'message'
                => 'Module does not belong to selected level/program'
            ], 422);
        }

        if (
            $chapter->module_id != $module->id
            || $chapter->level_id != $level->id
            || $chapter->program_id != $program->id
        ) {

            return response()->json([
                'success' => false,
                'message'
                => 'Chapter does not belong to selected hierarchy'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | THUMBNAIL UPLOAD
        |--------------------------------------------------------------------------
        */

        if (
            $request->hasFile('thumbnail')
            && $request->file('thumbnail')->isValid()
        ) {

            if (!file_exists(public_path($this->uploadPath))) {

                mkdir(
                    public_path($this->uploadPath),
                    0777,
                    true
                );
            }

            $file = $request->file('thumbnail');

            $filename = time()
                . '_'
                . Str::random(10)
                . '.'
                . $file->getClientOriginalExtension();

            $file->move(
                public_path($this->uploadPath),
                $filename
            );

            $validated['thumbnail']
                = $this->uploadPath . $filename;
        }

        /*
        |--------------------------------------------------------------------------
        | GOVERNANCE DEFAULTS
        |--------------------------------------------------------------------------
        */

        $defaultStatus = $isSystemUser
            ? true
            : false;

        $defaultPublishStatus = $isSystemUser
            ? Topic::PUBLISH_PUBLISHED
            : Topic::PUBLISH_DRAFT;

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        if ($lang === 'en') {

            $topic = Topic::create([
                ...$validated,

                'created_by' => auth()->id(),

                'status' => $defaultStatus,

                'publish_status'
                => $defaultPublishStatus,
            ]);
        } else {

            $topic = Topic::create([
                'program_id'
                => $validated['program_id'],

                'level_id'
                => $validated['level_id'],

                'module_id'
                => $validated['module_id'],

                'chapter_id'
                => $validated['chapter_id'],

                'title' => 'BASE_RECORD',

                'description' => null,

                'thumbnail'
                => $validated['thumbnail'] ?? null,

                'estimated_duration'
                => $validated['estimated_duration']
                    ?? null,

                'created_by' => auth()->id(),

                'status' => $defaultStatus,

                'publish_status'
                => $defaultPublishStatus,
            ]);

            $topic->translations()->create([
                'language_code' => $lang,
                'title' => $validated['title'],
                'description'
                => $validated['description'] ?? null,
            ]);
        }

        $topic->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
            'translations'
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $topic = Topic::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
            'translations'
        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | NON SYSTEM USER RESTRICTION
        |--------------------------------------------------------------------------
        */

        if (
            !$this->isSystemUser()
            && (
                !$topic->status
                || $topic->publish_status
                !== Topic::PUBLISH_PUBLISHED
            )
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Topic not available'
            ], 404);
        }

        if ($lang === 'en') {

            if ($topic->title === 'BASE_RECORD') {

                return response()->json([
                    'success' => false,
                    'message'
                    => 'English content not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $topic->id,
                    'language_code' => 'en',
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'thumbnail' => $topic->thumbnail,
                    'estimated_duration'
                    => $topic->estimated_duration,
                    'status' => (bool) $topic->status,
                    'publish_status'
                    => $topic->publish_status,
                    'program' => $topic->program,
                    'level' => $topic->level,
                    'module' => $topic->module,
                    'chapter' => $topic->chapter,
                    'creator' => $topic->creator,
                ]
            ]);
        }

        $translation = $topic->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {

            return response()->json([
                'success' => false,
                'message'
                => 'Translation not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $topic->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description'
                => $translation->description,
                'thumbnail' => $topic->thumbnail,
                'estimated_duration'
                => $topic->estimated_duration,
                'status' => (bool) $topic->status,
                'publish_status'
                => $topic->publish_status,
                'program' => $topic->program,
                'level' => $topic->level,
                'module' => $topic->module,
                'chapter' => $topic->chapter,
                'creator' => $topic->creator,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $topic = Topic::with('translations')
            ->findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration'
            => 'nullable|integer|min:1',
            'thumbnail'
            => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $isSystemUser = $this->isSystemUser();

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY VALIDATION
        |--------------------------------------------------------------------------
        */

        $program = Program::find(
            $validated['program_id']
        );

        $level = Level::find(
            $validated['level_id']
        );

        $module = Module::find(
            $validated['module_id']
        );

        $chapter = Chapter::find(
            $validated['chapter_id']
        );

        if (!$program) {

            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        if (!$level) {

            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        if (!$module) {

            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        if (!$chapter) {

            return response()->json([
                'success' => false,
                'message' => 'Chapter not found'
            ], 404);
        }

        if ($level->program_id != $program->id) {

            return response()->json([
                'success' => false,
                'message'
                => 'Level does not belong to selected program'
            ], 422);
        }

        if (
            $module->level_id != $level->id
            || $module->program_id != $program->id
        ) {

            return response()->json([
                'success' => false,
                'message'
                => 'Module does not belong to selected level/program'
            ], 422);
        }

        if (
            $chapter->module_id != $module->id
            || $chapter->level_id != $level->id
            || $chapter->program_id != $program->id
        ) {

            return response()->json([
                'success' => false,
                'message'
                => 'Chapter does not belong to selected hierarchy'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | THUMBNAIL UPLOAD
        |--------------------------------------------------------------------------
        */

        if (
            $request->hasFile('thumbnail')
            && $request->file('thumbnail')->isValid()
        ) {

            $oldPath = $topic->getRawOriginal(
                'thumbnail'
            );

            if (
                $oldPath
                && file_exists(public_path($oldPath))
            ) {

                unlink(public_path($oldPath));
            }

            if (!file_exists(public_path($this->uploadPath))) {

                mkdir(
                    public_path($this->uploadPath),
                    0777,
                    true
                );
            }

            $file = $request->file('thumbnail');

            $filename = time()
                . '_'
                . Str::random(10)
                . '.'
                . $file->getClientOriginalExtension();

            $file->move(
                public_path($this->uploadPath),
                $filename
            );

            $validated['thumbnail']
                = $this->uploadPath . $filename;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE DATA
        |--------------------------------------------------------------------------
        */

        $updateData = [
            'program_id'
            => $validated['program_id'],

            'level_id'
            => $validated['level_id'],

            'module_id'
            => $validated['module_id'],

            'chapter_id'
            => $validated['chapter_id'],

            'estimated_duration'
            => $validated['estimated_duration']
                ?? $topic->estimated_duration,

            'thumbnail'
            => $validated['thumbnail']
                ?? $topic->getRawOriginal(
                    'thumbnail'
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER CONTROLS
        |--------------------------------------------------------------------------
        */

        if ($isSystemUser) {

            if ($request->has('status')) {

                $updateData['status']
                    = filter_var(
                        $request->status,
                        FILTER_VALIDATE_BOOLEAN
                    );
            }

            if ($request->filled('publish_status')) {

                $allowedStatuses = [
                    Topic::PUBLISH_DRAFT,
                    Topic::PUBLISH_PUBLISHED,
                    Topic::PUBLISH_UNPUBLISHED,
                ];

                if (
                    in_array(
                        $request->publish_status,
                        $allowedStatuses
                    )
                ) {

                    $updateData['publish_status']
                        = $request->publish_status;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | LANGUAGE LOGIC
        |--------------------------------------------------------------------------
        */

        if ($lang === 'en') {

            $updateData['title']
                = $validated['title'];

            $updateData['description']
                = $validated['description'] ?? null;

            $topic->update($updateData);
        } else {

            $topic->update($updateData);

            $topic->translations()->updateOrCreate(
                [
                    'language_code' => $lang
                ],
                [
                    'title' => $validated['title'],

                    'description'
                    => $validated['description']
                        ?? null,
                ]
            );
        }

        $topic->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
            'translations'
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $topic = Topic::findOrFail($id);

        $thumbnail = $topic->getRawOriginal(
            'thumbnail'
        );

        if (
            $thumbnail
            && file_exists(public_path($thumbnail))
        ) {

            unlink(public_path($thumbnail));
        }

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($id)
    {
        if (!$this->isSystemUser()) {

            return response()->json([
                'success' => false,
                'message'
                => 'Only system users can change status'
            ], 403);
        }

        $topic = Topic::findOrFail($id);

        $topic->update([
            'status' => !$topic->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $topic->id,
                'status' => (bool) $topic->status,
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

        $topic = Topic::findOrFail($id);

        /*
    |--------------------------------------------------------------------------
    | GOVERNANCE RULES
    |--------------------------------------------------------------------------
    */

        $updateData = [
            'publish_status'
            => $validated['publish_status']
        ];

        /*
    |--------------------------------------------------------------------------
    | AUTO STATUS HANDLING
    |--------------------------------------------------------------------------
    */

        if (
            $validated['publish_status']
            === Topic::PUBLISH_PUBLISHED
        ) {

            $updateData['status'] = true;
        }

        if (
            $validated['publish_status']
            === Topic::PUBLISH_UNPUBLISHED
        ) {

            $updateData['status'] = false;
        }

        $topic->update($updateData);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $topic->id,

                'status'
                => (bool) $topic->status,

                'publish_status'
                => $topic->publish_status
            ]
        ]);
    }
}
