<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
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
            $notifications = Notification::where('user_id', auth()->id())
                ->orWhere('type', 'admin_alert')
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
}
