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
        $borrowedBooks = Borrow::with('book')->where('user_id', $user->id)->get();

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

    // public function confirmReturn($borrowId)
    // {
    //     $borrow = Borrow::findOrFail($borrowId);

    //     if ($borrow->status !== 'Returned') {
    //         return response()->json(['message' => 'This book has not been returned.'], 400);
    //     }

    //     $borrow->status = 'Confirmed';
    //     $borrow->save();

    //     // Increment the book copies
    //     $book = Book::findOrFail($borrow->book_id);
    //     $book->no_of_copies += 1;
    //     $book->save();

    //     return response()->json(['message' => 'Return confirmed successfully!']);
    // }

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

        $book = Book::findOrFail($bookId);

        if ($book->no_of_copies <= 0) {
            return response()->json([
                'message' => 'No copies available for renewal'
            ], 400);
        }

        // Create renew request
        RenewRequest::create([
            'borrow_id' => $borrow->id,
            'user_id' => auth()->id(),
            'book_id' => $bookId,
            'current_due_date' => $borrow->due_date,
            'requested_date' => $request->renewDate,
            'status' => 'pending'
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
        $requests = RenewRequest::with(['user', 'book'])
            ->where('status', 'pending')
            ->get();

        return response()->json($requests);
    }

    public function approveRenewRequest(Request $request, $requestId)
    {
        $request->validate([
            'dueDate' => 'required|date'
        ]);

        $renewRequest = RenewRequest::findOrFail($requestId);
        $borrow = Borrow::findOrFail($renewRequest->borrow_id);

        // Update borrow record
        $borrow->due_date = $request->dueDate;
        $borrow->save();

        // Update request status
        $renewRequest->status = 'approved';
        $renewRequest->save();

        // Notify user
        Mail::to($renewRequest->user->email)
            ->send(new RenewalApprovedMail($renewRequest, $request->dueDate));

        return response()->json(['message' => 'Renewal approved successfully']);
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
}
