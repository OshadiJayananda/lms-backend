<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Borrow;
use App\Models\Category;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Display a listing of books.
     */
    public function index(Request $request)
    {
        $query = $request->query('q');
        $categoryId = $request->query('category');
        $categoryId = (int)$categoryId;
        $perPage = $request->query('per_page', 10); // Default to 10 items per page

        $booksQuery = Book::with('author');

        if (!empty($categoryId)) {
            $category = Category::find($categoryId);

            if ($category && $category->childCategories()->exists()) {
                $subCategoryIds = $category->childCategories->pluck('id')->toArray();
                $subCategoryIds[] = $categoryId;

                $booksQuery->whereIn('category_id', $subCategoryIds);
            } else {
                $booksQuery->where('category_id', $categoryId);
            }
        }

        if ($query) {
            $booksQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                    ->orWhere('isbn', 'like', "%$query%")
                    ->orWhereHas('author', function ($q2) use ($query) {
                        $q2->where('name', 'like', "%$query%");
                    });
            });
        }

        $books = $booksQuery->paginate($perPage);

        return response()->json($books);
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
        $book->load('author');

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
        }

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

    public function getDashboardStats()
    {
        $totalBooks = Book::count();
        $totalMembers = User::role('user')->count();
        $borrowedBooks = Borrow::where('status', 'Issued')->count();
        $overdueBooks = Borrow::overdueBooks()->count();

        // Books Borrowed Per Month (last 6 months)
        $borrowedPerMonth = Borrow::selectRaw('MONTH(issued_date) as month, COUNT(*) as count')
            ->whereYear('issued_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top Borrowing Members (by total borrows)
        $topMembers = User::role('user')
            ->select('id', 'name')
            ->withCount('borrowedBooks')
            ->orderByDesc('borrowed_books_count')
            ->limit(5)
            ->get();

        // Most Borrowed Books
        $topBooks = Book::select('id', 'name')
            ->withCount('borrows')
            ->orderByDesc('borrows_count')
            ->limit(5)
            ->get();

        // Recent Book Requests
        $recentRequests = Borrow::with(['user', 'book'])->where('status', 'Pending')
            ->latest()
            ->take(5)
            ->get();

        // Recently Added Books
        $recentBooks = Book::latest()->take(5)->get();

        // Recent Members
        $recentMembers = User::role('user')->latest()->take(5)->get();

        return response()->json([
            'totalBooks' => $totalBooks,
            'totalMembers' => $totalMembers,
            'borrowedBooks' => $borrowedBooks,
            'overdueBooks' => $overdueBooks,
            'borrowedPerMonth' => $borrowedPerMonth,
            'topMembers' => $topMembers,
            'topBooks' => $topBooks,
            'recentRequests' => $recentRequests,
            'recentBooks' => $recentBooks,
            'recentMembers' => $recentMembers,
        ]);
    }

    public function getUserDashboardStats(Request $request)
    {
        $user = auth()->user();

        $active_borrowed = $user->borrowedBooks()->where('status', 'Issued')->count();
        $borrowed = $user->borrowedBooks()->count();
        $returned = $user->borrowedBooks()->whereIn('status', ['Returned', 'Confirmed'])->count();
        $overdue = $user->overdueBooksCount();

        $borrowLimit = 5;
        $borrowDuration = '2 weeks';
        $finePerDay = 50;

        $latestBooks = Book::latest()->take(5)->select('id', 'name', 'image')->get();

        // Get monthly stats for the last 6 months
        $monthlyStats = [];

        $createdAt = $user->created_at->startOfMonth();
        $currentMonth = now()->startOfMonth();

        $diffInMonths = $createdAt->diffInMonths($currentMonth);
        $monthsToShow = min($diffInMonths + 1, 6);
        for ($i = $monthsToShow - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthName = $date->format('M Y');

            $monthlyStats[] = [
                'month' => $monthName,
                'borrowed' => $user->borrowedBooks()
                    // ->where('status', 'Issued')
                    ->whereMonth('issued_date', $date->month)
                    ->whereYear('issued_date', $date->year)
                    ->count(),
                'returned' => $user->returnedBooks()
                    // ->where('status', 'Returned')
                    ->whereMonth('returned_date', $date->month)
                    ->whereYear('returned_date', $date->year)
                    ->count(),
            ];
        }

        return response()->json([
            'active_borrowed' => $active_borrowed,
            'borrowed' => $borrowed,
            'returned' => $returned,
            'overdue' => $overdue,
            'borrowLimit' => $borrowLimit,
            'borrowDuration' => $borrowDuration,
            'finePerDay' => $finePerDay,
            'latestBooks' => $latestBooks,
            'monthlyStats' => $monthlyStats,
        ]);
    }
}
