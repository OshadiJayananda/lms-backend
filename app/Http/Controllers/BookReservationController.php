<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrow;
use App\Models\BorrowingPolicy;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->paginate(10);

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

        Notification::create([
            'user_id' => $reservation->user_id,
            'book_id' => $reservation->book_id,
            'reservation_id' => $reservation->id,
            'title' => 'Reservation Rejected',
            'message' => "Your reservation for {$reservation->book->name} has been rejected",
            'type' => Notification::TYPE_RESERVATION_REJECTED,
            'is_read' => false
        ]);

        return response()->json(['message' => 'Reservation rejected']);
    }

    public function confirmBookGiven($reservationId)
    {
        try {
            $reservation = BookReservation::with(['book', 'user'])->findOrFail($reservationId);

            if ($reservation->status !== 'approved') {
                return response()->json([
                    'message' => 'Reservation must be approved first'
                ], 400);
            }

            $policy = BorrowingPolicy::currentPolicy();
            $borrowDuration = $policy->borrow_duration_days ?? 14;

            $borrow = Borrow::create([
                'user_id' => $reservation->user_id,
                'book_id' => $reservation->book_id,
                'issued_date' => now(),
                'due_date' => now()->addDays($borrowDuration),
                'status' => 'Issued'
            ]);

            Notification::create([
                'user_id' => $reservation->user_id,
                'book_id' => $reservation->book_id,
                'title' => 'Book Issued',
                'message' => "Your book {$reservation->book->name} has been issued",
                'type' => Notification::TYPE_BOOK_ISSUED,
                'is_read' => false
            ]);

            // Delete the reservation
            $reservation->delete();

            return response()->json([
                'message' => 'Book confirmed as given to user',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to confirm book given: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to confirm book issuance',
                'error' => $e->getMessage()
            ], 500);
        }
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

            $reservation->status = 'approved';
            $reservation->save();

            Notification::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Approved',
                'message' => "Your reservation for {$book->name} has been approved. Please confirm if you still want this book.",
                'type' => Notification::TYPE_RESERVATION_APPROVED,
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
                'user_id' => 1,
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Confirmed',
                'message' => "User {$user->name} has confirmed reservation for {$book->name}",
                'type' => Notification::TYPE_BOOK_READY,
                'is_read' => false
            ]);

            $borrow = Borrow::create([
                'user_id' => $reservation->user_id,
                'book_id' => $reservation->book_id,
                'status' => 'Approved',
            ]);

            $reservation->book->decrement('no_of_copies');

            return response()->json([
                'message' => 'Admin has been notified',
                'borrow' => $borrow
            ]);
        } else {
            Notification::create([
                'user_id' => 1,
                'book_id' => $book->id,
                'reservation_id' => $reservation->id,
                'title' => 'Reservation Declined',
                'message' => "User {$user->name} has declined reservation for {$book->name}",
                'type' => Notification::TYPE_RESERVATION_REJECTED,
                'is_read' => false
            ]);

            $reservation->delete();

            // Check for pending reservations
            $pendingReservations = BookReservation::where('book_id', $book->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingReservations) {
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $book->id,
                    'title' => 'Pending Reservations',
                    'message' => "There are pending reservations for {$book->name}",
                    'type' => Notification::TYPE_ADMIN_ALERT,
                    'is_read' => false
                ]);
            }

            return response()->json(['message' => 'Reservation cancelled']);
        }
    }

    public function getPendingReservations($bookId)
    {
        try {
            $pendingReservations = BookReservation::where('book_id', $bookId)
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'count' => $pendingReservations,
                'message' => $pendingReservations > 0
                    ? 'There are pending reservations for this book'
                    : 'No pending reservations for this book'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch pending reservations: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch pending reservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy($reservationId)
    {
        DB::beginTransaction();
        try {
            $reservation = BookReservation::findOrFail($reservationId);

            // Only allow deletion of rejected reservations
            if ($reservation->status !== 'rejected') {
                return response()->json([
                    'message' => 'Only rejected reservations can be deleted'
                ], 422);
            }

            // Delete related notifications first
            Notification::where('reservation_id', $reservationId)->delete();

            $reservation->delete();

            DB::commit();

            return response()->json(['message' => 'Reservation deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete reservation: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
