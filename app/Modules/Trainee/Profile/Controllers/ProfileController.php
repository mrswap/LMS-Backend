<?php

namespace App\Modules\Trainee\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditService;

class ProfileController extends Controller
{

    protected $uploadPath = 'uploads/users/profile-images/';

    public function profile(Request $request)
    {

        AuditService::log('profile_viewed', 'User viewed their profile');

        $user = auth()->id();
        app(\App\Services\NotificationService::class)->send(
            $user,
            'AUTH',
            [
                'title'   => 'Test Notification',
                'message' => 'Firebase test working',

                'screen'  => 'HomeScreen',
                'id'      => 1,

                'image'   => null,
                'link'    => null,
                'meta'    => []
            ]
        );
        return response()->json(['data' => $request->user()]);
    }


    public function updateProfile(Request $request)
    {
        AuditService::log('profile_updated', 'User updated their profile');

        $user = $request->user();

        /*
        |------------------------------------------------------------------
        | VALIDATION
        |------------------------------------------------------------------
        */
        $validated = $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'mobile' => 'nullable|string',
            'employee_id' => 'nullable|string',
            'department' => 'nullable|string',
            'designation_id' => 'nullable|exists:designations,id',
            'region' => 'nullable|string',
            'city' => 'nullable|string',
            'profile_image' => $request->hasFile('profile_image')
                ? 'image|mimes:jpg,jpeg,png,webp|max:2048'
                : 'nullable',

        ]);

        /*
        |------------------------------------------------------------------
        | PROFILE IMAGE HANDLING
        |------------------------------------------------------------------
        */
        $oldImage = $user->getRawOriginal('profile_image');

        // ✅ Case 1: New Image Upload
        if ($request->hasFile('profile_image')) {

            // delete old
            if ($oldImage && file_exists(public_path($oldImage))) {
                unlink(public_path($oldImage));
            }

            // upload new
            $file = $request->file('profile_image');
            $name = time() . '_' . \Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);

            $validated['profile_image'] = $this->uploadPath . $name;
        }

        // ✅ Case 2: Explicit NULL → remove image
        elseif ($request->has('profile_image') && $request->profile_image === null) {

            if ($oldImage && file_exists(public_path($oldImage))) {
                unlink(public_path($oldImage));
            }

            $validated['profile_image'] = null;
        }

        /*
        |------------------------------------------------------------------
        | UPDATE USER
        |------------------------------------------------------------------
        */
        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }


    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ]);

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Wrong password'], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        AuditService::log('password_changed', 'User changed their password');

        return response()->json(['message' => 'Password changed']);
    }
}
