<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ChapterTranslation;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChapterController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/chapters/';

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

        $query = Chapter::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
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

        if ($request->filled('module_id')) {
            if (!Module::find($request->module_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }
            $query->where('module_id', $request->module_id);
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
                        ->orWhereHas('module', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('level', fn($q3) => $q3->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('program', fn($q4) => $q4->where('title', 'like', "%{$search}%"));
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

        $chapters = $query->paginate($limit);

        /*
        |-----------------------------
        | TRANSFORM
        |-----------------------------
        */
        $chapters->getCollection()->transform(function ($chapter) use ($lang) {

            if ($lang === 'en') {
                return [
                    'id' => $chapter->id,
                    'language_code' => 'en',
                    'title' => $chapter->title,
                    'description' => $chapter->description,
                    'thumbnail' => $chapter->thumbnail,
                    'status' => (bool) $chapter->status,
                    'program' => $chapter->program,
                    'level' => $chapter->level,
                    'module' => $chapter->module,
                    'creator' => $chapter->creator,
                    'created_at' => $chapter->created_at,
                ];
            }

            $translation = $chapter->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $chapter->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $chapter->thumbnail,
                'status' => (bool) $chapter->status,
                'program' => $chapter->program,
                'level' => $chapter->level,
                'module' => $chapter->module,
                'creator' => $chapter->creator,
                'created_at' => $chapter->created_at,
            ];
        });

        $chapters->setCollection(
            $chapters->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $chapters
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $program = Program::find($validated['program_id']);
        $level = Level::find($validated['level_id']);
        $module = Module::find($validated['module_id']);

        if (!$program) return response()->json(['success' => false, 'message' => 'Program not found'], 404);
        if (!$level) return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        if (!$module) return response()->json(['success' => false, 'message' => 'Module not found'], 404);

        if ($level->program_id != $program->id) {
            return response()->json(['success' => false, 'message' => 'Level does not belong to selected program'], 422);
        }

        if ($module->level_id != $level->id || $module->program_id != $program->id) {
            return response()->json(['success' => false, 'message' => 'Module does not belong to selected level/program'], 422);
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

            $chapter = Chapter::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        } else {

            $chapter = Chapter::create([
                'program_id' => $validated['program_id'],
                'level_id' => $validated['level_id'],
                'module_id' => $validated['module_id'],
                'title' => 'BASE_RECORD',
                'description' => null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'created_by' => auth()->id(),
            ]);

            ChapterTranslation::create([
                'chapter_id' => $chapter->id,
                'language_code' => $lang,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        $chapter->load(['creator:id,name', 'program:id,title', 'level:id,title', 'module:id,title']);

        return response()->json([
            'success' => true,
            'data' => $chapter
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

        $chapter = Chapter::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'translations'
        ])->findOrFail($id);

        if ($lang === 'en') {

            if ($chapter->title === 'BASE_RECORD') {
                return response()->json([
                    'success' => false,
                    'message' => 'English content not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $chapter->id,
                    'language_code' => 'en',
                    'title' => $chapter->title,
                    'description' => $chapter->description,
                    'thumbnail' => $chapter->thumbnail,
                    'status' => (bool) $chapter->status,
                    'program' => $chapter->program,
                    'level' => $chapter->level,
                    'module' => $chapter->module,
                    'creator' => $chapter->creator,
                ]
            ]);
        }

        $translation = $chapter->translations->where('language_code', $lang)->first();

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $chapter->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $chapter->thumbnail,
                'status' => (bool) $chapter->status,
                'program' => $chapter->program,
                'level' => $chapter->level,
                'module' => $chapter->module,
                'creator' => $chapter->creator,
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

        $chapter = Chapter::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'nullable|boolean',
        ]);

        // =============================
        // 🔗 HIERARCHY VALIDATION
        // =============================
        $program = Program::find($validated['program_id']);
        $level   = Level::find($validated['level_id']);
        $module  = Module::find($validated['module_id']);

        if (!$program || !$level || !$module) {
            return response()->json(['success' => false, 'message' => 'Invalid hierarchy'], 404);
        }

        if (
            $level->program_id != $program->id ||
            $module->level_id != $level->id
        ) {
            return response()->json(['success' => false, 'message' => 'Invalid hierarchy mapping'], 422);
        }

        // =============================
        // 🖼️ THUMBNAIL UPLOAD (with delete old)
        // =============================
        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            $oldPath = $chapter->getRawOriginal('thumbnail');

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
            $chapter->update([
                'program_id' => $validated['program_id'],
                'level_id'   => $validated['level_id'],
                'module_id'  => $validated['module_id'],
                'title'      => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail'  => $validated['thumbnail'] ?? $chapter->thumbnail,
                'status'     => $validated['status'] ?? $chapter->status,
            ]);
        } else {

            // 🔹 Core fields update
            $chapter->update([
                'program_id' => $validated['program_id'],
                'level_id'   => $validated['level_id'],
                'module_id'  => $validated['module_id'],
                'thumbnail'  => $validated['thumbnail'] ?? $chapter->thumbnail,
                'status'     => $validated['status'] ?? $chapter->status,
            ]);

            // 🔹 Translation update/create
            $chapter->translations()->updateOrCreate(
                ['language_code' => $lang],
                [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $chapter->fresh(['translations'])
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $chapter = Chapter::findOrFail($id);

        $oldPath = $chapter->getRawOriginal('thumbnail');

        if ($oldPath && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $chapter->delete();

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
        $chapter = Chapter::findOrFail($id);

        $chapter->update([
            'status' => !$chapter->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $chapter->id,
                'status' => (bool) $chapter->status
            ]
        ]);
    }
}
