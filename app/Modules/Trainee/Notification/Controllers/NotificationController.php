<?php

namespace App\Modules\Trainee\Notification\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 📥 Get Notifications (Paginated)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user
            ->notifications()
            ->latest()
            ->paginate(20);

        // Analytics
        $totalNotifications = $user
            ->notifications()
            ->count();

        $unreadNotifications = $user
            ->notifications()
            ->where('is_read', false)
            ->count();

        $readNotifications = $user
            ->notifications()
            ->where('is_read', true)
            ->count();

        // Inject analytics after current_page and before data
        $notificationsArray = $notifications->toArray();

        $customResponse = [
            'current_page' => $notificationsArray['current_page'],

            'analytics' => [
                'total'   => $totalNotifications,
                'unread' => $unreadNotifications,
                'read'    => $readNotifications,
            ],

            'data' => $notificationsArray['data'],

            'first_page_url' => $notificationsArray['first_page_url'],
            'from' => $notificationsArray['from'],
            'last_page' => $notificationsArray['last_page'],
            'last_page_url' => $notificationsArray['last_page_url'],
            'links' => $notificationsArray['links'],
            'next_page_url' => $notificationsArray['next_page_url'],
            'path' => $notificationsArray['path'],
            'per_page' => $notificationsArray['per_page'],
            'prev_page_url' => $notificationsArray['prev_page_url'],
            'to' => $notificationsArray['to'],
            'total' => $notificationsArray['total'],
        ];

        return response()->json($customResponse);
    }
    /*
    |--------------------------------------------------------------------------
    | 📌 Mark Single Notification as Read
    |--------------------------------------------------------------------------
    */
    public function markRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->markAsRead();

        // fresh load (updated values ke saath)
        $notification->refresh();

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | 📌 Mark All Notifications as Read
    |--------------------------------------------------------------------------
    */
    public function markAllRead(Request $request)
    {
        $request->user()
            ->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔢 Unread Count (for badge)
    |--------------------------------------------------------------------------
    */
    public function unreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }
}
