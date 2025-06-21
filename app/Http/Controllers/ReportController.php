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
                    ->withCount(['borrowedBooks' => function ($q) use ($fromDate, $toDate) {
                        if ($fromDate) $q->where('issued_date', '>=', $fromDate);
                        if ($toDate) $q->where('issued_date', '<=', $toDate);
                    }]);

                $data['members'] = $query->latest()->get();
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
                $title = 'Overdue Books Report';
                $query = Borrow::overdueBooks()
                    ->with(['user', 'book']);

                if ($fromDate) $query->where('issued_date', '>=', $fromDate);
                if ($toDate) $query->where('issued_date', '<=', $toDate);

                $data['overdueBooks'] = $query->get()
                    ->map(function ($borrowing) {
                        $borrowing->days_overdue = now()->diffInDays(Carbon::parse($borrowing->due_date));
                        $borrowing->calculated_fine = $borrowing->days_overdue * $borrowing->fine_per_day;
                        return $borrowing;
                    });
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
