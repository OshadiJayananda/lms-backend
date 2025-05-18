<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrow;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookReservationController extends Controller
{

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

    public function getUserReservationList()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $reservations = BookReservation::where('user_id', $user->id)
            ->latest()
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
            'title' => 'Reservation Rejected',
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

        // Delete the reservation
        $reservation->delete();

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
