<?php

namespace App\Http\Controllers;

use App\Mail\BookApprovalMail;
use App\Mail\BookAvailableNotification;
use App\Mail\BookIssuedMail;
use App\Mail\RenewalApprovedMail;
use App\Mail\RenewalRejectedMail;
use App\Models\Borrow;
use App\Models\Book;
use App\Models\BookAvailabilityNotification;
use App\Models\BookReservation;
use App\Models\Notification;
use App\Models\RenewRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BorrowController extends Controller
{
    public function requestBook($bookId)
    {
        $user = Auth::user();
        $book = Book::findOrFail($bookId);

        if ($book->no_of_copies > 0) {
            $book->no_of_copies -= 1;
            $book->save();

            Borrow::create([
                'user_id' => $user->id,
                'book_id' => $bookId,
                'issued_date' => now(),
                'due_date' => now()->addDays(30),
                'status' => 'Pending',
            ]);

            return response()->json(['message' => 'Book requested successfully!']);
        } else {
            return response()->json(['message' => 'No copies available'], 400);
        }
    }

    public function getBorrowedBooks()
    {
        $user = Auth::user();
        $borrowedBooks = Borrow::with('book')
            ->where('user_id', $user->id)
            ->whereIn('status', ['Issued', 'Renewed']) // Include renewed books
            ->get();

        return response()->json($borrowedBooks);
    }

    public function getPendingRequests()
    {
        $pendingRequests = Borrow::with(['user', 'book'])->where('status', 'Pending')->get();
        return response()->json($pendingRequests);
    }

    public function approveRequest($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);
        $borrow->status = 'Approved';
        $borrow->save();

        // Send email to user
        $user = User::findOrFail($borrow->user_id);
        $book = Book::findOrFail($borrow->book_id);

        Mail::to($user->email)->send(new BookApprovalMail($book, $borrow));

        return response()->json(['message' => 'Request approved successfully!']);
    }

    public function rejectRequest($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);
        $borrow->status = 'Rejected';
        $borrow->save();

        // Increment the book copies if rejected
        $book = Book::findOrFail($borrow->book_id);
        $book->no_of_copies += 1;
        $book->save();

        return response()->json(['message' => 'Request rejected successfully!']);
    }

    public function confirmBookGiven($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);

        // Update status and set due date to 2 weeks from now
        $borrow->status = 'Issued';
        $borrow->issued_date = now(); // Set the issued date to now
        $borrow->due_date = now()->addWeeks(2); // Set due date to 2 weeks from now
        $borrow->save();

        // Send email to user
        $user = User::findOrFail($borrow->user_id);
        $book = Book::findOrFail($borrow->book_id);

        Mail::to($user->email)->send(new BookIssuedMail($book, $borrow));

        return response()->json(['message' => 'Book issued successfully!']);
    }

    public function returnBook($bookId)
    {
        $borrow = Borrow::where('book_id', $bookId)
            ->where('user_id', auth()->id())
            ->latest()
            ->firstOrFail();
        // Check if the book is issued and can be returned
        if ($borrow->status !== 'Issued') {
            return response()->json(['message' => 'This book cannot be returned.'], 400);
        }

        // Update the status to "Returned"
        $borrow->status = 'Returned';
        $borrow->save();


        return response()->json(['message' => 'Book returned successfully!']);
    }

    public function getReturnedBooks()
    {
        $returnedBooks = Borrow::with(['user', 'book'])->where('status', 'Returned')->get();
        return response()->json($returnedBooks);
    }

    public function getAllBorrowedBooks(Request $request)
    {
        $query = $request->query('q'); // Get the search query from the request

        $borrowedBooks = Borrow::with(['user', 'book'])
            ->when($query, function ($q) use ($query) {
                $q->whereHas('book', function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%") // Search by book name
                        ->orWhere('id', 'like', "%{$query}%"); // Search by book ID
                })
                    ->orWhereHas('user', function ($q) use ($query) {
                        $q->where('id', 'like', "%{$query}%") // Search by user ID
                            ->orWhere('name', 'like', "%{$query}%"); // Search by user name
                    });
            })
            ->get();

        return response()->json($borrowedBooks);
    }

    public function renewBook(Request $request, $bookId)
    {
        $request->validate([
            'renewDate' => 'required|date|after_or_equal:today|before_or_equal:today +30 days',
        ]);

        $borrow = Borrow::where('book_id', $bookId)
            ->where('user_id', auth()->id())
            ->where('status', 'Issued')
            ->firstOrFail();

        $borrow->due_date = $request->renewDate;
        $borrow->save();

        return response()->json(['message' => 'Book renewed successfully!']);
    }

    public function checkBookAvailability($bookId)
    {
        $book = Book::findOrFail($bookId);
        return response()->json([
            'available' => $book->no_of_copies > 0,
            'copies_available' => $book->no_of_copies
        ]);
    }

    public function renewRequest(Request $request, $bookId)
    {
        $request->validate([
            'renewDate' => 'required|date|after_or_equal:today'
        ]);

        $borrow = Borrow::where('book_id', $bookId)
            ->where('user_id', auth()->id())
            ->where('status', 'Issued')
            ->firstOrFail();

        $existingRequest = RenewRequest::where('borrow_id', $borrow->id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'A renewal request is already pending for this book. Please wait for approval.'], 422);
        }

        $renewRequest = RenewRequest::create([
            'borrow_id' => $borrow->id,
            'user_id' => auth()->id(),
            'book_id' => $bookId,
            'current_due_date' => $borrow->due_date,
            'requested_date' => $request->renewDate,
            'status' => 'pending'
        ]);

        // Create notification for admin
        Notification::create([
            'user_id' => 1,
            'book_id' => $bookId,
            'renew_request_id' => $renewRequest->id,
            'title' => 'New Renewal Request',
            'message' => "User has requested to renew book '{$borrow->book->name}' until {$request->renewDate}",
            'type' => Notification::TYPE_RENEWAL_REQUEST,
            'is_read' => false,
            'metadata' => [
                'request_id' => $renewRequest->id,
                'current_due_date' => $borrow->due_date,
                'requested_date' => $request->renewDate
            ]
        ]);

        return response()->json(['message' => 'Renewal request submitted for admin approval']);
    }

    public function notifyAdmin(Request $request, $bookId)
    {
        $request->validate([
            'requestedDate' => 'required|date'
        ]);

        $user = auth()->user();
        $book = Book::findOrFail($bookId);

        // Create notification
        BookAvailabilityNotification::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'requested_date' => $request->requestedDate,
            'notified' => false
        ]);

        return response()->json(['message' => 'Admin will be notified when copies become available']);
    }

    public function getRenewRequests()
    {
        try {
            $requests = RenewRequest::with([
                'user',
                'book',
                'book.reservations' => function ($query) {
                    $query->where('status', 'pending');
                }
            ])
                ->where('status', 'pending')
                ->get();

            return response()->json($requests);
        } catch (\Exception $e) {
            Log::error('Failed to fetch renew requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch renewal requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approveRenewRequest(Request $request, $requestId)
    {
        $request->validate([
            'admin_proposed_date' => 'required|date|after_or_equal:today'
        ]);

        $renewRequest = RenewRequest::with(['borrow', 'user', 'book'])->findOrFail($requestId);

        // Update with admin's proposed date
        $renewRequest->update([
            'admin_proposed_date' => $request->admin_proposed_date,
            'status' => 'pending_user_confirmation'
        ]);

        // Notify user about the proposed date
        Notification::create([
            'user_id' => $renewRequest->user_id,
            'book_id' => $renewRequest->book_id,
            'renew_request_id' => $renewRequest->id,
            'title' => 'Renewal Date Change Request',
            'message' => "Admin has proposed a new renewal date for '{$renewRequest->book->name}': {$request->admin_proposed_date}",
            'type' => Notification::TYPE_RENEWAL_DATE_CHANGED,
            'is_read' => false,
            'metadata' => [
                'request_id' => $renewRequest->id,
                'proposed_date' => $request->admin_proposed_date,
                'current_due_date' => $renewRequest->current_due_date
            ]
        ]);

        return response()->json(['message' => 'User has been notified to confirm the new date']);
    }

    public function rejectRenewRequest($requestId)
    {
        $renewRequest = RenewRequest::findOrFail($requestId);
        $renewRequest->status = 'rejected';
        $renewRequest->save();

        // Notify user
        Mail::to($renewRequest->user->email)
            ->send(new RenewalRejectedMail($renewRequest));

        return response()->json(['message' => 'Renewal rejected']);
    }

    // Admin method to check for availability notifications
    public function checkAvailabilityNotifications()
    {
        $notifications = BookAvailabilityNotification::with(['user', 'book'])
            ->where('notified', false)
            ->get();

        return response()->json($notifications);
    }

    // Method to notify users when books become available
    public function notifyAvailableBooks($bookId)
    {
        $book = Book::findOrFail($bookId);
        $notifications = BookAvailabilityNotification::with('user')
            ->where('book_id', $bookId)
            ->where('notified', false)
            ->get();

        foreach ($notifications as $notification) {
            Mail::to($notification->user->email)
                ->send(new BookAvailableNotification($book, $notification->requested_date));

            $notification->notified = true;
            $notification->save();
        }

        return response()->json(['message' => 'Users notified about book availability']);
    }

    public function confirmReturn($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);

        if ($borrow->status !== 'Returned') {
            return response()->json(['message' => 'This book has not been returned.'], 400);
        }

        $borrow->status = 'Confirmed';
        $borrow->save();

        // Increment the book copies
        $book = Book::findOrFail($borrow->book_id);
        $book->no_of_copies += 1;
        $book->save();

        // Check if there are pending reservations for this book
        $pendingReservations = BookReservation::with('user')
            ->where('book_id', $book->id)
            ->where('status', 'pending')
            ->get();

        if ($pendingReservations->count() > 0) {
            foreach ($pendingReservations as $reservation) {
                // Create notification for admin
                Notification::create([
                    'user_id' => $reservation->user_id,
                    'book_id' => $book->id,
                    'reservation_id' => $reservation->id,
                    'title' => "Book Now Available: {$book->name}",
                    'message' => "The book '{$book->name}' is now available. You have a pending reservation for this book.",
                    'type' => 'book_available',
                    'is_read' => false
                ]);

                // Create notification for admin
                Notification::create([
                    'user_id' => 1, // Assuming admin has ID 1
                    'book_id' => $book->id,
                    'reservation_id' => $reservation->id,
                    'title' => "Book Available with Pending Reservation",
                    'message' => "Book '{$book->name}' is now available with {$pendingReservations->count()} pending reservation(s).",
                    'type' => 'admin_alert',
                    'is_read' => false
                ]);
            }
        }

        return response()->json(['message' => 'Return confirmed successfully!']);
    }

    public function respondToReservation(Request $request, $reservationId)
    {
        $request->validate([
            'confirm' => 'required|boolean'
        ]);

        $reservation = BookReservation::findOrFail($reservationId);
        $book = Book::findOrFail($reservation->book_id);

        if ($request->confirm) {
            // User confirms they want the book
            if ($book->no_of_copies > 0) {
                // Create borrow record
                $borrow = Borrow::create([
                    'user_id' => $reservation->user_id,
                    'book_id' => $reservation->book_id,
                    'issued_date' => now(),
                    'due_date' => now()->addWeeks(2),
                    'status' => 'Issued'
                ]);

                // Reduce book copies
                $book->decrement('no_of_copies');

                // Notify admin
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $book->id,
                    'reservation_id' => $reservation->id,
                    'title' => 'Reservation Confirmed',
                    'message' => "User has confirmed reservation for {$book->name}",
                    'type' => 'reservation_confirmed',
                    'is_read' => false
                ]);

                // Delete reservation
                $reservation->delete();

                return response()->json(['message' => 'Book issued successfully']);
            } else {
                return response()->json(['message' => 'No copies available'], 400);
            }
        } else {
            // User declines the reservation
            Notification::create([
                'user_id' => 1, // Admin ID
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Declined',
                'message' => "User has declined reservation for {$book->name}",
                'type' => 'reservation_declined',
                'is_read' => false
            ]);

            // Delete reservation
            $reservation->delete();

            return response()->json(['message' => 'Reservation cancelled']);
        }
    }
    public function handleReservationResponse(Request $request, $reservationId)
    {
        $request->validate([
            'confirm' => 'required|boolean'
        ]);

        $reservation = BookReservation::findOrFail($reservationId);
        $book = $reservation->book;
        $user = $reservation->user;

        if ($request->confirm) {
            // User confirms they want the book
            Notification::create([
                'user_id' => 1, // Admin ID
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Confirmed',
                'message' => "User {$user->name} has confirmed reservation for {$book->name}",
                'type' => 'reservation_confirmed', // Matches enum
                'is_read' => false
            ]);

            return response()->json(['message' => 'Admin has been notified']);
        } else {
            // User declines the reservation
            Notification::create([
                'user_id' => 1, // Admin ID
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Declined',
                'message' => "User {$user->name} has declined reservation for {$book->name}",
                'type' => 'reservation_rejected', // Changed to match enum
                'is_read' => false
            ]);

            // Delete reservation
            $reservation->delete();

            // Check if there are other pending reservations
            $pendingReservations = BookReservation::where('book_id', $book->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingReservations) {
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $book->id,
                    'title' => 'Pending Reservations',
                    'message' => "There are pending reservations for {$book->name}",
                    'type' => 'admin_alert',
                    'is_read' => false
                ]);
            }

            return response()->json(['message' => 'Reservation cancelled']);
        }
    }

    public function confirmRenewalDate(Request $request, $requestId)
    {
        $request->validate([
            'confirm' => 'required|boolean'
        ]);

        DB::beginTransaction();
        try {
            $renewRequest = RenewRequest::with(['borrow', 'user', 'book'])->findOrFail($requestId);

            if ($request->confirm) {
                // User accepted the date change
                $newDueDate = $renewRequest->admin_proposed_date ?? $renewRequest->requested_date;

                $renewRequest->borrow->update([
                    'due_date' => $newDueDate,
                    'status' => 'Renewed' // Update status to Renewed
                ]);

                $renewRequest->update([
                    'status' => 'approved',
                    'processed_at' => now()
                ]);

                // Notify admin
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $renewRequest->book_id,
                    'renew_request_id' => $renewRequest->id,
                    'title' => 'Renewal Confirmed',
                    'message' => "User has confirmed the renewal date change for {$renewRequest->book->name}",
                    'type' => Notification::TYPE_RENEWAL_CONFIRMED,
                    'is_read' => false,
                    'metadata' => [
                        'new_due_date' => $newDueDate,
                        'book_id' => $renewRequest->book_id,
                        'user_id' => $renewRequest->user_id
                    ]
                ]);

                // Send email confirmation
                Mail::to($renewRequest->user->email)
                    ->send(new RenewalApprovedMail($renewRequest));
            } else {
                // User rejected the date change
                $renewRequest->update([
                    'status' => 'rejected',
                    'processed_at' => now()
                ]);

                // Notify admin
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $renewRequest->book_id,
                    'renew_request_id' => $renewRequest->id,
                    'title' => 'Renewal Declined',
                    'message' => "User has declined the renewal date change for {$renewRequest->book->name}",
                    'type' => Notification::TYPE_RENEWAL_DECLINED,
                    'is_read' => false
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => $request->confirm
                    ? 'Renewal confirmed successfully'
                    : 'Renewal declined',
                'due_date' => $request->confirm ? $newDueDate : null,
                'status' => $request->confirm ? 'Renewed' : null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Renewal confirmation failed: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process renewal confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
