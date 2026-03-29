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
    | INDEX (FILTER READY)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Topic::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title'
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

        /*
        |--------------------------------------------------------------------------
        | FILTER: CHAPTER
        |--------------------------------------------------------------------------
        */
        if ($request->filled('chapter_id')) {

            $chapter = Chapter::find($request->chapter_id);

            if (!$chapter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chapter not found'
                ], 404);
            }

            $query->where('chapter_id', $request->chapter_id);
        }

        $data = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration' => 'nullable|integer|min:1', // 🔥 NEW
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Fetch hierarchy
        $program = Program::find($validated['program_id']);
        $level   = Level::find($validated['level_id']);
        $module  = Module::find($validated['module_id']);
        $chapter = Chapter::find($validated['chapter_id']);

        if (!$program) return response()->json(['success' => false, 'message' => 'Program not found'], 404);
        if (!$level) return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        if (!$module) return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        if (!$chapter) return response()->json(['success' => false, 'message' => 'Chapter not found'], 404);

        // 🔥 FULL HIERARCHY VALIDATION
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

        if ($chapter->module_id != $module->id) {
            return response()->json([
                'success' => false,
                'message' => 'Chapter does not belong to selected module'
            ], 422);
        }

        // Upload
        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $topic = Topic::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $topic->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic created successfully',
            'data' => $topic
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $topic = Topic::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $topic
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);

        $validated = $request->validate([
            'program_id' => 'nullable|integer',
            'level_id' => 'nullable|integer',
            'module_id' => 'nullable|integer',
            'chapter_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration' => 'nullable|integer|min:1',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $programId = $validated['program_id'] ?? $topic->program_id;
        $levelId   = $validated['level_id'] ?? $topic->level_id;
        $moduleId  = $validated['module_id'] ?? $topic->module_id;
        $chapterId = $validated['chapter_id'] ?? $topic->chapter_id;

        $program = Program::find($programId);
        $level   = Level::find($levelId);
        $module  = Module::find($moduleId);
        $chapter = Chapter::find($chapterId);

        if (!$program || !$level || !$module || !$chapter) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy data'
            ], 404);
        }

        // Validate chain
        if (
            $level->program_id != $program->id ||
            $module->level_id != $level->id ||
            $chapter->module_id != $module->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy mapping'
            ], 422);
        }

        // Replace image
        if ($request->hasFile('thumbnail')) {

            $oldPath = $topic->getRawOriginal('thumbnail');

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
        $validated['chapter_id'] = $chapterId;

        $topic->update($validated);

        $topic->load([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic updated successfully',
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

        $oldPath = $topic->getRawOriginal('thumbnail');

        if ($oldPath && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
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
        $topic = Topic::findOrFail($id);

        $topic->update([
            'status' => !$topic->status
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic
        ]);
    }
}
