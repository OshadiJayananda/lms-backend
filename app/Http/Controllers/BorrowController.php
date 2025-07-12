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
use App\Models\BorrowingPolicy;
use App\Models\Notification;
use App\Models\RenewRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;

class BorrowController extends Controller
{
    public function requestBook($bookId)
    {
        $user = Auth::user();
        $book = Book::findOrFail($bookId);

        // Check if the user has overdue books
        if ($user->overdueBooksCount() > 0) {
            return response()->json([
                'message' => 'You have overdue books. Please return them before borrowing new ones.'
            ], 400);
        }

        // Check if the user has reached the borrowing limit
        $borrowingLimit = BorrowingPolicy::currentPolicy()->borrowing_limit ?? 5;
        $borrowedCount = Borrow::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Issued'])
            ->count();

        if ($borrowedCount >= $borrowingLimit) {
            return response()->json([
                'message' => 'You have reached your borrowing limit'
            ], 400);
        }

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
                'status' => 'Pending',
            ]);

            return response()->json(['message' => 'Book requested successfully!']);
        } else {
            return response()->json(['message' => 'No copies available'], 400);
        }
    }

    public function getBorrowedBooks(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 10);
        $searchQuery = $request->input('search', '');
        $status = $request->input('status', '');

        $query = Borrow::with('book', 'book.author')
            ->where('user_id', $user->id);

        // Apply search filter
        if ($searchQuery) {
            $query->whereHas('book', function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('id', 'like', "%{$searchQuery}%");
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $borrowedBooks = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($borrowedBooks);
    }

    public function getPendingRequests()
    {
        $pendingRequests = Borrow::with(['user', 'book'])->whereIn('status', ['Pending', 'Approved'])->paginate(10);
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

        Notification::create([
            'user_id' => $borrow->user_id,
            'book_id' => $borrow->book_id,
            'title' => 'Book Request Approved',
            'message' => "Your request for '{$book->name}' has been approved. You can now collect the book from the library.",
            'type' => 'book_approved',
            'is_read' => false
        ]);

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

        Notification::create([
            'user_id' => $borrow->user_id,
            'book_id' => $borrow->book_id,
            'title' => 'Book Request Rejected',
            'message' => "Your request for '{$book->name}' has been rejected. Try to borrow Book on next time",
            'type' => 'book_rejected',
            'is_read' => false
        ]);

        return response()->json(['message' => 'Request rejected successfully!']);
    }

    public function confirmBookGiven($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);

        // Get current borrowing policy
        $policy = BorrowingPolicy::currentPolicy();
        $borrowDuration = $policy->borrow_duration_days ?? 14;

        // Update status and set due date based on policy
        $borrow->status = 'Issued';
        $borrow->issued_date = now();
        $borrow->due_date = now()->addDays($borrowDuration);
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
            ->whereIn('status', ['Issued', 'Overdue'])
            ->latest()
            ->firstOrFail();

        // Update the status and set returned date
        $borrow->update([
            'status' => 'Returned',
            'returned_date' => now() // Add this line
        ]);

        return response()->json([
            'message' => 'Book returned successfully!',
            'data' => [
                'returned_date' => $borrow->fresh()->returned_date
            ]
        ]);
    }

    public function getReturnedBooks()
    {
        $returnedBooks = Borrow::with(['user', 'book'])->where('status', 'Returned')->paginate(10);
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

    // In app/Http/Controllers/BorrowController.php

    public function getOverdueBooks()
    {
        $user = Auth::user();
        $overdueBooks = Borrow::with(['user', 'book'])
            ->where('user_id', $user->id)
            ->overdue() // Using the standardized scope
            ->get()
            ->map(function ($borrow) {
                $borrow->is_overdue = true;
                $borrow->fine_amount = $borrow->calculateFine();
                return $borrow;
            });

        return response()->json($overdueBooks);
    }



    public function markAsOverdue()
    {
        $overdueBooks = Borrow::where('status', 'Issued')
            ->where('due_date', '<', now())
            ->where('fine_paid', false)
            ->update(['status' => 'Overdue']);

        return [
            'count' => $overdueBooks,
            'timestamp' => now()
        ];
    }
    public function destroy($id)
    {
        $borrow = Borrow::findOrFail($id);

        // If the book was issued but not returned, increment the book copies
        if (in_array($borrow->status, ['Issued', 'Overdue'])) {
            $book = Book::find($borrow->book_id);
            if ($book) {
                $book->increment('no_of_copies');
            }
        }

        $borrow->delete();

        return response()->json(['message' => 'Borrow record deleted successfully']);
    }
}
