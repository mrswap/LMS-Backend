<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Unauthenticated
        |--------------------------------------------------------------------------
        */

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Inactive User
        |--------------------------------------------------------------------------
        */

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account inactive'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Load Role + Permissions
        |--------------------------------------------------------------------------
        */

        $user->loadMissing('role.permissions');

        /*
        |--------------------------------------------------------------------------
        | SUPERADMIN BYPASS
        |--------------------------------------------------------------------------
        */

        if ($user->role?->name === 'superadmin') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | No Role
        |--------------------------------------------------------------------------
        */

        if (!$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not assigned'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Role Disabled
        |--------------------------------------------------------------------------
        */

        if (!$user->role->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Role inactive'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Permission Check
        |--------------------------------------------------------------------------
        */

        $hasPermission = $user->role
            ->permissions()
            ->where('name', $permission)
            ->exists();

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        return $next($request);
    }
}
