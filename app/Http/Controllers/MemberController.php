<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $searchQuery = $request->query('q');
        $statusFilter = $request->query('status');
        $perPage = $request->query('per_page', 10);

        $members = User::role('user')
            ->select('id', 'name', 'email', 'contact', 'created_at')
            ->withCount([
                'borrowedBooks as total_borrowed',
                'returnedBooks as total_returned',
            ])
            ->when($searchQuery, function ($query) use ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                        ->orWhere('email', 'like', "%{$searchQuery}%")
                        ->orWhere('contact', 'like', "%{$searchQuery}%")
                        ->orWhere('id', 'like', "%{$searchQuery}%");
                });
            })
            ->paginate($perPage);

        $members->getCollection()->transform(function ($user) {
            $user->status = $user->overdueBooksCount() > 0 ? 'Blocked' : 'Active';
            return $user;
        });

        if ($statusFilter) {
            $members->setCollection(
                $members->getCollection()->filter(function ($user) use ($statusFilter) {
                    return $statusFilter === 'active'
                        ? $user->status === 'Active'
                        : $user->status !== 'Active';
                })
            );
        }

        return response()->json($members);
    }
}
