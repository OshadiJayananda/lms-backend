<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfilePictureRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // Register a new user
    public function register(RegisterUserRequest $request)
    {
        try {
            $validatedUser = $request->validated();

            $user = User::create([
                'name' => $validatedUser['name'],
                'email' => $validatedUser['email'],
                'address' => $validatedUser['address'],
                'contact' => $validatedUser['contact'],
                'password' => Hash::make($validatedUser['password']),
            ]);

            if ($user->roles->isEmpty()) {
                $user->assignRole('user');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'user' => new UserResource($user),
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    // Login user
    public function login(LoginUserRequest $request)
    {
        try {
            $validatedCredentials = $request->validated();

            if (!Auth::attempt(['email' => $validatedCredentials['email'], 'password' => $validatedCredentials['password']])) {
                return response()->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }
            $token = $user->createToken('auth_token')->plainTextToken;


            return response()->json([
                'access_token' => $token,
                'user' => new UserResource($user),
                'role' => $user->getRoleNames()->first(),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Logout user
    public function logout(Request $request)
    {
        try {
            if ($request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'message' => 'User has been logged out successfully',
            ], 200);
        } catch (Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while logging out.',
            ], 500);
        }
    }


    // Update Profile Picture
    public function updateProfilePicture(UpdateProfilePictureRequest $request)
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


    public function getProfilePicture(Request $request)
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

    public function removeProfilePicture(Request $request)
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
