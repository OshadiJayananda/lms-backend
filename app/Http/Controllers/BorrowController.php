<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BorrowController extends Controller
{
    public function requestBook(Request $request, $bookId)
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

        return response()->json(['message' => 'Request approved successfully!']);
    }

    public function rejectRequest($borrowId)
    {
        $borrow = Borrow::findOrFail($borrowId);
        $borrow->status = 'Rejected';
        $borrow->save();

        // Optionally, increment the book copies if rejected
        $book = Book::findOrFail($borrow->book_id);
        $book->no_of_copies += 1;
        $book->save();

        return response()->json(['message' => 'Request rejected successfully!']);
    }
}
