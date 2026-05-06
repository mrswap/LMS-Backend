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

        $query = Module::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'translations'
        ]);

        /*
        |-----------------------------
        | FILTERS
        |-----------------------------
        */
        if ($request->filled('program_id')) {

            if (!Program::find($request->program_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('level_id')) {

            if (!Level::find($request->level_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Level not found'
                ], 404);
            }

            $query->where('level_id', $request->level_id);
        }

        /*
        |-----------------------------
        | SEARCH
        |-----------------------------
        */
        if ($request->filled('search')) {

            $search = $request->search;

            if ($lang === 'en') {

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('level', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('program', fn($q3) => $q3->where('title', 'like', "%{$search}%"));
                });
            } else {

                $query->whereHas('translations', function ($q) use ($lang, $search) {
                    $q->where('language_code', $lang)
                        ->where('title', 'like', "%{$search}%");
                });
            }
        }

        /*
        |-----------------------------
        | BASE RECORD FILTER
        |-----------------------------
        */
        if ($lang === 'en' && !$request->filled('search')) {
            $query->where('title', '!=', 'BASE_RECORD');
        }

        /*
        |-----------------------------
        | STATUS
        |-----------------------------
        */
        if ($request->has('status')) {
            if ($request->status !== 'all') {
                $query->where('status', (bool) $request->status);
            }
        } else {
            $query->where('status', true);
        }

        /*
        |-----------------------------
        | SORTING
        |-----------------------------
        */
        $sortByMap = [
            'createdAt' => 'created_at',
            'title'     => 'title',
        ];

        $sortBy = $request->get('sortBy', 'createdAt');
        $order  = strtolower($request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = $sortByMap[$sortBy] ?? 'created_at';

        $query->orderBy($sortColumn, $order);

        /*
        |-----------------------------
        | PAGINATION
        |-----------------------------
        */
        $limit = (int) $request->get('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        $modules = $query->paginate($limit);

        /*
        |-----------------------------
        | TRANSFORM
        |-----------------------------
        */
        $modules->getCollection()->transform(function ($module) use ($lang) {

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

            if (!$translation) return null;

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
        });

        $modules->setCollection(
            $modules->getCollection()->filter()->values()
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

        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json(['success' => false, 'message' => 'Program not found'], 404);
        }

        $level = Level::find($validated['level_id']);
        if (!$level) {
            return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        }

        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
            ], 422);
        }

        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);
            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $module = Module::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        } else {

            $module = Module::create([
                'program_id' => $validated['program_id'],
                'level_id' => $validated['level_id'],
                'title' => 'BASE_RECORD',
                'description' => null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'created_by' => auth()->id(),
            ]);

            ModuleTranslation::create([
                'module_id' => $module->id,
                'language_code' => $lang,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        $module->load(['creator:id,name', 'program:id,title', 'level:id,title']);

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

        $module = Module::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'nullable|boolean',
        ]);

        // =============================
        // 🔗 HIERARCHY VALIDATION
        // =============================
        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json(['success' => false, 'message' => 'Program not found'], 404);
        }

        $level = Level::find($validated['level_id']);
        if (!$level) {
            return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        }

        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
            ], 422);
        }

        // =============================
        // 🖼️ THUMBNAIL UPLOAD (delete old)
        // =============================
        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            $oldPath = $module->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            if (!file_exists(public_path($this->uploadPath))) {
                mkdir(public_path($this->uploadPath), 0777, true);
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        // =============================
        // 🌐 LANGUAGE LOGIC
        // =============================
        if ($lang === 'en') {

            // Direct update (store jaisa)
            $module->update([
                'program_id' => $validated['program_id'],
                'level_id'   => $validated['level_id'],
                'title'      => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail'  => $validated['thumbnail'] ?? $module->thumbnail,
                'status'     => $validated['status'] ?? $module->status,
            ]);
        } else {

            // 🔹 Core fields update
            $module->update([
                'program_id' => $validated['program_id'],
                'level_id'   => $validated['level_id'],
                'thumbnail'  => $validated['thumbnail'] ?? $module->thumbnail,
                'status'     => $validated['status'] ?? $module->status,
            ]);

            // 🔹 Translation update/create
            $module->translations()->updateOrCreate(
                ['language_code' => $lang],
                [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ]
            );
        }

        $module->load(['creator:id,name', 'program:id,title', 'level:id,title', 'translations']);

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

        $oldPath = $module->getRawOriginal('thumbnail');

        if ($oldPath && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
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
        $module = Module::findOrFail($id);

        $module->update([
            'status' => !$module->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $module->id,
                'status' => (bool) $module->status
            ]
        ]);
    }
}
