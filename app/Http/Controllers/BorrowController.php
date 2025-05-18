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

        // Check if user already has a pending request for this book
        $existingRequest = Borrow::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('status', 'Pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'message' => 'You already have a pending request for this book'
            ], 400);
        }

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
            ->whereIn('status', ['Issued', 'Renewed', 'Pending'])
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
            ->where('status', 'Issued')
            ->latest()
            ->firstOrFail();

        // Update the status and set returned date
        $borrow->update([
            'status' => 'Returned',
            'returned_date' => now() // Add this line
        ]);

        // Increment the book's available copies count
        $book = Book::find($bookId);
        if ($book) {
            $book->increment('no_of_copies');
        }

        return response()->json([
            'message' => 'Book returned successfully!',
            'data' => [
                'returned_date' => $borrow->fresh()->returned_date
            ]
        ]);
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

    public function createBorrowFromReservation($reservationId)
    {
        $reservation = BookReservation::with('book', 'user')->findOrFail($reservationId);
        $book = $reservation->book;
        $user = $reservation->user;

        if ($book->no_of_copies <= 0) {
            return response()->json([
                'message' => 'No copies available'
            ], 400);
        }

        // Create borrow record
        $borrow = Borrow::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'issued_date' => now(),
            'due_date' => now()->addWeeks(2),
            'status' => 'Issued'
        ]);

        // Reduce book copies
        $book->decrement('no_of_copies');

        return response()->json([
            'message' => 'Borrow record created',
            'borrow' => $borrow
        ]);
    }
    // public function checkBookAvailability($bookId)
    // {
    //     $book = Book::findOrFail($bookId);
    //     return response()->json([
    //         'available' => $book->no_of_copies > 0,
    //         'copies_available' => $book->no_of_copies
    //     ]);
    // }


    // public function checkAvailabilityNotifications()
    // {
    //     $notifications = BookAvailabilityNotification::with(['user', 'book'])
    //         ->where('notified', false)
    //         ->get();

    //     return response()->json($notifications);
    // }

    // public function notifyAvailableBooks($bookId)
    // {
    //     $book = Book::findOrFail($bookId);
    //     $notifications = BookAvailabilityNotification::with('user')
    //         ->where('book_id', $bookId)
    //         ->where('notified', false)
    //         ->get();

    //     foreach ($notifications as $notification) {
    //         Mail::to($notification->user->email)
    //             ->send(new BookAvailableNotification($book, $notification->requested_date));

    //         $notification->notified = true;
    //         $notification->save();
    //     }

    //     return response()->json(['message' => 'Users notified about book availability']);
    // }


}
