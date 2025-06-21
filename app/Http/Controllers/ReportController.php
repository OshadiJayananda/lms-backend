<?php
// app/Http/Controllers/ReportController.php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\User;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function generateReport($type)
    {
        $data = [];
        $title = '';

        // Get date range filters from request
        $fromDate = request()->input('from_date', null);
        $toDate = request()->input('to_date', null);

        switch ($type) {
            case 'books':
                $title = 'Books Report';
                $query = Book::withCount(['borrows' => function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) $q->where('issued_date', '>=', $fromDate);
                    if ($toDate) $q->where('issued_date', '<=', $toDate);
                }]);

                $data['books'] = $query->latest()->get();
                break;

            case 'members':
                $title = 'Members Report';
                $query = User::role('user')
                    ->withCount([
                        'borrowedBooks as borrowed_books_count' => function ($q) use ($fromDate, $toDate) {
                            if ($fromDate) $q->where('issued_date', '>=', $fromDate);
                            if ($toDate) $q->where('issued_date', '<=', $toDate);
                        },
                        'returnedBooks as returned_books_count' => function ($q) use ($fromDate, $toDate) {
                            if ($fromDate) $q->where('issued_date', '>=', $fromDate);
                            if ($toDate) $q->where('issued_date', '<=', $toDate);
                        }
                    ]);

                $data['members'] = $query->latest()->get()->each(function ($user) {
                    $user->status = $user->overdueBooksCount() > 0 ? 'Blocked' : 'Active';
                });
                break;

            case 'borrowings':
                $title = 'Borrowings Report';
                $query = Borrow::with(['user', 'book'])
                    ->where('status', '!=', 'Pending');

                if ($fromDate) $query->where('issued_date', '>=', $fromDate);
                if ($toDate) $query->where('issued_date', '<=', $toDate);

                $data['borrowings'] = $query->latest()->get();
                break;

            case 'overdue':
                try {
                    $title = 'Overdue Books Report';

                    // Validate dates
                    $fromDate = request('from_date') ? Carbon::parse(request('from_date'))->startOfDay() : null;
                    $toDate = request('to_date') ? Carbon::parse(request('to_date'))->endOfDay() : null;

                    // Base query with proper relationship loading
                    $query = Borrow::with([
                        'user:id,name',
                        'book:id,name,isbn',
                        'payments' => fn($q) => $q->completed()->select(['id', 'borrow_id', 'amount'])
                    ])
                        ->whereIn('status', ['Issued', 'Overdue', 'Confirmed', 'Returned'])
                        ->whereNotNull('due_date')
                        ->where('fine_paid', false);

                    // Apply date filters
                    if ($fromDate) {
                        $query->where('issued_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $query->where('issued_date', '<=', $toDate);
                    }

                    // Get and process results
                    $overdueBooks = $query->get()
                        ->filter(function ($borrow) {
                            return $borrow->isOverdue();
                        })
                        ->map(function ($borrow) {
                            $daysOverdue = now()->diffInDays($borrow->due_date, false);
                            $finePerDay = optional(BorrowingPolicy::currentPolicy())->fine_per_day ?? 10; // Default fallback

                            return [
                                'id' => $borrow->id,
                                'book' => $borrow->book,
                                'user' => $borrow->user,
                                'issued_date' => $borrow->issued_date,
                                'due_date' => $borrow->due_date,
                                'status' => $borrow->status,
                                'days_overdue' => $daysOverdue,
                                'calculated_fine' => $daysOverdue * $finePerDay,
                                'paid_fine_amount' => $borrow->payments->sum('amount'),
                            ];
                        });

                    $data = [
                        'overdueBooks' => $overdueBooks->values(),
                        'fromDate' => $fromDate?->toDateString(),
                        'toDate' => $toDate?->toDateString(),
                        'title' => $title,
                        'generatedAt' => now()->format('Y-m-d H:i:s')
                    ];

                    return response()->json($data);
                } catch (\Exception $e) {
                    \Log::error('Overdue report error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                    return response()->json([
                        'error' => 'Failed to generate report',
                        'details' => $e->getMessage()
                    ], 500);
                }
                break;
        }

        // Add date range to title if provided
        if ($fromDate || $toDate) {
            $title .= ' (' . ($fromDate ? Carbon::parse($fromDate)->format('M d, Y') : 'Start') . ' to ' .
                ($toDate ? Carbon::parse($toDate)->format('M d, Y') : 'End') . ')';
        }

        $data['title'] = $title;
        $data['generatedAt'] = Carbon::now()->format('M d, Y h:i A');

        $pdf = Pdf::loadView('reports.' . $type, $data);
        return $pdf->download($title . ' - ' . now()->format('Y-m-d') . '.pdf');
    }
}
