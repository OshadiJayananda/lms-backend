<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    public function login(LoginUserRequest $loginUserRequest)
    {
        try {
            $validatedCredentials = $loginUserRequest->validated();

            if (!auth()->attempt($validatedCredentials)) {
                return response()->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            if ($user->roles->isEmpty()) {
                $user->assignRole('user');
            }

            // Include the user's roles
            $role = $user->getRoleNames()->first();

            return response()->json([
                'access_token' => $token,
                'user' => new UserResource($user),
                'role' => $role,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
