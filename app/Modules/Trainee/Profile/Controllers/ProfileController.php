<?php

namespace App\Modules\Trainee\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->only([
            'name',
            'mobile',
            'department',
            'designation_id',
            'region',
            'city'
        ]);

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');

            // store in storage/app/public/profile_images
            $path = $file->store('profile_images', 'public');

            $data['profile_image'] = $path;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated',
            'data' => $user
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
