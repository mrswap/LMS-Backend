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
    |--------------------------------------------------------------------------
    | FILTER: PROGRAM
    |--------------------------------------------------------------------------
    */
        if ($request->filled('program_id')) {

            $program = Program::find($request->program_id);

            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $query->where('program_id', $request->program_id);
        }

        /*
    |--------------------------------------------------------------------------
    | FILTER: LEVEL
    |--------------------------------------------------------------------------
    */
        if ($request->filled('level_id')) {

            $level = Level::find($request->level_id);

            if (!$level) {
                return response()->json([
                    'success' => false,
                    'message' => 'Level not found'
                ], 404);
            }

            $query->where('level_id', $request->level_id);
        }

        /*
    |--------------------------------------------------------------------------
    | SEARCH (MODULE + LEVEL + PROGRAM)
    |--------------------------------------------------------------------------
    */
        if ($request->filled('search')) {

            $search = $request->search;

            if ($lang === 'en') {

                $query->where(function ($q) use ($search) {

                    // Module title
                    $q->where('title', 'like', "%{$search}%")

                        // Level title
                        ->orWhereHas('level', function ($q2) use ($search) {
                            $q2->where('title', 'like', "%{$search}%");
                        })

                        // Program title
                        ->orWhereHas('program', function ($q3) use ($search) {
                            $q3->where('title', 'like', "%{$search}%");
                        });
                });
            } else {

                // 🔥 Translation-based search
                $query->whereHas('translations', function ($q) use ($lang, $search) {
                    $q->where('language_code', $lang)
                        ->where('title', 'like', "%{$search}%");
                });
            }
        }

        /*
    |--------------------------------------------------------------------------
    | HIDE BASE_RECORD (EN ONLY)
    |--------------------------------------------------------------------------
    */
        if ($lang === 'en' && !$request->filled('search')) {
            $query->where('title', '!=', 'BASE_RECORD');
        }

        $modules = $query->latest()->paginate(10);

        /*
    |--------------------------------------------------------------------------
    | TRANSFORM RESPONSE
    |--------------------------------------------------------------------------
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            $oldPath = $module->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $module->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? $module->thumbnail,
            ]);
        } else {

            $module->translations()->updateOrCreate(
                ['language_code' => $lang],
                [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ]
            );
        }

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
