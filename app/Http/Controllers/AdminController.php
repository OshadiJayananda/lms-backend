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
        //Must be at least 8 characters with uppercase, lowercase, number and special character
        $cleanName = strtolower(preg_replace('/\s+/', '', $name));
        $namePart = substr($cleanName, 0, 2);

        // Ensure at least one uppercase, lowercase, number, and special character
        $uppercase = chr(rand(65, 90)); // A-Z
        $lowercase = chr(rand(97, 122)); // a-z
        $number    = chr(rand(48, 57)); // 0-9
        $special   = chr(rand(33, 47)); // special characters like ! " # $

        // Random filler to reach desired length
        $randomLength = 8 - strlen($namePart) - 4;
        $randomString = substr(str_shuffle(
            str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*', 5)
        ), 0, $randomLength);

        // Combine and shuffle to avoid predictable order
        $password = str_shuffle($namePart . $uppercase . $lowercase . $number . $special . $randomString);

        return $password;
    }
}
