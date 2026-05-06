<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::select(
            'id',
            'module',
            'name',
            'label'
        )
            ->orderBy('module')
            ->get()
            ->groupBy('module')
            ->map(function ($items, $module) {

                return [
                    'module' => $module,
                    'permissions' => $items->values()
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'data' => $permissions
        ]);
    }
}
