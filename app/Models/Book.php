<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'isbn',
        'image',
        'description',
        'no_of_copies',
        'category_id',
        'author_id' // Add this
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function getImageAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function reservations()
    {
        return $this->hasMany(BookReservation::class);
    }
}
