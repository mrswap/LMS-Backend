<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function index(Request $request)
    {
        $data = Chapter::where('module_id', $request->module_id)
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $chapter = Chapter::create([
            ...$request->validate([
                'program_id' => 'required|exists:programs,id',
                'level_id' => 'required|exists:levels,id',
                'module_id' => 'required|exists:modules,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'thumbnail' => 'nullable|string',
            ]),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $chapter], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => Chapter::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $chapter = Chapter::findOrFail($id);

        $chapter->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string',
        ]));

        return response()->json(['success' => true, 'data' => $chapter]);
    }

    public function destroy($id)
    {
        Chapter::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function toggleStatus($id)
    {
        $chapter = Chapter::findOrFail($id);
        $chapter->update(['status' => !$chapter->status]);

        return response()->json(['success' => true, 'data' => $chapter]);
    }
}
