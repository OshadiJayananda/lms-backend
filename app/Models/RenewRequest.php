<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrow_id',
        'user_id',
        'book_id',
        'current_due_date',
        'requested_date',
        'admin_proposed_date', // Added
        'status',
        'admin_notes',
        'processed_at' // Added
    ];

    protected $casts = [
        'current_due_date' => 'datetime',
        'requested_date' => 'datetime',
        'admin_proposed_date' => 'datetime', // Added
        'processed_at' => 'datetime' // Added
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PENDING_USER_CONFIRMATION = 'pending_user_confirmation';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function borrow()
    {
        return $this->belongsTo(Borrow::class);
    }

    public function notification()
    {
        return $this->hasOne(Notification::class);
    }
}
