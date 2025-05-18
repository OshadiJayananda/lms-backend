<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BorrowingPolicy;
use Illuminate\Http\Request;

class BorrowingPolicyController extends Controller
{
    public function index()
    {
        // Since we only have one policy, we redirect to show
        $policy = BorrowingPolicy::currentPolicy();
        return response()->json($policy);
    }

    public function update(Request $request)
    {
        // We only allow one policy, so we update if exists
        $policy = BorrowingPolicy::currentPolicy();

        $validated = $request->validate([
            'borrow_limit' => 'required|integer|min:1',
            'borrow_duration_days' => 'required|integer|min:1',
            'fine_per_day' => 'required|numeric|min:0',
        ]);

        $policy->update($validated);

        return response()->json($policy, 201);
    }

    // public function show()
    // {
    //     $borrowingPolicy = BorrowingPolicy::currentPolicy();
    //     return response()->json($borrowingPolicy);
    // }

    // public function update(Request $request, BorrowingPolicy $borrowingPolicy)
    // {
    //     $validated = $request->validate([
    //         'borrow_limit' => 'sometimes|integer|min:1',
    //         'borrow_duration_days' => 'sometimes|integer|min:1',
    //         'fine_per_day' => 'sometimes|numeric|min:0',
    //     ]);

    //     $borrowingPolicy->update($validated);

    //     return response()->json($borrowingPolicy);
    // }

    public function destroy()
    {
        $borrowingPolicy = BorrowingPolicy::currentPolicy();
        // We don't actually delete, we reset to defaults

        $borrowingPolicy->update([
            'borrow_limit' => 5,
            'borrow_duration_days' => 14,
            'fine_per_day' => 50.00,
        ]);

        return response()->json(['message' => 'Policy reset to default values']);
    }
}
