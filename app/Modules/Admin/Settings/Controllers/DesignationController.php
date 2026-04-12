<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Designation;

class DesignationController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Designation::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:designations,name',
            'label' => 'required'
        ]);

        $designation = Designation::create($request->all());

        return response()->json([
            'message' => 'Created',
            'data' => $designation
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Designation::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $designation = Designation::findOrFail($id);

        $designation->update($request->only('name', 'label'));

        return response()->json([
            'message' => 'Updated',
            'data' => $designation
        ]);
    }

    public function destroy($id)
    {
        $designation = Designation::findOrFail($id);

        if ($designation->users()->count()) {
            return response()->json([
                'message' => 'Cannot delete, assigned to users'
            ], 422);
        }

        $designation->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }

    public function toggleStatus($id)
    {
        $designation = Designation::findOrFail($id);

        $designation->is_active = !$designation->is_active;
        $designation->save();

        return response()->json([
            'status' => $designation->is_active
        ]);
    }
}
