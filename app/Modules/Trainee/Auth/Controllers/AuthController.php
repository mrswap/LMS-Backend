<?php

namespace App\Modules\Trainee\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\SmtpService;
use App\Models\UserProgress;
use App\Models\Topic;
use App\Services\AuditService;


class AuthController extends Controller
{
    protected $smtpService;

    public function __construct(SmtpService $smtpService)
    {
        $this->smtpService = $smtpService;
    }

    /*
    |-----------------------------------------
    | REGISTER
    |-----------------------------------------
    */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required',
            'password' => 'required|min:6|confirmed',
            'designation_id' => 'required|exists:designations,id',
            'region' => 'required',
        ]);

        DB::beginTransaction();

        try {

            $roleId = \App\Models\Role::where('name', User::ROLE_SALES)->value('id');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'employee_id' => $request->employee_id,
                'role_id' => $roleId,
                'designation_id' => $request->designation_id,
                'department' => $request->department,
                'region' => $request->region,
                'city' => $request->city,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);

            /*
            |-----------------------------------------
            | CREATE EMAIL VERIFICATION TOKEN
            |-----------------------------------------
            */
            $token = Str::random(64);

            DB::table('email_verification_tokens')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'token' => $token,
                    'expires_at' => Carbon::now()->addMinutes(60),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $verifyLink = rtrim(env('FRONT_END_URL'), '/') . "trainee/verify-email?token=$token";

            /*
            |-----------------------------------------
            | APPLY SMTP + SEND MAIL
            |-----------------------------------------
            */
            $smtp = \App\Models\SmtpSetting::first();
            if ($smtp) {
                $this->smtpService->applyConfig($smtp);
            }

            Mail::raw("Verify your email:\n$verifyLink", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verify Email');
            });

            DB::commit();

            return response()->json([
                'message' => 'Registered successfully. Please verify your email.'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Register Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |-----------------------------------------
    | VERIFY EMAIL (TOKEN BASED)
    |-----------------------------------------
    */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required'
        ]);

        $record = DB::table('email_verification_tokens')
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        $user = User::find($record->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
            $user->save();
        }

        DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    /*
    |-----------------------------------------
    | LOGIN
    |-----------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account inactive'], 403);
        }

        if (!$user->isSales()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Verify email first'], 403);
        }

        // 🔐 DEVICE CHECK
        $deviceId = $request->header('X-Device-Id');

        if (!$deviceId) {
            return response()->json([
                'message' => 'Device ID required'
            ], 400);
        }

        if ($user->device_id && $user->device_id !== $deviceId) {
            return response()->json([
                'message' => 'Already logged in on another device. Contact admin.'
            ], 403);
        }

        // ✅ Bind device
        $user->device_id = $deviceId;
        $user->device_name = $request->header('User-Agent');
        $user->last_login_at = now();
        $user->save();

        // 🔑 Token create
        $token = $user->createToken('trainee_token')->plainTextToken;

        // 🧾 AUDIT LOG (AFTER SUCCESS LOGIN)
        audit_log($user->id, 'login', 'User logged in');

        $userId = $user->id;

        // 🔍 Progress init
        $exists = UserProgress::where('user_id', $userId)->exists();

        if (!$exists) {
            $firstTopic = Topic::orderBy('id')->first();

            if ($firstTopic) {
                UserProgress::create([
                    'user_id' => $userId,
                    'program_id' => $firstTopic->program_id,
                    'level_id' => $firstTopic->level_id,
                    'module_id' => $firstTopic->module_id,
                    'chapter_id' => $firstTopic->chapter_id,
                    'topic_id' => $firstTopic->id,
                    'is_unlocked' => true,
                    'is_completed' => false,
                ]);
            }
        }

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
    /*
    |-----------------------------------------
    | LOGOUT
    |-----------------------------------------
    */
    public function logout(Request $request)
    {
        AuditService::log('logged_out', 'User logged out of the system');       

        $user = $request->user();

        if (!$user) {       
            return response()->json([
                'message' => 'Unauthenticated'  
            ], 401);
        }

        // 🔑 Delete current token
        $request->user()->currentAccessToken()?->delete();

        // 🔓 Unbind device
        $user->device_id = null;
        $user->device_name = null;
        $user->save();

        // 🧾 Audit log
        audit_log($user->id, 'logout', 'User logged out');

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
