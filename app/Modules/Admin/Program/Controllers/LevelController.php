<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LevelController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/levels/';

    public function index(Request $request)
    {
        $data = Level::with('creator:id,name')
            ->where('program_id', $request->program_id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
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

        $level = Level::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $level->load('creator:id,name');

        return response()->json([
            'success' => true,
            'data' => $level
        ], 201);
    }

    public function show($id)
    {
        $level = Level::with('creator:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $level
        ]);
    }

    public function update(Request $request, $id)
    {
        $level = Level::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {

            // delete old
            if ($level->thumbnail && file_exists(public_path($level->getRawOriginal('thumbnail')))) {
                unlink(public_path($level->getRawOriginal('thumbnail')));
            }

            $file = $request->file('thumbnail');

            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        $level->update($validated);
        $level->load('creator:id,name');

        return response()->json([
            'success' => true,
            'data' => $level
        ]);
    }

    public function destroy($id)
    {
        $level = Level::findOrFail($id);

        if ($level->thumbnail && file_exists(public_path($level->getRawOriginal('thumbnail')))) {
            unlink(public_path($level->getRawOriginal('thumbnail')));
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

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