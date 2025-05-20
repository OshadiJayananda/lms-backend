<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index()
    {
        $members = User::role('user')->select('id', 'name', 'email', 'contact', 'created_at')
            ->withCount([
                'borrowedBooks as total_borrowed',
                'returnedBooks as total_returned',
            ])
            ->paginate(10);

        $members->getCollection()->transform(function ($user) {
            $user->status = $user->overdueBooksCount() > 0 ? 'Blocked' : 'Active';
            return $user;
        });

        return response()->json($members);
    }
}
