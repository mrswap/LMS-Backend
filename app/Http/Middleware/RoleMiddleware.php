<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // Step 1: Not logged in
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Step 2: Inactive user
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account inactive'
            ], 403);
        }

        // 🔥 Step 3: Role check (FIXED)
        $user->load('role');

        if (!empty($roles) && (! $user->role || !in_array($user->role->name, $roles))) {
            return response()->json([
                'message' => 'Unauthorized role access'
            ], 403);
        }

        return $next($request);
    }
}
