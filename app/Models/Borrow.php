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

    protected $casts = [
        'issued_date' => 'datetime',
        'due_date' => 'datetime',
        'returned_date' => 'datetime',
    ];

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
        // Only apply logic for these statuses
        if (!in_array($this->status, ['Issued', 'Overdue', 'Confirmed', 'Returned'])) {
            return false;
        }

        if (!$this->due_date) {
            return false;
        }

        if ($this->fine_paid) {
            return false; // Already handled, not overdue anymore
        }

        // If not returned yet, and now is past due_date
        if (!$this->returned_date && now()->greaterThan($this->due_date)) {
            return true;
        }

        // If returned after due_date
        if ($this->returned_date && $this->returned_date->greaterThan($this->due_date)) {
            return true;
        }

        return false;
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

    //overdueBooks scope
    public function scopeOverdue($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['Issued', 'Overdue'])
                ->where('due_date', '<', now())
                ->where('fine_paid', false)
                ->whereNull('returned_date');
        })->orWhere(function ($q) {
            $q->whereIn('status', ['Returned', 'Confirmed'])
                ->where('fine_paid', false)
                ->whereNotNull('returned_date')
                ->whereColumn('returned_date', '>', 'due_date');
        });
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
