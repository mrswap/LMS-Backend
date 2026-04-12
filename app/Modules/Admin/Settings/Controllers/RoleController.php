<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;


class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::latest()->get());
    }

    public function store(Request $request)
    {
        $role = Role::create($request->only('name', 'label'));

        return response()->json($role, 201);
    }

    public function show($id)
    {
        return response()->json(Role::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'System role cannot be modified'], 422);
        }

        $role->update($request->only('name', 'label'));

        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $role->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function toggleStatus($id)
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'System role cannot be disabled'], 422);
        }

        $role->is_active = !$role->is_active;
        $role->save();

        return response()->json(['message' => 'Status updated']);
    }
}
