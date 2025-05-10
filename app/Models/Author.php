<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bio',
        'nationality',
        'birth_date',
        'death_date'
    ];

    public function books()
    {
        return $this->hasMany(Book::class);
    }
}
