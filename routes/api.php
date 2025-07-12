<?php

use App\Http\Controllers\BorrowingPolicyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookReservationController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RenewBookController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
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
    Route::get('/admin/renew-requests', [RenewBookController::class, 'getRenewRequests']);
    Route::post('/admin/renew-requests/{requestId}/approve', [RenewBookController::class, 'approveRenewRequest']);
    Route::post('/admin/renew-requests/{requestId}/reject', [RenewBookController::class, 'rejectRenewRequest']);
    Route::delete('/admin/renew-requests/{requestId}', [RenewBookController::class, 'destroy']);

    // Availability notifications
    // Route::get('/admin/availability-notifications', [BorrowController::class, 'checkAvailabilityNotifications']);
    // Route::post('/admin/notify-available/{bookId}', [BorrowController::class, 'notifyAvailableBooks']);
    Route::get('/admin/book-reservations', [BookReservationController::class, 'getReservations']);
    Route::post('/admin/book-reservations/{reservationId}/approve', [BookReservationController::class, 'approveReservation']);
    Route::post('/admin/book-reservations/{reservationId}/reject', [BookReservationController::class, 'rejectReservation']);
    Route::delete('/admin/book-reservations/{reservationId}', [BookReservationController::class, 'destroy']);
    // Route::get('/admin/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/admin/book-reservations/{reservationId}/confirm-given', [BookReservationController::class, 'confirmBookGiven']);
    Route::get('/admin/notifications', [NotificationController::class, 'index']);
    // Route::post('/admin/book-reservations/{reservation}/create-borrow', [BorrowController::class, 'createBorrowFromReservation']);
    Route::post('/admin/renew-requests/{requestId}/confirm', [RenewBookController::class, 'confirmRenewalDate']);
    Route::post('authors', [AuthorController::class, 'store']);
    Route::put('authors/{author}', [AuthorController::class, 'update']);
    Route::delete('authors/{author}', [AuthorController::class, 'destroy']);
    Route::get('admin/members', [MemberController::class, 'index']);
    Route::get('/admin/dashboard-stats', [BookController::class, 'getDashboardStats']);
    Route::put('/borrowing-policies', [BorrowingPolicyController::class, 'update']);
    Route::delete('/borrowing-policies', [BorrowingPolicyController::class, 'destroy']);
    Route::get('/admin/book-reservations/pending/{bookId}', [BookReservationController::class, 'getPendingReservations']);
    Route::get('/admin/payments', [PaymentController::class, 'getPaymentList']);
    Route::delete('/admin/borrowed-books/{id}', [BorrowController::class, 'destroy']);
    // Report Routes
    Route::prefix('admin/reports')->group(function () {
        Route::get('/{type}', [ReportController::class, 'generateReport'])
            ->name('admin.reports.generate')
            ->where('type', 'books|members|borrowings|overdue');

        // Optional: Additional report endpoints
        Route::post('/custom', [ReportController::class, 'generateCustomReport']);
    });

    Route::get('/admin/reports/overdue', [ReportController::class, 'generateOverdueReport']);
});

// Authenticated Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/validate-token', [AuthController::class, 'validateToken']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/user/dashboard-stats', [BookController::class, 'getUserDashboardStats']);
    Route::get('/borrowing-policies', [BorrowingPolicyController::class, 'index']);

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

    Route::get('authors', [AuthorController::class, 'index']);
    Route::get('authors/{author}', [AuthorController::class, 'show']);

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
    Route::post('/borrowed-books/{bookId}/renew', [RenewBookController::class, 'renewBook']);

    // getOverdueBooks
    Route::get('/borrows/overdue', [BorrowController::class, 'getOverdueBooks']);



    // Book availability check
    // Route::get('/books/{bookId}/availability', [BorrowController::class, 'checkBookAvailability']);

    // Renewal requests
    Route::post('/borrowed-books/{bookId}/renew-request', [RenewBookController::class, 'renewRequest']);
    Route::post('/borrowed-books/{bookId}/notify-admin', [RenewBookController::class, 'notifyAdmin']);
    Route::post('/books/{bookId}/reserve', [BookReservationController::class, 'reserveBook']);
    Route::get('/book-reservations', [BookReservationController::class, 'getUserReservationList']);
    Route::post('/book-reservations/{reservation}/response', [BookReservationController::class, 'handleReservationResponse']);

    Route::post('/notifications/create', [NotificationController::class, 'create']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    // Route::post('/reservations/{reservationId}/respond', [BookReservationController::class, 'respondToReservation']);
    Route::get('/user/notifications', [NotificationController::class, 'userNotifications']);
    Route::post('/renew-requests/{requestId}/confirm', [RenewBookController::class, 'confirmRenewalDate']);
    Route::post(
        '/notifications/{notification}/renewal-response',
        [NotificationController::class, 'handleRenewalResponse']
    );


    //Paymrnt routes
    Route::post('/payments/create-checkout-session/{borrow}', [PaymentController::class, 'createCheckoutSession']);
    Route::get('/payments/success', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/payments/cancel', [PaymentController::class, 'paymentCancel'])->name('payment.cancel');
    Route::get('/payments/history', [PaymentController::class, 'getPaymentHistory']);
});
