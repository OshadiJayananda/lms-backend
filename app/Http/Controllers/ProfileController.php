<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfilePictureRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{

    // Update Profile Picture
    public function store(UpdateProfilePictureRequest $request)
    {
        $user = Auth::user();

        if ($request->hasFile('profile_picture')) {
            // Delete old profile picture if exists
            if ($user->profile_picture) {
                $oldPath = str_replace(asset(''), '', $user->profile_picture);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('profile_picture');
            $path = $file->store('profile_pictures', 'public');

            $user->profile_picture = Storage::url($path); // Store full URL
            $user->save();

            return response()->json(['profile_picture' => asset($user->profile_picture)], 200);
        }

        return response()->json(['error' => 'File upload failed'], 400);
    }


    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'profile_picture' => $user->profile_picture ? asset($user->profile_picture) : null
        ]);
    }


    // Change Password
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();
        $user->password = Hash::make($request->password); // Hash the new password
        $user->save();

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();

        if ($user->profile_picture) {
            $oldPath = str_replace(asset(''), '', $user->profile_picture);
            Storage::disk('public')->delete($oldPath);
            $user->profile_picture = null;
            $user->save();
        }

        return response()->json(['message' => 'Profile picture removed successfully'], 200);
    }
}
