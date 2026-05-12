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
use App\Models\UserDevice;
use App\Services\NotificationService;


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
            'source' => 'nullable|in:web,app' // 👈 IMPORTANT
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

            // 🔹 TOKEN
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

            /*
            |-----------------------------------------
            | 🔥 VERIFY LINK LOGIC
            |-----------------------------------------
            */

            $source = $request->get('source', 'web');

            if ($source != 'web') {
                // 👉 MOBILE APP DEEP LINK
                $verifyLink = rtrim(env('APP_DEEP_LINK', 'avante://'), '/')
                    . "/verify-email?token=$token";
            } else {
                // 👉 WEB LINK (DEFAULT)
                $verifyLink = rtrim(env('FRONT_END_SALES_URL'), '/')
                    . "/verify-email?token=$token";
            }

            /*
            |-----------------------------------------
            | SMTP + MAIL
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
            'password' => 'required',

            'fcm_token' => 'nullable|string',
            'device_type' => 'nullable|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        /*
        |--------------------------------------------------------------------------
        | ❌ INVALID CREDENTIALS
        |--------------------------------------------------------------------------
        */

        if (
            !$user
            || !Hash::check($request->password, $user->password)
        ) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | ❌ ACCOUNT INACTIVE
        |--------------------------------------------------------------------------
        */

        if (!$user->is_active) {

            return response()->json([
                'message' => 'Account inactive'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | ❌ ROLE CHECK
        |--------------------------------------------------------------------------
        */

        if (!$user->isSales()) {

            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | ❌ EMAIL VERIFY CHECK
        |--------------------------------------------------------------------------
        */

        if (!$user->email_verified_at) {

            return response()->json([
                'message' => 'Verify email first'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔐 DEVICE CHECK
        |--------------------------------------------------------------------------
        */

        $deviceId = $request->header('X-Device-Id');

        if (!$deviceId) {

            return response()->json([
                'message' => 'Device ID required'
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | ❌ MULTI DEVICE BLOCK
        |--------------------------------------------------------------------------
        */

        if (
            $user->device_id
            && $user->device_id !== $deviceId
        ) {

            return response()->json([
                'message' => 'Already logged in on another device. Contact admin.'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | ✅ BIND DEVICE
        |--------------------------------------------------------------------------
        */

        $user->device_id = $deviceId;

        $user->device_name =
            $request->device_name
            ?? $request->header('User-Agent');

        $user->last_login_at = now();

        $user->save();

        /*
        |--------------------------------------------------------------------------
        | 🔑 CREATE TOKEN
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken('trainee_token')
            ->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | 🧾 AUDIT LOG
        |--------------------------------------------------------------------------
        */

        audit_log(
            $user->id,
            'login',
            'User logged in'
        );

        $userId = $user->id;

        /*
        |--------------------------------------------------------------------------
        | 🔍 INITIAL PROGRESSION
        |--------------------------------------------------------------------------
        */

        $exists = UserProgress::where('user_id', $userId)->exists();

        if (!$exists) {

            $firstTopic = Topic::with([
                'program',
                'level',
                'module',
                'chapter'
            ])
                ->orderBy('id')
                ->first();

            if ($firstTopic) {

                /*
            |--------------------------------------------------------------------------
            | 🔓 UNLOCK FIRST TOPIC
            |--------------------------------------------------------------------------
            */

                $progress = UserProgress::create([
                    'user_id' => $userId,

                    'program_id' => $firstTopic->program_id,
                    'level_id' => $firstTopic->level_id,
                    'module_id' => $firstTopic->module_id,
                    'chapter_id' => $firstTopic->chapter_id,
                    'topic_id' => $firstTopic->id,

                    'is_unlocked' => true,
                    'is_completed' => false,
                ]);

                /*
            |--------------------------------------------------------------------------
            | 🖼 IMAGE RESOLVE
            |--------------------------------------------------------------------------
            */

                $image =
                    $firstTopic->image
                    ?? $firstTopic->chapter->image
                    ?? $firstTopic->module->image
                    ?? $firstTopic->level->image
                    ?? $firstTopic->program->image
                    ?? null;

                /*
            |--------------------------------------------------------------------------
            | 👤 TRAINEE NOTIFICATION
            |--------------------------------------------------------------------------
            */

                app(\App\Services\NotificationService::class)->send(
                    $user,
                    'TRAINING_ASSIGNED',
                    [
                        'title' => 'Training Assigned',

                        'message' =>
                        "Your training journey has started with {$firstTopic->title}",

                        'screen' => 'TopicDetails',

                        'id' => $firstTopic->id,

                        'image' => $image,

                        'meta' => [

                            'progress_id' => $progress->id,

                            'program_id' => $firstTopic->program_id,
                            'program_title' => $firstTopic->program->title ?? null,

                            'level_id' => $firstTopic->level_id,
                            'level_title' => $firstTopic->level->title ?? null,

                            'module_id' => $firstTopic->module_id,
                            'module_title' => $firstTopic->module->title ?? null,

                            'chapter_id' => $firstTopic->chapter_id,
                            'chapter_title' => $firstTopic->chapter->title ?? null,

                            'topic_id' => $firstTopic->id,
                            'topic_title' => $firstTopic->title,
                        ]
                    ],
                    ['db', 'push']
                );

                /*
            |--------------------------------------------------------------------------
            | 🛡 ADMIN NOTIFICATION
            |--------------------------------------------------------------------------
            */

                $adminPayload = [

                    'title' => 'Training Auto Assigned',

                    'message' =>
                    "{$user->name} received initial training assignment",

                    'screen' => 'UserDetails',

                    'id' => $user->id,

                    'image' => $image,

                    'meta' => [

                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_email' => $user->email,

                        'program_id' => $firstTopic->program_id,
                        'program_title' => $firstTopic->program->title ?? null,

                        'level_id' => $firstTopic->level_id,
                        'level_title' => $firstTopic->level->title ?? null,

                        'module_id' => $firstTopic->module_id,
                        'module_title' => $firstTopic->module->title ?? null,

                        'chapter_id' => $firstTopic->chapter_id,
                        'chapter_title' => $firstTopic->chapter->title ?? null,

                        'topic_id' => $firstTopic->id,
                        'topic_title' => $firstTopic->title,
                    ]
                ];

                app(\App\Services\NotificationService::class)->sendToRole(
                    'admin',
                    'TRAINING_ASSIGNED',
                    $adminPayload,
                    ['db', 'push']
                );

                app(\App\Services\NotificationService::class)->sendToRole(
                    'superadmin',
                    'TRAINING_ASSIGNED',
                    $adminPayload,
                    ['db', 'push']
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 📱 STORE DEVICE TOKEN
        |--------------------------------------------------------------------------
        */

        if ($request->fcm_token) {

            UserDevice::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                ],
                [
                    'fcm_token' => $request->fcm_token,

                    'device_type' =>
                    $request->device_type
                        ?? 'android',

                    'device_name' =>
                    $request->device_name
                        ?? $request->header('User-Agent'),

                    'last_used_at' => now()
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */

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

        $deviceId = $request->header('X-Device-Id');

        UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
