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
use Illuminate\Support\Facades\Cache;
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
                'role' => $user->getRoleNames()->first(),
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
            if ($user = $request->user()) {
                Cache::forget("user_token_{$user->id}");
                $currentToken = $user->currentAccessToken();

                if ($currentToken) {
                    $currentToken->delete();
                }
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

    public function validateToken(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Token is invalid'], 401);
            }

            // Cache the response for 5 minutes to reduce DB checks
            $data = Cache::remember("user_token_{$user->id}", now()->addMinutes(5), function () use ($user) {
                return [
                    'message' => 'Token is valid',
                    'user' => new UserResource($user),
                ];
            });

            return response()->json($data);
        } catch (Exception $e) {
            Log::error('Token Validation Error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while validating the token.'], 500);
        }
    }
}
