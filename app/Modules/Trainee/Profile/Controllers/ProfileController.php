<?php

namespace App\Modules\Trainee\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{

    protected $uploadPath = 'uploads/users/profile-images/';

    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user(); // 🔥 id auth se

        /*
        |------------------------------------------------------------------
        | VALIDATION
        |------------------------------------------------------------------
        */
        $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'mobile' => 'nullable|string',
            'designation_id' => 'nullable|exists:designations,id',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        /*
    |------------------------------------------------------------------
    | PROFILE IMAGE
    |------------------------------------------------------------------
    */
        if ($request->hasFile('profile_image')) {

            // old delete
            if (
                $user->getRawOriginal('profile_image') &&
                file_exists(public_path($user->getRawOriginal('profile_image')))
            ) {

                unlink(public_path($user->getRawOriginal('profile_image')));
            }

            // upload new
            $file = $request->file('profile_image');
            $name = time() . '_' . \Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);

            $user->profile_image = $this->uploadPath . $name;
        }

        /*
    |------------------------------------------------------------------
    | UPDATE FIELDS
    |------------------------------------------------------------------
    */
        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'employee_id' => $request->employee_id,
            'department' => $request->department,
            'designation_id' => $request->designation_id,
            'region' => $request->region,
            'city' => $request->city,
        ]);

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

        return response()->json(['message' => 'Password changed']);
    }
}
