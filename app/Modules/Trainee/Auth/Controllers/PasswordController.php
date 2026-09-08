<?php

namespace App\Modules\Trainee\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\SmtpService;


class PasswordController extends Controller {
    protected $smtpService;

    public function __construct(SmtpService $smtpService) {
        $this->smtpService = $smtpService;
    }

    /*
    |-----------------------------------------
    | FORGOT PASSWORD
    |-----------------------------------------
    */

    public function forgotPassword(Request $request) {
        /*
        |-----------------------------------------
        | VALIDATION
        |-----------------------------------------
        */
        $request->validate([
            'email' => 'required|email'
        ]);

        /*
        |-----------------------------------------
        | CHECK USER
        |-----------------------------------------
        */
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        /*
        |-----------------------------------------
        | GENERATE TOKEN
        |-----------------------------------------
        */
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        /*
        |-----------------------------------------
        | DYNAMIC LINK (DEFAULT = MOBILE)
        |-----------------------------------------
        */
        $source = $request->input('source', 'app');
        if ($source == 'app') {
            $verifyLink = env('APP_DEEP_LINK', 'avante://')
                . "trainee/reset-password?token=$token";
        } else if ($source == 'web') {
            $verifyLink = rtrim(env('FRONT_END_SALES_URL'), '/')
                . "/trainee/reset-password?token=$token";
        } else if ($source == 'ios') {
            $verifyLink = rtrim(env('IOS_DEEP_LINK'), '/')
                . "/trainee/reset-password?token=$token";
        } else {
            $verifyLink = env('APP_DEEP_LINK', 'avante://')
                . "trainee/reset-password?token=$token";
        }

        $verifyLink = rtrim(env('FRONT_END_SALES_URL'), '/') . "/reset-password?token={$token}";

        /*
        |-----------------------------------------
        | APPLY SMTP CONFIG
        |-----------------------------------------
        */
        $smtp = \App\Models\SmtpSetting::first();
        if ($smtp) {
            $this->smtpService->applyConfig($smtp);
        }

        /*
        |-----------------------------------------
        | SEND MAIL
        |-----------------------------------------
        */
        Mail::raw(
            "Reset your password:\n\n" . $verifyLink,
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Reset Password');
            }
        );

        /*
        |-----------------------------------------
        | RESPONSE
        |-----------------------------------------
        */
        return response()->json([
            'message' => 'Reset link sent to email'
        ]);
    }

    /*
    |-----------------------------------------
    | RESET PASSWORD
    |-----------------------------------------
    */
    public function resetPassword(Request $request) {
        $request->validate([
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Invalid or expired token'
            ], 400);
        }

        // Token expiry check (60 min)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'message' => 'Token expired'
            ], 400);
        }

        $user = User::where('email', $record->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        // Delete token after use
        DB::table('password_reset_tokens')
            ->where('email', $record->email)
            ->delete();

        return response()->json([
            'message' => 'Password reset successful'
        ]);
    }
}
