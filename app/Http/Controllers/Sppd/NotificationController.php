<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return Inertia::render('sppd/notifikasi/index', [
            'items' => $query->paginate($this->perPage($request))->withQueryString(),
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
            'filter' => $filter,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->where('id', $notification)->first()?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
