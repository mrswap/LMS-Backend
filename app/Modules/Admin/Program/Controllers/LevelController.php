<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LevelController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/levels/';

    /*
    |--------------------------------------------------------------------------
    | LIST LEVELS
    | GET /levels?program_id=1
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Level::with([
            'creator:id,name',
            'program:id,title'
        ]);

        // Optional filter
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

        $data = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE LEVEL
    | POST /levels
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Check Program exists
        $program = Program::find($request->program_id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found'
            ], 404);
        }

        // Ensure folder exists
        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        // Upload file
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $level = Level::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $level->load([
            'creator:id,name',
            'program:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Level created successfully',
            'data' => $level
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW LEVEL
    | GET /levels/{id}
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $level = Level::with([
            'creator:id,name',
            'program:id,title'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $level
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE LEVEL
    | POST /levels/{id}
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $level = Level::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Ensure folder exists
        if (!file_exists(public_path($this->uploadPath))) {
            mkdir(public_path($this->uploadPath), 0777, true);
        }

        // Replace image
        if ($request->hasFile('thumbnail')) {

            $oldPath = $level->getRawOriginal('thumbnail');

            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $level->update($validated);

        $level->load([
            'creator:id,name',
            'program:id,title'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Level updated successfully',
            'data' => $level
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE LEVEL
    | DELETE /levels/{id}
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $level = Level::findOrFail($id);

        $oldPath = $level->getRawOriginal('thumbnail');

        if ($oldPath && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    | POST /levels/{id}/toggle-status
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $level = Level::findOrFail($id);

        $level->update([
            'status' => !$level->status
        ]);

        return response()->json([
            'success' => true,
            'data' => $level
        ]);
    }
}