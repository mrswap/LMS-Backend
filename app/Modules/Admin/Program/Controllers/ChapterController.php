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
        ])->when($lang === 'en', function ($q) {
            $q->where('title', '!=', 'BASE_RECORD');
        });

        if ($request->filled('program_id')) {
            $program = Program::find($request->program_id);
            if (!$program) return response()->json(['success' => false, 'message' => 'Program not found'], 404);
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('level_id')) {
            $level = Level::find($request->level_id);
            if (!$level) return response()->json(['success' => false, 'message' => 'Level not found'], 404);
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('module_id')) {
            $module = Module::find($request->module_id);
            if (!$module) return response()->json(['success' => false, 'message' => 'Module not found'], 404);
            $query->where('module_id', $request->module_id);
        }

        $chapters = $query->latest()->paginate(10);

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

            $translation = $chapter->translations->where('language_code', $lang)->first();
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            $oldPath = $chapter->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $chapter->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? $chapter->thumbnail,
            ]);

        } else {

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
            'data' => $chapter
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