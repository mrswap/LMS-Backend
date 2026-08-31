<?php

namespace App\Modules\Admin\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\UserDevice;


class AuthController extends Controller {
    public function login(Request $request) {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account inactive'
            ], 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    public function logout(Request $request) {
        
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $deviceId = $request->header('X-Device-Id');

        // Delete current Sanctum token
        $request->user()->currentAccessToken()?->delete();

        // Clear device binding
        $user->update([
            'device_id'   => null,
            'device_name' => null,
        ]);

        // Remove device record
        UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->delete();

        // Audit log
        audit_log($user->id, 'logout', 'User logged out');

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
