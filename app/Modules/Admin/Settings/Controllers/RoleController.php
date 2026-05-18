<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Role;

class RoleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = Role::query()
            ->withCount('permissions')
            ->latest();

        /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }

        /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

        if ($request->has('status')) {

            if ($request->status !== 'all') {

                $query->where(
                    'is_active',
                    (int) $request->status
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Admin Filter
        |--------------------------------------------------------------------------
        */

        if ($request->has('is_admin')) {

            $isAdmin = (int) $request->is_admin;

            if ($isAdmin === 1) {

                // admin/system roles
                $query->where('is_system', 1);
            } elseif ($isAdmin === 0) {

                // non-admin roles
                $query->where('is_system', 0);
            }
        }

        $roles = $query->get()->map(function ($role) {

            return [

                'id' => $role->id,

                'name' => $role->name,

                'label' => $role->label,

                'is_system' => (bool) $role->is_system,

                'is_active' => (bool) $role->is_active,

                'permissions_count' => $role->permissions_count,

                // ✅ custom flag
                'hidden' => $role->id == 4 && $role->name === 'sales',

                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Roles fetched successfully',
            'data' => $roles
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
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:roles,name'
            ],
            'label' => [
                'required',
                'string',
                'max:100'
            ],
            'permissions' => [
                'nullable',
                'array'
            ],
            'permissions.*' => [
                'exists:permissions,id'
            ]
        ]);

        $role = Role::create([
            'name' => strtolower($validated['name']),
            'label' => $validated['label'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sync Permissions
        |--------------------------------------------------------------------------
        */

        $role->permissions()->sync(
            $validated['permissions'] ?? []
        );

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $role = Role::with('permissions')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Block System Role Update
        |--------------------------------------------------------------------------
        */

        if ($role->is_system) {

            return response()->json([
                'success' => false,
                'message' => 'System role cannot be modified'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id)
            ],
            'label' => [
                'required',
                'string',
                'max:100'
            ],
            'permissions' => [
                'nullable',
                'array'
            ],
            'permissions.*' => [
                'exists:permissions,id'
            ]
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Role
        |--------------------------------------------------------------------------
        */

        $role->update([
            'name' => strtolower($validated['name']),
            'label' => $validated['label'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sync Permissions
        |--------------------------------------------------------------------------
        */

        $role->permissions()->sync(
            $validated['permissions'] ?? []
        );

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($id)
    {
        $role = Role::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Block System Role Disable
        |--------------------------------------------------------------------------
        */

        if ($role->is_system) {

            return response()->json([
                'success' => false,
                'message' => 'System role cannot be disabled'
            ], 422);
        }

        $role->is_active = !$role->is_active;

        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'status' => $role->is_active
        ]);
    }
}
