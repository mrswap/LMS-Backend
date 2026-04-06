<?php

namespace App\Modules\Admin\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordController extends Controller
{
    // 1️⃣ FORGOT PASSWORD
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        // ✅ CLEAN LINK (NO EMAIL)
        $resetLink = url("/reset-password?token=$token");

        Mail::raw("Reset your password securely:\n$resetLink", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Reset Password');
        });

        return response()->json([
            'message' => 'Reset link sent to email'
        ]);
    }

    // 2️⃣ RESET PASSWORD
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        // ✅ FIND BY TOKEN ONLY
        $record = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Invalid or expired token'
            ], 400);
        }

        // ⏱ EXPIRY CHECK (60 MIN)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'message' => 'Token expired'
            ], 400);
        }

        // ✅ GET USER FROM TOKEN RECORD
        $user = User::where('email', $record->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // ✅ UPDATE PASSWORD
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // ✅ DELETE TOKEN (ONE-TIME USE)
        DB::table('password_reset_tokens')
            ->where('email', $record->email)
            ->delete();

        return response()->json([
            'message' => 'Password reset successful'
        ]);
    }
}
