<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index()
    {
        $members = User::role('user')->select('id', 'name', 'email', 'contact')
            ->withCount([
                'borrowedBooks as total_borrowed',
                'returnedBooks as total_returned',
            ])
            ->get()
            ->map(function ($user) {
                $user->status = $user->borrowedBooks()->where('status', 'Expired')->count() > 3 ? 'Blocked' : 'Active';
                return $user;
            });

        return response()->json($members);
    }
}
