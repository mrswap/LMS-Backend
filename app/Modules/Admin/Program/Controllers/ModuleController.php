<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleTranslation;
use App\Models\Program;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/modules/';

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
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $user = auth()->user();

        $user->loadMissing('role');

        $isSystemUser = (bool) $user?->role?->is_system;

        $query = Module::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
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
                    ->orWhereHas('level', function ($q2) use ($search) {

                        $q2->where(
                            'title',
                            'like',
                            "%{$search}%"
                        );
                    })
                    ->orWhereHas('program', function ($q3) use ($search) {

                        $q3->where(
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

        if ($lang === 'en' && !$request->filled('search')) {

            $query->where(
                'title',
                '!=',
                'BASE_RECORD'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER FILTERS
        |--------------------------------------------------------------------------
        */

        if ($isSystemUser) {

            /*
            |--------------------------------------------------------------------------
            | PUBLISH STATUS
            |--------------------------------------------------------------------------
            */

            if ($request->has('publish_status')) {

                if ($request->publish_status !== 'all') {

                    $query->where(
                        'publish_status',
                        $request->publish_status
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            if ($request->has('status')) {

                if ($request->status !== 'all') {

                    $query->where(
                        'status',
                        (bool) $request->status
                    );
                }
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | NON SYSTEM USER RESTRICTIONS
            |--------------------------------------------------------------------------
            */

            $query->where('status', true);

            $query->where(
                'publish_status',
                Module::PUBLISH_PUBLISHED
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

        $modules = $query->paginate($limit);

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM
        |--------------------------------------------------------------------------
        */

        $modules->getCollection()->transform(
            function ($module) use ($lang) {

                if ($lang === 'en') {

                    return [
                        'id' => $module->id,
                        'language_code' => 'en',
                        'title' => $module->title,
                        'description' => $module->description,
                        'thumbnail' => $module->thumbnail,
                        'status' => (bool) $module->status,
                        'publish_status' => $module->publish_status,
                        'program' => $module->program,
                        'level' => $module->level,
                        'creator' => $module->creator,
                        'created_at' => $module->created_at,
                    ];
                }

                $translation = $module->translations
                    ->where('language_code', $lang)
                    ->first();

                if (!$translation) {
                    return null;
                }

                return [
                    'id' => $module->id,
                    'translation_id' => $translation->id,
                    'language_code' => $lang,
                    'title' => $translation->title,
                    'description' => $translation->description,
                    'thumbnail' => $module->thumbnail,
                    'status' => (bool) $module->status,
                    'publish_status' => $module->publish_status,
                    'program' => $module->program,
                    'level' => $module->level,
                    'creator' => $module->creator,
                    'created_at' => $module->created_at,
                ];
            }
        );

        $modules->setCollection(
            $modules->getCollection()
                ->filter()
                ->values()
        );

        return response()->json([
            'success' => true,
            'data' => $modules
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        /*
        |--------------------------------------------------------------------------
        | USER GOVERNANCE
        |--------------------------------------------------------------------------
        */

        $user = auth()->user();

        $user->loadMissing('role');

        $isSystemUser = (bool) $user?->role?->is_system;

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY VALIDATION
        |--------------------------------------------------------------------------
        */

        $program = Program::find(
            $validated['program_id']
        );

        if (!$program) {

            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        $level = Level::find(
            $validated['level_id']
        );

        if (!$level) {

            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        if ($level->program_id != $program->id) {

            return response()->json([
                'success' => false,
                'message'
                    => 'Level does not belong to selected program'
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
        | CREATE
        |--------------------------------------------------------------------------
        */

        if ($lang === 'en') {

            $module = Module::create([
                ...$validated,

                'created_by' => auth()->id(),

                'status' => $isSystemUser
                    ? true
                    : false,

                'publish_status' => $isSystemUser
                    ? Module::PUBLISH_PUBLISHED
                    : Module::PUBLISH_DRAFT,
            ]);

        } else {

            $module = Module::create([
                'program_id'
                    => $validated['program_id'],

                'level_id'
                    => $validated['level_id'],

                'title' => 'BASE_RECORD',

                'description' => null,

                'thumbnail'
                    => $validated['thumbnail'] ?? null,

                'created_by' => auth()->id(),

                'status' => $isSystemUser
                    ? true
                    : false,

                'publish_status' => $isSystemUser
                    ? Module::PUBLISH_PUBLISHED
                    : Module::PUBLISH_DRAFT,
            ]);

            $module->translations()->create([
                'language_code' => $lang,
                'title' => $validated['title'],
                'description'
                    => $validated['description'] ?? null,
            ]);
        }

        $module->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'translations'
        ]);

        return response()->json([
            'success' => true,
            'data' => $module
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

        $module = Module::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'translations'
        ])->findOrFail($id);

        if ($lang === 'en') {

            if ($module->title === 'BASE_RECORD') {

                return response()->json([
                    'success' => false,
                    'message' => 'English content not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $module->id,
                    'language_code' => 'en',
                    'title' => $module->title,
                    'description' => $module->description,
                    'thumbnail' => $module->thumbnail,
                    'status' => (bool) $module->status,
                    'publish_status' => $module->publish_status,
                    'program' => $module->program,
                    'level' => $module->level,
                    'creator' => $module->creator,
                ]
            ]);
        }

        $translation = $module->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {

            return response()->json([
                'success' => false,
                'message' => 'Translation not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $module->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $module->thumbnail,
                'status' => (bool) $module->status,
                'publish_status' => $module->publish_status,
                'program' => $module->program,
                'level' => $module->level,
                'creator' => $module->creator,
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

        $module = Module::with('translations')
            ->findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        /*
        |--------------------------------------------------------------------------
        | USER GOVERNANCE
        |--------------------------------------------------------------------------
        */

        $user = auth()->user();

        $user->loadMissing('role');

        $isSystemUser = (bool) $user?->role?->is_system;

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY VALIDATION
        |--------------------------------------------------------------------------
        */

        $program = Program::find(
            $validated['program_id']
        );

        if (!$program) {

            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        $level = Level::find(
            $validated['level_id']
        );

        if (!$level) {

            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        if ($level->program_id != $program->id) {

            return response()->json([
                'success' => false,
                'message'
                    => 'Level does not belong to selected program'
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

            $oldPath = $module->getRawOriginal(
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

            'thumbnail'
                => $validated['thumbnail']
                ?? $module->getRawOriginal('thumbnail'),
        ];

        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER CONTROLS
        |--------------------------------------------------------------------------
        */

        if ($isSystemUser) {

            if ($request->has('status')) {

                $updateData['status']
                    = (bool) $request->status;
            }

            if ($request->filled('publish_status')) {

                $allowedStatuses = [
                    Module::PUBLISH_DRAFT,
                    Module::PUBLISH_PUBLISHED,
                    Module::PUBLISH_UNPUBLISHED,
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

            $module->update($updateData);

        } else {

            $module->update($updateData);

            $module->translations()->updateOrCreate(
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

        $module->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'translations'
        ]);

        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $module = Module::findOrFail($id);

        $thumbnail = $module->getRawOriginal(
            'thumbnail'
        );

        if (
            $thumbnail
            && file_exists(public_path($thumbnail))
        ) {

            unlink(public_path($thumbnail));
        }

        $module->delete();

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
        $user = auth()->user();

        $user->loadMissing('role');

        if (!(bool) $user?->role?->is_system) {

            return response()->json([
                'success' => false,
                'message'
                    => 'Only system users can change status'
            ], 403);
        }

        $module = Module::findOrFail($id);

        $module->update([
            'status' => !$module->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $module->id,
                'status' => (bool) $module->status,
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

        $user = auth()->user();

        $user->loadMissing('role');

        if (!(bool) $user?->role?->is_system) {

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

        $module = Module::findOrFail($id);

        $module->update([
            'publish_status'
                => $validated['publish_status']
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $module->id,
                'publish_status'
                    => $module->publish_status
            ]
        ]);
    }
}