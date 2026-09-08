<?php

namespace App\Modules\Admin\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Services\SmtpService;


class UserController extends Controller {
    protected $uploadPath = 'uploads/users/profile-images/';

    protected $smtpService;

    public function __construct(SmtpService $smtpService) {
        $this->smtpService = $smtpService;
    }

    public function index(Request $request) {
        /*
            |------------------------------------------------------------
            | ROLE FILTER
            |------------------------------------------------------------
            | Supported:
            |
            | ?role=sales
            | ?role=staff
            | ?role=superadmin
            | ?role=all
            | ?role=no_sales
            */
        $role = strtolower(
            $request->get('role', User::ROLE_SALES)
        );

        $query = User::query()
            ->with([
                'creator:id,name',
                'role:id,name,label',
                'designation:id,name,label'
            ])

            /*
                |------------------------------------------------------------
                | EXCLUDE ROLE ID = 1
                |------------------------------------------------------------
                */
            ->where('role_id', '!=', 1);

        /*
            |------------------------------------------------------------
            | ROLE CONDITION
            |------------------------------------------------------------
            */
        if ($role !== 'all') {

            /*
                |------------------------------------------------------------
                | NO SALES
                |------------------------------------------------------------
                */
            if ($role === 'no_sales') {

                $query->whereHas('role', function ($q) {

                    $q->where('name', '!=', User::ROLE_SALES);
                });
            } else {

                /*
                    |------------------------------------------------------------
                    | NORMAL ROLE FILTER
                    |------------------------------------------------------------
                    */
                $allowedRoles = [
                    User::ROLE_SUPERADMIN,
                    User::ROLE_STAFF,
                    User::ROLE_SALES,
                ];

                if (!in_array($role, $allowedRoles)) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid role provided.'
                    ], 422);
                }

                $query->whereHas('role', function ($q) use ($role) {

                    $q->where('name', $role);
                });
            }
        }

        /*
                |------------------------------------------------------------
                | SEARCH
                |------------------------------------------------------------
                */
        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");

                /*
                    |------------------------------------------------------------
                    | SEARCH BY ROLE
                    |------------------------------------------------------------
                    */
                $q->orWhereHas('role', function ($roleQuery) use ($search) {

                    $roleQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });

                /*
                    |------------------------------------------------------------
                    | SEARCH BY DESIGNATION
                    |------------------------------------------------------------
                    */
                $q->orWhereHas('designation', function ($designationQuery) use ($search) {

                    $designationQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            });
        }

        /*
                |------------------------------------------------------------
                | STATUS FILTER
                |------------------------------------------------------------
                */
        if ($request->has('status')) {

            if ($request->status !== 'all') {

                $query->where(
                    'is_active',
                    (int) $request->status === 1 ? 1 : 0
                );
            }
        }

        /*
                |------------------------------------------------------------
                | DESIGNATION FILTER
                |------------------------------------------------------------
                */
        if ($request->filled('designation_id')) {

            $query->where(
                'designation_id',
                $request->designation_id
            );
        }

        /*
                |------------------------------------------------------------
                | REGION FILTER
                |------------------------------------------------------------
                */
        if ($request->filled('region')) {

            $query->where(
                'region',
                $request->region
            );
        }

        /*
                |------------------------------------------------------------
                | CITY FILTER
                |------------------------------------------------------------
                */
        if ($request->filled('city')) {

            $query->where(
                'city',
                $request->city
            );
        }

        /*
                |------------------------------------------------------------
                | SORTING
                |------------------------------------------------------------
                */
        $sortByMap = [
            'createdAt'  => 'created_at',
            'updatedAt'  => 'updated_at',
            'name'       => 'name',
            'email'      => 'email',
            'mobile'     => 'mobile',
            'employeeId' => 'employee_id',
        ];

        $sortBy = $request->get(
            'sortBy',
            'createdAt'
        );

        $order = strtolower(
            $request->get('order', 'desc')
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $sortColumn = $sortByMap[$sortBy]
            ?? 'created_at';

        $query->orderBy(
            $sortColumn,
            $order
        );

        /*
                |------------------------------------------------------------
                | PAGINATION
                |------------------------------------------------------------
                */
        $limit = (int) $request->get('limit', 10);

        $limit = ($limit > 0 && $limit <= 100)
            ? $limit
            : 10;

        $users = $query->paginate($limit);

        /*
                |------------------------------------------------------------
                | RESPONSE
                |------------------------------------------------------------
                */
        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }


    public function store(Request $request) {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required',
            'department' => 'required',
            'region' => 'required',
            'designation_id' => 'required|exists:designations,id',
            'role_id' => 'required|exists:roles,id',
            'password' => 'nullable|min:6',
        ]);

        $imagePath = null;

        /*
    |--------------------------------------------------------------------------
    | PROFILE IMAGE
    |--------------------------------------------------------------------------
    */
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');

            $name = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $name);

            $imagePath = $this->uploadPath . $name;
        }

        /*
    |--------------------------------------------------------------------------
    | CREATE USER
    |--------------------------------------------------------------------------
    */
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'employee_id' => $request->employee_id,
            'role_id' => $request->role_id,
            'department' => $request->department,
            'designation_id' => $request->designation_id,
            'region' => $request->region,
            'city' => $request->city,

            // Password can be null during user creation.
            // User will create password using the email link.
            'password' => $request->filled('password')
                ? Hash::make($request->password)
                : null,

            'profile_image' => $imagePath,
            'created_by' => auth()->id(),
        ]);

        /*
    |--------------------------------------------------------------------------
    | GENERATE PASSWORD SETUP TOKEN
    |--------------------------------------------------------------------------
    */
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token,
                'created_at' => Carbon::now(),
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | PASSWORD SETUP LINK
    |--------------------------------------------------------------------------
    */
        $passwordSetupLink = rtrim(
            env('FRONT_END_SALES_URL'),
            '/'
        ) . "/reset-password?token={$token}";

        /*
    |--------------------------------------------------------------------------
    | APPLY SMTP CONFIGURATION
    |--------------------------------------------------------------------------
    */
        $smtp = \App\Models\SmtpSetting::first();

        if ($smtp) {
            $this->smtpService->applyConfig($smtp);
        }

        /*
    |--------------------------------------------------------------------------
    | SEND ACCOUNT CREATION EMAIL
    |--------------------------------------------------------------------------
    */
        $notificationStatus = 'sent';

        try {

            Mail::raw(
                "Hello {$user->name},

Your Avante Medical account has been created successfully.

You can login using the following email address:

Username / Email: {$user->email}

Login URL:
" . rtrim(env('FRONT_END_SALES_URL'), '/') . "

To create your password, please use the secure link below:

Set Your Password:
{$passwordSetupLink}

This password setup link is valid for 60 minutes.

If you did not expect this account, please contact the administrator.

Regards,
Avante Medical Team",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Your Avante Medical Account Has Been Created');
                }
            );
        } catch (\Throwable $e) {

            $notificationStatus = 'failed';

            \Log::error('User creation email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */
        return response()->json([
            'message' => 'User account created successfully.',
            'data' => $user,
            'notification' => [
                'type' => 'email',
                'status' => $notificationStatus,
            ],
        ]);
    }





    public function show($id) {
        return response()->json(User::findOrFail($id));
    }

    public function update(Request $request, $id) {
        $user = User::findOrFail($id);

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $name = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);
            $user->profile_image = $this->uploadPath . $name;
        }

        $data = [
            'name' => $request->name ?? $user->name,
            'mobile' => $request->mobile ?? $user->mobile,
            'employee_id' => $request->employee_id ?? $user->employee_id,
            'designation_id' => $request->designation_id ?? $user->designation_id,
            'region' => $request->region ?? $user->region,
            'city' => $request->city ?? $user->city,
            'role_id' => $request->role_id ?? $user->role_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function destroy($id) {
        User::findOrFail($id)->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function toggleStatus($id) {
        $user = User::findOrFail($id);

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'status' => $user->is_active
        ]);
    }

    public function profile(Request $request) {
        $user = $request->user();

        $user->load([
            'role.permissions',
            'designation'
        ]);

        /*
    |--------------------------------------------------------------------------
    | Assigned Permission IDs
    |--------------------------------------------------------------------------
    */

        $assignedPermissions = $user->role
            ? $user->role->permissions->pluck('id')->toArray()
            : [];

        /*
    |--------------------------------------------------------------------------
    | Superadmin
    |--------------------------------------------------------------------------
    */

        if ($user->role?->name === 'superadmin') {

            $assignedPermissions = \App\Models\Permission::pluck('id')
                ->toArray();
        }

        /*
    |--------------------------------------------------------------------------
    | All Permissions
    |--------------------------------------------------------------------------
    */

        $permissions = \App\Models\Permission::select(
            'id',
            'name'
        )
            ->get()
            ->map(function ($permission) use ($assignedPermissions) {

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'assigned' => in_array(
                        $permission->id,
                        $assignedPermissions
                    )
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [

                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'employee_id' => $user->employee_id,
                'profile_image' => $user->profile_image,

                'department' => $user->department,
                'region' => $user->region,
                'city' => $user->city,

                'is_active' => $user->is_active,

                /*
            |--------------------------------------------------------------------------
            | Role
            |--------------------------------------------------------------------------
            */

                'role' => [
                    'id' => $user->role?->id,
                    'name' => $user->role?->name,
                    'label' => $user->role?->label,
                ],

                /*
            |--------------------------------------------------------------------------
            | Designation
            |--------------------------------------------------------------------------
            */

                'designation' => [
                    'id' => $user->designation?->id,
                    'name' => $user->designation?->name,
                ],

                /*
            |--------------------------------------------------------------------------
            | Permissions
            |--------------------------------------------------------------------------
            */

                'permissions' => $permissions,

                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    public function updateProfile(Request $request) {
        $user = $request->user();

        // 🔹 Validation (dynamic)
        $request->validate([
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6|confirmed',
        ]);

        /*
        |--------------------------------------------------------------------------
        | PROFILE IMAGE
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $name = time() . '_' . \Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);
            $user->profile_image = $this->uploadPath . $name;
        }

        /*
        |--------------------------------------------------------------------------
        | PASSWORD (ONLY IF PROVIDED)
        |--------------------------------------------------------------------------
        */
        if ($request->filled('password')) {
            $user->password = \Hash::make($request->password);
        }

        /*
        |--------------------------------------------------------------------------
        | ALL OTHER FIELDS (DYNAMIC UPDATE)
        |--------------------------------------------------------------------------
        */
        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'mobile' => $request->mobile ?? $user->mobile,
            'employee_id' => $request->employee_id ?? $user->employee_id,
            'department' => $request->department ?? $user->department,
            'designation' => $request->designation ?? $user->designation,
            'region' => $request->region ?? $user->region,
            'city' => $request->city ?? $user->city,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    public function changePassword(Request $request) {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CHECK CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PASSWORD
        |--------------------------------------------------------------------------
        */
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    public function resetDevice($id) {
        $user = User::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Reset User Device
        |--------------------------------------------------------------------------
        */

        $user->device_id = null;
        $user->device_name = null;
        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Remove All Registered Devices
        |--------------------------------------------------------------------------
        */

        $user->devices()->delete();

        /*
        |--------------------------------------------------------------------------
        | Revoke All Login Tokens
        |--------------------------------------------------------------------------
        */

        $user->tokens()->delete();

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */

        audit_log(
            auth()->id(),
            'reset_device',
            "Admin reset device for user {$user->id}"
        );

        return response()->json([
            'status'  => true,
            'message' => 'Device reset successfully.'
        ]);
    }
}
