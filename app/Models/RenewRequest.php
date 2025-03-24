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
        'status',
        'admin_notes'
    ];

    protected $casts = [
        'current_due_date' => 'date',
        'requested_date' => 'date',
    ];

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
}
