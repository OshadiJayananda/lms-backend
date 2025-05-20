<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'address',
        'contact',
        'password',
        'profile_picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    public function borrowedBooks()
    {
        return $this->hasMany(\App\Models\Borrow::class);
    }

    public function returnedBooks()
    {
        return $this->hasMany(\App\Models\Borrow::class)->whereIn('status', ['Confirmed', 'Returned']);
    }

    public function overdueBooksCount()
    {
        return $this->hasMany(Borrow::class)
            ->whereIn('status', ['Issued', 'Confirmed', 'Overdue', 'Returned'])
            ->whereNotNull('due_date')
            ->where('fine_paid', false)
            ->get()
            ->filter(function ($borrow) {
                return $borrow->isOverdue(); // uses Borrow model's method
            })
            ->count();
    }
}
