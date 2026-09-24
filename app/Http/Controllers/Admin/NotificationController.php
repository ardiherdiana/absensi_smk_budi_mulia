<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Inertia\Inertia;

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
