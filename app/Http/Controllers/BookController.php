<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrow;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Display a listing of books.
     */
    public function index()
    {
        $books = Book::all();
        return response()->json($books, 200);
    }

    /**
     * Store a newly created book.
     */
    public function store(BookRequest $request)
    {
        $data = $request->validated();

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('book_images', 'public');
        }

        $book = Book::create($data);

        return response()->json(['message' => 'Book created successfully!', 'book' => $book], 201);
    }

    /**
     * Display the specified book.
     */
    public function show(Book $book)
    {
        return response()->json($book);
    }

    /**
     * Update the specified book.
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        // Validate the request data
        $data = $request->validated();

        // Handle Image Upload
        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($book->image) {
                Storage::delete('public/' . $book->image);
            }
            // Store the new image and get the relative path
            $data['image'] = $request->file('image')->store('book_images', 'public');
        } else {
            // If no new image is uploaded, retain the existing image
            $data['image'] = $book->image;
        }

        // Log the updated data
        Log::info('Updated Book Data:', $data);

        // Update the book record
        $book->update($data);

        // Return success response
        return response()->json(['message' => 'Book updated successfully!', 'book' => $book]);
    }
    /**
     * Remove the specified book.
     */
    public function destroy(Book $book)
    {
        $book->delete();
        return response()->json(['message' => 'Book deleted successfully!']);
    }

    /**
     * Search for books based on a query.
     */
    public function search(Request $request)
    {
        // Get the search query from the request
        $query = $request->query('q');

        // If no query is provided, return all books
        if (!$query) {
            return response()->json(Book::all());
        }

        // Perform the search
        $books = Book::where('name', 'like', "%$query%")
            ->orWhere('author', 'like', "%$query%")
            ->orWhere('isbn', 'like', "%$query%")
            ->get();

        return response()->json($books);
    }

    /**
     * Check if the ISBN is unique.
     */
    public function checkIsbn(Request $request)
    {
        // Get the ISBN from the request query
        $isbn = $request->query('isbn');

        // Check if the ISBN exists in the database
        $exists = Book::where('isbn', $isbn)->exists();

        // Return a JSON response indicating whether the ISBN exists
        return response()->json(['exists' => $exists]);
    }

    // Add to BookController.php
    public function reserveBook(Request $request, $bookId)
    {
        try {
            $request->validate([
                'reservation_date' => 'required|date|after_or_equal:today'
            ]);

            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $book = Book::find($bookId);
            if (!$book) {
                return response()->json(['message' => 'Book not found'], 404);
            }

            // Check for existing reservation
            $existing = BookReservation::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($existing) {
                return response()->json([
                    'message' => 'You already have an active reservation for this book'
                ], 400);
            }

            $reservation = BookReservation::create([
                'user_id' => $user->id,
                'book_id' => $bookId,
                'reservation_date' => $request->reservation_date,
                'expiry_date' => now()->addDays(7),
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Reservation submitted successfully',
                'reservation' => $reservation
            ], 201);
        } catch (\Exception $e) {
            Log::error('Reservation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReservations()
    {
        $reservations = BookReservation::with(['user', 'book'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reservations);
    }

    public function rejectReservation($reservationId)
    {
        $reservation = BookReservation::findOrFail($reservationId);
        $reservation->status = 'rejected';
        $reservation->save();

        // Create notification for user
        Notification::create([
            'user_id' => $reservation->user_id,
            'book_id' => $reservation->book_id,
            'message' => "Your reservation for {$reservation->book->name} has been rejected",
            'type' => 'reservation_rejected',
            'user_notified' => false,
            'admin_notified' => true
        ]);

        return response()->json(['message' => 'Reservation rejected']);
    }

    public function confirmBookGiven($reservationId)
    {
        $reservation = BookReservation::findOrFail($reservationId);

        if ($reservation->status !== 'approved') {
            return response()->json([
                'message' => 'Reservation must be approved first'
            ], 400);
        }

        $reservation->status = 'completed';
        $reservation->save();

        // Reduce book copies
        $reservation->book->decrement('no_of_copies');

        // Create notification for user
        Notification::create([
            'user_id' => $reservation->user_id,
            'book_id' => $reservation->book_id,
            'reservation_id' => $reservation->id,
            'title' => 'Book Ready for Pickup',  // Added title
            'message' => "Your book {$reservation->book->name} is ready for pickup",
            'type' => 'book_ready_for_pickup',  // Make sure this matches your enum
            'is_read' => false
        ]);

        return response()->json(['message' => 'Book confirmed as given to user']);
    }

    public function approveReservation($reservationId)
    {
        try {
            $reservation = BookReservation::with('book', 'user')->findOrFail($reservationId);
            $book = $reservation->book;
            $user = $reservation->user;

            if ($book->no_of_copies <= 0) {
                return response()->json([
                    'message' => 'Cannot approve reservation - no copies available'
                ], 400);
            }

            // Update reservation status but don't create borrow record yet
            $reservation->status = 'approved';
            $reservation->save();

            // Create notification for user to confirm
            Notification::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Approved',
                'message' => "Your reservation for {$book->name} has been approved. Do you still want this book?",
                'type' => 'reservation_approved',
                'is_read' => false
            ]);

            return response()->json([
                'message' => 'Reservation approved - waiting for user confirmation'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to approve reservation: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to approve reservation',
                'error' => $e->getMessage()
            ], 500);
        }
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
}
