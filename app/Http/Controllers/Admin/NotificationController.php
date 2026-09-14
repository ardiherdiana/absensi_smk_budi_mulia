<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Inertia\Inertia;

// Mirrors backend/src/modules/notification/notification.routes.ts. The page
// itself is an Inertia visit; unread-count/mark-read/mark-all stay plain
// JSON endpoints since the original does optimistic client-side updates
// (sidebar badge polling, instant toggle-on-click) that a full Inertia
// page reload would undermine.
class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index()
    {
        return Inertia::render('admin/notifikasi-page', [
            'list' => fn () => $this->notifications->list(),
        ]);
    }

    public function unreadCount()
    {
        return response()->json(['count' => $this->notifications->unreadCount()]);
    }

    public function markRead(Notification $notification)
    {
        return response()->json($this->notifications->markRead($notification->id));
    }

    public function markAllRead()
    {
        $this->notifications->markAllRead();

        return response()->noContent();
    }
}
