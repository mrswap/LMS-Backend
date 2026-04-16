<?php

namespace App\Modules\Common\Options\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Designation;

class OptionController extends Controller
{
    /*
    |-----------------------------------------
    | ROLES
    |-----------------------------------------
    */
    public function roles(Request $request)
    {
        $status = $request->get('status', 1); // default active

        $query = Role::query()
            ->where('name', '!=', 'superadmin') // always hide superadmin
            ->select('id', 'name', 'label', 'is_active');

        if ($status !== 'all') {
            $query->where('is_active', (bool) $status);
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    /*
    |-----------------------------------------
    | DESIGNATIONS
    |-----------------------------------------
    */
    public function designations(Request $request)
    {
        $status = $request->get('status', 1); // default active

        $query = Designation::query()
            ->select('id', 'name', 'label', 'is_active');

        if ($status !== 'all') {
            $query->where('is_active', (bool) $status);
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }
}
