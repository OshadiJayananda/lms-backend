<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/books/search', [BookController::class, 'search']);
Route::get('/books/check-isbn', [BookController::class, 'checkIsbn']);


// Admin-only routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/admin-only-route', [BookController::class, 'adminFunction']);
    Route::apiResource('categories', CategoryController::class);
});

// Authenticated Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function () {
        return response()->json(auth()->user());
    });

    Route::post('/user/profile-picture', [AuthController::class, 'updateProfilePicture']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user/get-profile-picture', [AuthController::class, 'getProfilePicture']);
    Route::post('/user/remove-profile-picture', [AuthController::class, 'removeProfilePicture']);

    Route::apiResource('books', BookController::class);
});
