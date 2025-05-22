<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{

    public function create(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'book_id' => 'required|exists:books,id',
                'title' => 'required|string',
                'message' => 'required|string',
                'type' => 'required|string'
            ]);

            $notification = Notification::create([
                'user_id' => $request->user_id,
                'book_id' => $request->book_id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'is_read' => false
            ]);

            return response()->json($notification, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            // Get notifications for the authenticated admin user
            $notifications = Notification::where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('type', 'admin_alert');
            })
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($notifications);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead($notificationId)
    {
        try {
            $notification = Notification::findOrFail($notificationId);
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json(['message' => 'Notification marked as read']);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAllAsRead()
    {
        try {
            Notification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json(['message' => 'All notifications marked as read']);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function userNotifications()
    {
        try {
            $notifications = Notification::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($notifications);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user notifications: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Add this method to handle renewal confirmations
    public function handleRenewalResponse(Request $request, $notificationId)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'confirm' => 'required|boolean'
            ]);

            $notification = Notification::with(['renewRequest.borrow', 'renewRequest.book'])
                ->findOrFail($notificationId);

            if ($notification->type !== Notification::TYPE_RENEWAL_DATE_CHANGED) {
                throw new \Exception('Invalid notification type for this action');
            }

            $renewRequest = $notification->renewRequest;
            if (!$renewRequest) {
                throw new \Exception('Associated renew request not found');
            }

            if ($validated['confirm']) {
                // Use admin_proposed_date if available, otherwise requested_date
                $newDueDate = $renewRequest->admin_proposed_date ?? $renewRequest->requested_date;

                if (!$newDueDate) {
                    Log::info('No valid renewal date found');
                    throw new \Exception('No valid renewal date found');
                }

                $renewRequest->borrow->update([
                    'due_date' => $newDueDate
                ]);
            } else {
                $newDueDate = $renewRequest->requested_date;
                if (!$newDueDate) {
                    throw new \Exception('No valid renewal date found');
                }
            }

            $renewRequest->update([
                'status' => $validated['confirm'] ? 'approved' : 'rejected',
                'processed_at' => now()
            ]);

            Notification::create([
                'user_id' => $renewRequest->user_id,
                'book_id' => $renewRequest->book_id,
                'renew_request_id' => $renewRequest->id,
                'title' => $validated['confirm'] ? 'Renewal Approved' : 'Renewal Rejected',
                'message' => $validated['confirm']
                    ? "Your renewal request for '{$renewRequest->book->name}' was approved. New due date: {$newDueDate}."
                    : "Your renewal request for '{$renewRequest->book->name}' was rejected.",
                'type' => $validated['confirm']
                    ? Notification::TYPE_RENEWAL_CONFIRMED
                    : Notification::TYPE_RENEWAL_DECLINED,
                'is_read' => false
            ]);

            Notification::create([
                'user_id' => 1, // Assuming 1 is the admin user ID
                'book_id' => $renewRequest->book_id,
                'renew_request_id' => $renewRequest->id,
                'title' => $validated['confirm'] ? 'Renewal Approved' : 'Renewal Rejected',
                'message' => $validated['confirm']
                    ? "{$renewRequest->user->name} renewal request for '{$renewRequest->book->name}' was approved. New due date: {$newDueDate}."
                    : "{$renewRequest->user->name} renewal request for '{$renewRequest->book->name}' was rejected.",
                'type' => $validated['confirm']
                    ? Notification::TYPE_RENEWAL_CONFIRMED
                    : Notification::TYPE_RENEWAL_DECLINED,
                'is_read' => false
            ]);

            $notification->update([
                'is_read' => true,
                'read_at' => now(),
                'metadata' => [
                    'renew_request_id' => $renewRequest->id,
                    'new_due_date' => $newDueDate
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => $validated['confirm']
                    ? 'Renewal confirmed and user notified.'
                    : 'Renewal declined and user notified.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Renewal response failed: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process renewal response',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
