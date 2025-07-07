<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'reservation_id',
        'renew_request_id',
        'title',
        'message',
        'type',
        'is_read',
        'read_at',
        'metadata'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Notification types
    const TYPE_RESERVATION_PENDING = 'reservation_pending';
    const TYPE_RESERVATION_APPROVED = 'reservation_approved';
    const TYPE_RESERVATION_REJECTED = 'reservation_rejected';
    const TYPE_BOOK_AVAILABLE = 'book_available';
    const TYPE_BOOK_READY = 'book_ready_for_pickup';
    const TYPE_ADMIN_ALERT = 'admin_alert';
    const TYPE_RENEWAL_REQUEST = 'renewal_request';
    const TYPE_RENEWAL_DATE_CHANGED = 'renewal_date_changed';
    const TYPE_RENEWAL_CONFIRMED = 'renewal_confirmed';
    const TYPE_RENEWAL_DECLINED = 'renewal_declined';
    const TYPE_RENEWAL_APPROVED = 'renewal_approved';
    const TYPE_BOOK_ISSUED = 'book_issued';
    const TYPE_RENEWAL_EXPIRED = 'renewal_expired';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function reservation()
    {
        return $this->belongsTo(BookReservation::class);
    }

    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    public function renewRequest()
    {
        return $this->belongsTo(RenewRequest::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
