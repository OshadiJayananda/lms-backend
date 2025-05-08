<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
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
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('guest')
    ->name('password.email');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])
    ->middleware('guest')
    ->name('password.update');

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/admin-only-route', [BookController::class, 'adminFunction']);
    // Route::apiResource('categories', CategoryController::class);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('/admin/book-requests', [BorrowController::class, 'getPendingRequests']);
    Route::post('/admin/book-requests/{borrowId}/approve', [BorrowController::class, 'approveRequest']);
    Route::post('/admin/book-requests/{borrowId}/reject', [BorrowController::class, 'rejectRequest']);
    Route::post('/admin/book-requests/{borrowId}/confirm', [BorrowController::class, 'confirmBookGiven']);
    Route::get('/admin/returned-books', [BorrowController::class, 'getReturnedBooks']);
    Route::post('/admin/returned-books/{borrowId}/confirm', [BorrowController::class, 'confirmReturn']);
    Route::get('/admin/borrowed-books', [BorrowController::class, 'getAllBorrowedBooks']);
    Route::get('/admin/renew-requests', [BorrowController::class, 'getRenewRequests']);
    Route::post('/admin/renew-requests/{requestId}/approve', [BorrowController::class, 'approveRenewRequest']);
    Route::post('/admin/renew-requests/{requestId}/reject', [BorrowController::class, 'rejectRenewRequest']);

    // Availability notifications
    Route::get('/admin/availability-notifications', [BorrowController::class, 'checkAvailabilityNotifications']);
    Route::post('/admin/notify-available/{bookId}', [BorrowController::class, 'notifyAvailableBooks']);
    Route::get('/admin/book-reservations', [BookController::class, 'getReservations']);
    Route::post('/admin/book-reservations/{reservationId}/approve', [BookController::class, 'approveReservation']);
    Route::post('/admin/book-reservations/{reservationId}/reject', [BookController::class, 'rejectReservation']);
    // Route::get('/admin/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/admin/book-reservations/{reservationId}/confirm-given', [BookController::class, 'confirmBookGiven']);
    Route::get('/admin/notifications', [NotificationController::class, 'index']);
    Route::post('/admin/book-reservations/{reservation}/create-borrow', [BookController::class, 'createBorrowFromReservation']);
    Route::post('/admin/renew-requests/{requestId}/confirm', [BorrowController::class, 'confirmRenewalDate']);
});

// Authenticated Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/validate-token', [AuthController::class, 'validateToken']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function () {
        $user = auth()->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'address' => $user->address,
            'contact' => $user->contact,
            'role' => $user->getRoleNames()->first()
        ]);
    });

    // Route::post('/user/profile-picture', [ProfileController::class, 'updateProfilePicture']);
    // Route::post('/user/change-password', [ProfileController::class, 'changePassword']);
    // Route::get('/user/get-profile-picture', [ProfileController::class, 'getProfilePicture']);
    // Route::post('/user/remove-profile-picture', [ProfileController::class, 'removeProfilePicture']);

    Route::apiResource('profile', ProfileController::class);
    Route::post('/user/change-password', [ProfileController::class, 'changePassword']);

    Route::apiResource('books', BookController::class);
    Route::post('/books/{bookId}/request', [BorrowController::class, 'requestBook']);
    Route::get('/borrowed-books', [BorrowController::class, 'getBorrowedBooks']);
    Route::post('/borrowed-books/{bookId}/return', [BorrowController::class, 'returnBook']);
    Route::post('/borrowed-books/{bookId}/renew', [BorrowController::class, 'renewBook']);

    // Book availability check
    Route::get('/books/{bookId}/availability', [BorrowController::class, 'checkBookAvailability']);

    // Renewal requests
    Route::post('/borrowed-books/{bookId}/renew-request', [BorrowController::class, 'renewRequest']);
    Route::post('/borrowed-books/{bookId}/notify-admin', [BorrowController::class, 'notifyAdmin']);
    Route::post('/books/{bookId}/reserve', [BookController::class, 'reserveBook']);
    Route::post('/reservations/{reservation}/respond', [BorrowController::class, 'handleReservationResponse']);

    Route::post('/notifications/create', [NotificationController::class, 'create']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/reservations/{reservationId}/respond', [BorrowController::class, 'respondToReservation']);
    Route::get('/user/notifications', [NotificationController::class, 'userNotifications']);
    Route::post('/renew-requests/{requestId}/confirm', [BorrowController::class, 'confirmRenewalDate']);
    Route::post(
        '/notifications/{notification}/renewal-response',
        [NotificationController::class, 'handleRenewalResponse']
    );
});
