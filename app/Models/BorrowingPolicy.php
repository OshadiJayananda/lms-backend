<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BorrowingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrow_limit',
        'borrow_duration_days',
        'fine_per_day',
    ];

    protected $casts = [
        'fine_per_day' => 'decimal:2',
    ];

    // Singleton pattern to always get the first (and only) policy
    public static function currentPolicy()
    {
        return static::firstOrCreate([]); // Creates with default values if doesn't exist
    }
}
