<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $data = Module::where('level_id', $request->level_id)
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $module = Module::create([
            ...$request->validate([
                'program_id' => 'required|exists:programs,id',
                'level_id' => 'required|exists:levels,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'thumbnail' => 'nullable|string',
            ]),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $module], 201);
    }

    public function show($id)
    {
        return response()->json(['success' => true, 'data' => Module::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $module->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string',
        ]));

        return response()->json(['success' => true, 'data' => $module]);
    }

    public function destroy($id)
    {
        Module::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function toggleStatus($id)
    {
        $module = Module::findOrFail($id);
        $module->update(['status' => !$module->status]);

        return response()->json(['success' => true, 'data' => $module]);
    }
}