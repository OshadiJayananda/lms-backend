<?php

namespace App\Http\Controllers;

use App\Mail\RenewalApprovedMail;
use App\Mail\RenewalRejectedMail;
use App\Models\Book;
use App\Models\BookAvailabilityNotification;
use App\Models\Borrow;
use App\Models\Notification;
use App\Models\RenewRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RenewBookController extends Controller
{
    public function renewBook(Request $request, $bookId)
    {
        $request->validate([
            'renewDate' => 'required|date|after_or_equal:today|before_or_equal:today +30 days',
        ]);

        $borrow = Borrow::where('book_id', $bookId)
            ->where('user_id', auth()->id())
            ->where('status', 'Issued')
            ->firstOrFail();

        $borrow->due_date = $request->renewDate;
        $borrow->save();

        return response()->json(['message' => 'Book renewed successfully!']);
    }

    public function renewRequest(Request $request, $bookId)
    {
        $request->validate([
            'renewDate' => 'required|date|after_or_equal:today'
        ]);

        $borrow = Borrow::where('book_id', $bookId)
            ->where('user_id', auth()->id())
            ->where('status', 'Issued')
            ->firstOrFail();

        $existingRequest = RenewRequest::where('borrow_id', $borrow->id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'A renewal request is already pending for this book. Please wait for approval.'], 422);
        }

        $renewRequest = RenewRequest::create([
            'borrow_id' => $borrow->id,
            'user_id' => auth()->id(),
            'book_id' => $bookId,
            'current_due_date' => $borrow->due_date,
            'requested_date' => $request->renewDate,
            'status' => 'pending'
        ]);

        // Create notification for admin
        Notification::create([
            'user_id' => 1,
            'book_id' => $bookId,
            'renew_request_id' => $renewRequest->id,
            'title' => 'New Renewal Request',
            'message' => "User has requested to renew book '{$borrow->book->name}' until {$request->renewDate}",
            'type' => Notification::TYPE_RENEWAL_REQUEST,
            'is_read' => false,
            'metadata' => [
                'request_id' => $renewRequest->id,
                'current_due_date' => $borrow->due_date,
                'requested_date' => $request->renewDate
            ]
        ]);

        return response()->json(['message' => 'Renewal request submitted for admin approval']);
    }

    public function getRenewRequests()
    {
        try {
            $requests = RenewRequest::with([
                'user',
                'book',
                'book.reservations' => function ($query) {
                    $query->where('status', 'pending');
                }
            ])
                // ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json($requests);
        } catch (\Exception $e) {
            Log::error('Failed to fetch renew requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch renewal requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approveRenewRequest(Request $request, $requestId)
    {
        $renewRequest = RenewRequest::with(['borrow', 'user', 'book'])->findOrFail($requestId);

        if ($request->filled('admin_proposed_date')) {
            // Validate if date is provided
            $request->validate([
                'admin_proposed_date' => 'required|date|after_or_equal:today'
            ]);

            // Update with admin's proposed date
            $renewRequest->update([
                'admin_proposed_date' => $request->admin_proposed_date,
                'status' => 'pending_user_confirmation'
            ]);

            // Notify user about the proposed date
            Notification::create([
                'user_id' => $renewRequest->user_id,
                'book_id' => $renewRequest->book_id,
                'renew_request_id' => $renewRequest->id,
                'title' => 'Renewal Date Change Request',
                'message' => "Admin has proposed a new renewal date for '{$renewRequest->book->name}': {$request->admin_proposed_date}",
                'type' => Notification::TYPE_RENEWAL_DATE_CHANGED,
                'is_read' => false,
                'metadata' => [
                    'request_id' => $renewRequest->id,
                    'proposed_date' => $request->admin_proposed_date,
                    'current_due_date' => $renewRequest->current_due_date
                ]
            ]);

            return response()->json(['message' => 'User has been notified to confirm the new date']);
        } else {
            // Direct approval (without proposed date)
            $renewRequest->update([
                'status' => 'approved',
                'approved_at' => now()
            ]);

            // Update the borrow record with the new due date
            $renewRequest->borrow->update([
                'due_date' => $renewRequest->requested_date,
            ]);

            // Notify user about the approval
            Notification::create([
                'user_id' => $renewRequest->user_id,
                'book_id' => $renewRequest->book_id,
                'renew_request_id' => $renewRequest->id,
                'title' => 'Renewal Approved',
                'message' => "Your renewal request for '{$renewRequest->book->name}' has been approved by the admin.",
                'type' => Notification::TYPE_RENEWAL_APPROVED,
                'is_read' => false,
                'metadata' => [
                    'request_id' => $renewRequest->id,
                    'approved_date' => now(),
                    'previous_due_date' => $renewRequest->current_due_date
                ]
            ]);

            return response()->json(['message' => 'Renewal request approved successfully and user notified.']);
        }
    }

    public function rejectRenewRequest($requestId)
    {
        $renewRequest = RenewRequest::findOrFail($requestId);
        $renewRequest->status = 'rejected';
        $renewRequest->save();

        // Notify user
        Mail::to($renewRequest->user->email)
            ->send(new RenewalRejectedMail($renewRequest));

        return response()->json(['message' => 'Renewal rejected']);
    }

    public function confirmRenewalDate(Request $request, $requestId)
    {
        $request->validate([
            'confirm' => 'required|boolean'
        ]);

        DB::beginTransaction();
        try {
            $renewRequest = RenewRequest::with(['borrow', 'user', 'book'])->findOrFail($requestId);

            if ($request->confirm) {
                // User accepted the date change
                $newDueDate = $renewRequest->admin_proposed_date ?? $renewRequest->requested_date;

                $renewRequest->borrow->update([
                    'due_date' => $newDueDate,
                    'status' => 'Renewed' // Update status to Renewed
                ]);

                $renewRequest->update([
                    'status' => 'approved',
                    'processed_at' => now()
                ]);

                // Notify admin
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $renewRequest->book_id,
                    'renew_request_id' => $renewRequest->id,
                    'title' => 'Renewal Confirmed',
                    'message' => "User has confirmed the renewal date change for {$renewRequest->book->name}",
                    'type' => Notification::TYPE_RENEWAL_CONFIRMED,
                    'is_read' => false,
                    'metadata' => [
                        'new_due_date' => $newDueDate,
                        'book_id' => $renewRequest->book_id,
                        'user_id' => $renewRequest->user_id
                    ]
                ]);

                // Send email confirmation
                Mail::to($renewRequest->user->email)
                    ->send(new RenewalApprovedMail($renewRequest));
            } else {
                // User rejected the date change
                $renewRequest->update([
                    'status' => 'rejected',
                    'processed_at' => now()
                ]);

                // Notify admin
                Notification::create([
                    'user_id' => 1, // Admin ID
                    'book_id' => $renewRequest->book_id,
                    'renew_request_id' => $renewRequest->id,
                    'title' => 'Renewal Declined',
                    'message' => "User has declined the renewal date change for {$renewRequest->book->name}",
                    'type' => Notification::TYPE_RENEWAL_DECLINED,
                    'is_read' => false
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => $request->confirm
                    ? 'Renewal confirmed successfully'
                    : 'Renewal declined',
                'due_date' => $request->confirm ? $newDueDate : null,
                'status' => $request->confirm ? 'Renewed' : null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Renewal confirmation failed: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process renewal confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function notifyAdmin(Request $request, $bookId)
    {
        $request->validate([
            'requestedDate' => 'required|date'
        ]);

        $user = auth()->user();
        $book = Book::findOrFail($bookId);

        // Create notification
        BookAvailabilityNotification::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'requested_date' => $request->requestedDate,
            'notified' => false
        ]);

        return response()->json(['message' => 'Admin will be notified when copies become available']);
    }

    public function destroy($requestId)
    {
        DB::beginTransaction();
        try {
            $renewRequest = RenewRequest::findOrFail($requestId);

            // Check if the request is in a deletable state
            if (!in_array($renewRequest->status, [RenewRequest::STATUS_APPROVED, RenewRequest::STATUS_REJECTED])) {
                return response()->json([
                    'message' => 'Only approved or rejected renewal requests can be deleted'
                ], 422);
            }

            // Delete related notifications first
            Notification::where('renew_request_id', $requestId)->delete();

            $renewRequest->delete();

            DB::commit();

            return response()->json(['message' => 'Renewal request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete renewal request: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete renewal request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
