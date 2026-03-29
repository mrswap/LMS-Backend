<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChapterController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/chapters/';

    /*
    |--------------------------------------------------------------------------
    | LIST CHAPTERS
    | GET /chapters?module_id=1
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Chapter::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title'
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
    | FILTER: MODULE
    |--------------------------------------------------------------------------
    */
        if ($request->filled('module_id')) {

            $module = Module::find($request->module_id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            $query->where('module_id', $request->module_id);
        }

        $data = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CHAPTER
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Program check
        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        // Level check
        $level = Level::find($validated['level_id']);
        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        // Module check
        $module = Module::find($validated['module_id']);
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        // 🔥 Hierarchy validation (VERY IMPORTANT)
        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
            ], 422);
        }

        if ($module->level_id != $level->id || $module->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module does not belong to selected level/program'
            ], 422);
        }

        // Folder
        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        // Upload
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $chapter = Chapter::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $chapter->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chapter created successfully',
            'data' => $chapter
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $chapter = Chapter::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $chapter
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $chapter = Chapter::findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'nullable|integer',
            'level_id' => 'nullable|integer',
            'module_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Resolve IDs
        $programId = $validated['program_id'] ?? $chapter->program_id;
        $levelId   = $validated['level_id'] ?? $chapter->level_id;
        $moduleId  = $validated['module_id'] ?? $chapter->module_id;

        $program = Program::find($programId);
        $level   = Level::find($levelId);
        $module  = Module::find($moduleId);

        if (!$program) return response()->json(['success' => false, 'message' => 'Program not found'], 404);
        if (!$level) return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        if (!$module) return response()->json(['success' => false, 'message' => 'Module not found'], 404);

        // Hierarchy validation
        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
            ], 422);
        }

        if ($module->level_id != $level->id || $module->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module does not belong to selected level/program'
            ], 422);
        }

        // Upload replace
        if ($request->hasFile('thumbnail')) {

            $oldPath = $chapter->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $validated['program_id'] = $programId;
        $validated['level_id'] = $levelId;
        $validated['module_id'] = $moduleId;

        $chapter->update($validated);

        $chapter->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chapter updated successfully',
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
            'data' => $chapter
        ]);
    }
}
