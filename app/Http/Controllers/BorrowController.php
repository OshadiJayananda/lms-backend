<?php

namespace App\Http\Controllers;

use App\Mail\BookApprovalMail;
use App\Mail\BookIssuedMail;
use App\Models\Borrow;
use App\Models\Book;
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

        return response()->json(['message' => 'Return confirmed successfully!']);
    }
}
