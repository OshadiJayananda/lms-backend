<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Borrow;
use App\Models\BorrowingPolicy;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Log;
// use Carbon\Carbon;
// use App\Models\Notification;
// use App\Models\BookReservation;

class BookController extends Controller
{

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

    public function show(Book $book)
    {
        $book->load('author');

        return response()->json($book);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $data = $request->validated();

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

    public function destroy(Book $book)
    {
        $book->delete();
        return response()->json(['message' => 'Book deleted successfully!']);
    }

    public function checkIsbn(Request $request)
    {
        $isbn = $request->query('isbn');
        $exists = Book::where('isbn', $isbn)->exists();

        return response()->json(['exists' => $exists]);
    }

    public function getDashboardStats()
    {
        $totalBooks = Book::count();
        $totalMembers = User::role('user')->count();
        $borrowedBooks = Borrow::where('status', 'Issued')->count();
        $overdueBooks = Borrow::overdue()->count();

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

        // Use the same logic as in the Borrow model
        $overdue = $user->borrowedBooks()
            ->whereIn('status', ['Issued', 'Overdue', 'Confirmed', 'Returned'])
            ->whereNotNull('due_date')
            ->where('fine_paid', false)
            ->get()
            ->filter(function ($borrow) {
                if (!in_array($borrow->status, ['Issued', 'Overdue', 'Confirmed', 'Returned'])) {
                    return false;
                }

                if (!$borrow->due_date) {
                    return false;
                }

                if ($borrow->fine_paid) {
                    return false;
                }

                // If not returned yet, and now is past due_date
                if (!$borrow->returned_date && now()->greaterThan($borrow->due_date)) {
                    return true;
                }

                // If returned after due_date
                if ($borrow->returned_date && $borrow->returned_date->greaterThan($borrow->due_date)) {
                    return true;
                }

                return false;
            })->count();

        // Get current borrowing policy for dynamic values
        $policy = BorrowingPolicy::currentPolicy();
        $borrowLimit = $policy->borrowing_limit ?? 5;
        $borrowDuration = ($policy->borrow_duration_days ?? 14) . ' days';
        $finePerDay = $policy->fine_per_day ?? 50;

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
                    ->whereMonth('issued_date', $date->month)
                    ->whereYear('issued_date', $date->year)
                    ->count(),
                'returned' => $user->borrowedBooks()
                    ->whereIn('status', ['Returned', 'Confirmed'])
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
