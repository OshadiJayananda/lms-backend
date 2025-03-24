<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookAvailabilityNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'requested_date',
        'notified',
        'notification_sent_at'
    ];

    protected $casts = [
        'requested_date' => 'date',
        'notified' => 'boolean',
        'notification_sent_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
