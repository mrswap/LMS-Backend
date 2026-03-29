<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Program;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/modules/';

    /*
    |--------------------------------------------------------------------------
    | LIST MODULES
    | GET /modules?level_id=1
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Module::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title'
        ]);

        // Apply filter only if level_id is provided
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

        $data = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE MODULE
    | POST /modules
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Check Program
        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        // Check Level
        $level = Level::find($validated['level_id']);
        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        // Check relation
        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
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

        $module = Module::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $module->load(['creator:id,name', 'program:id,title', 'level:id,title']);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data' => $module
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW MODULE
    | GET /modules/{id}
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $module = Module::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE MODULE
    | POST /modules/{id}
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'nullable|integer',
            'level_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Resolve program & level (new or old)
        $programId = $validated['program_id'] ?? $module->program_id;
        $levelId   = $validated['level_id'] ?? $module->level_id;

        // Check Program
        $program = Program::find($programId);
        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        // Check Level
        $level = Level::find($levelId);
        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Level not found'
            ], 404);
        }

        // Check relation
        if ($level->program_id != $program->id) {
            return response()->json([
                'success' => false,
                'message' => 'Level does not belong to selected program'
            ], 422);
        }

        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        // Replace image
        if ($request->hasFile('thumbnail')) {

            $oldPath = $module->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        // Force correct IDs (important)
        $validated['program_id'] = $programId;
        $validated['level_id'] = $levelId;

        $module->update($validated);

        $module->load(['creator:id,name', 'program:id,title', 'level:id,title']);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'data' => $module
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE MODULE
    | DELETE /modules/{id}
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
    | POST /modules/{id}/toggle-status
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
            'data' => $module
        ]);
    }
}
