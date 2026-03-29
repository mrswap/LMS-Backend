<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index(Request $request)
    {
        $data = Topic::where('chapter_id', $request->chapter_id)
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $topic = Topic::create([
            ...$request->validate([
                'program_id' => 'required|exists:programs,id',
                'level_id' => 'required|exists:levels,id',
                'module_id' => 'required|exists:modules,id',
                'chapter_id' => 'required|exists:chapters,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'thumbnail' => 'nullable|string',
            ]),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $topic], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => Topic::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);

        $topic->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string',
        ]));

        return response()->json(['success' => true, 'data' => $topic]);
    }

    public function destroy($id)
    {
        Topic::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function toggleStatus($id)
    {
        $topic = Topic::findOrFail($id);
        $topic->update(['status' => !$topic->status]);

        return response()->json(['success' => true, 'data' => $topic]);
    }
}