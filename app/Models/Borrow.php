<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Borrow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'issued_date',
        'due_date',
        'returned_date',
        'status',
        'fine_paid',
    ];

    protected $appends = ['is_overdue', 'fine'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    public function getFineAttribute(): float
    {
        return $this->calculateFine();
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['Issued', 'Confirmed', 'Overdue']) &&
            $this->due_date &&
            now()->greaterThan($this->due_date);
    }

    public function calculateFine(): float
    {
        if (!$this->isOverdue()) {
            return 0.00;
        }

        $policy = BorrowingPolicy::currentPolicy();

        $today = Carbon::today();

        $dueDate = Carbon::parse($this->due_date)->startOfDay();
        $daysOverdue = $dueDate->diffInDays($today, false);

        return round($daysOverdue * $policy->fine_per_day, 2);
    }
}
