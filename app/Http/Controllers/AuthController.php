<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Register a new user
    public function register(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'address' => 'required|string|max:255',
                'contact' => 'required|string|max:10',
                'password' => 'required|string|min:6|confirmed',
            ]);

            // Check for validation errors
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create a new user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'address' => $request->address,
                'contact' => $request->contact,
                'password' => Hash::make($request->password),
            ]);

            if ($user->roles->isEmpty()) {
                $user->assignRole('user');
            }
            // Issue a Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Return success response
            return response()->json([
                'access_token' => $token,
                'user' => $user,
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    //Login
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string|min:6',
            ]);

            // Check for validation errors
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $credentials = ['email' => $request->email, 'password' => $request->password];

            if (!auth()->attempt($credentials)) {
                return response()->json(['error' => 'invalid credentials'], 403);
            }

            $user = User::where('email', $request->email)->firstOrFail();
            $token = $user->createToken('auth_token')->plainTextToken;

            if ($user->roles->isEmpty()) {
                $user->assignRole('user');
            }

            // Include the user's roles
            $role = $user->getRoleNames()->first(); // Assuming a user has one role

            return response()->json([
                'access_token' => $token,
                'user' => $user,
                'role' => $role,
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Check if the user has a valid token
            if ($request->user()->currentAccessToken()) {
                // Revoke the current token
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'message' => 'User has been logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            // Log the exception for debugging
            Log::error('Logout Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while logging out.',
            ], 500);
        }
    }
}
