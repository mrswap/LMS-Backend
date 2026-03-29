<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/programs/';

    public function index()
    {
        $data = Program::with('creator:id,name')
            ->latest()
            ->paginate(10);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // File Upload
        if ($request->hasFile('thumbnail')) {

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $program = Program::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $program->load('creator:id,name');

        return response()->json([
            'success' => true,
            'data' => $program
        ], 201);
    }

    public function show($id)
    {
        $program = Program::with('creator:id,name')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $program]);
    }

    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {

            // Delete old file
            if ($program->thumbnail && file_exists(public_path($program->thumbnail))) {
                unlink(public_path($program->thumbnail));
            }

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $program->update($validated);
        $program->load('creator:id,name');
        
        return response()->json([
            'success' => true,
            'data' => $program
        ]);
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);

        // Delete file
        if ($program->thumbnail && file_exists(public_path($program->thumbnail))) {
            unlink(public_path($program->thumbnail));
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    public function toggleStatus($id)
    {
        $program = Program::findOrFail($id);

        $program->update(['status' => !$program->status]);

        return response()->json([
            'success' => true,
            'data' => $program
        ]);
    }
}
