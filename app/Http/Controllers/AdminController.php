<?php

namespace App\Http\Controllers;

use App\Mail\AdminAccountCreated;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate incoming data
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users',
            ]);

            //generate admin password
            $generatedPassword = $this->generateRandomPassword($validated['name']);

            // Create the admin user
            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'email_verified_at' => now(),
                'password'          => Hash::make($generatedPassword),
            ]);

            // Assign admin role
            $user->assignRole('admin');

            Mail::to($user->email)->send(new AdminAccountCreated(
                $user->name,
                $user->email,
                $generatedPassword
            ));

            return response()->json([
                'message' => 'Admin user created successfully',
                'user'    => $user,
            ], 201);
        } catch (ValidationException $e) {
            Log::error('Validation Error creating admin user: ' . $e->errors());

            return response()->json([
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error creating admin user: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while creating the admin user.',
            ], 500);
        }
    }

    private function generateRandomPassword($name)
    {
        $cleanName = strtolower(preg_replace('/\s+/', '', $name));
        $randomString = bin2hex(random_bytes(5)); // 10 characters
        $namePart = substr($cleanName, 0, 2);
        $password = $namePart . $randomString;

        return $password;
    }
}
