<?php

namespace App\Services;

use App\Models\Notification;

// Mirrors backend/src/modules/notification/notification.service.ts
class NotificationService
{
    public function create(string $type, string $judul, string $pesan, ?string $guruId = null): Notification
    {
        return Notification::create([
            'type' => $type,
            'judul' => $judul,
            'pesan' => $pesan,
            'guruId' => $guruId,
        ]);
    }

    public function list(int $limit = 50)
    {
        // guru:id,nama - id is required for Eloquent to match the relation
        // (Prisma's `select: { nama: true }` doesn't need this), but the
        // original response only ever exposes {nama} - hide it again after
        // loading so the JSON shape matches exactly.
        return Notification::with('guru:id,nama')
            ->orderByDesc('createdAt')
            ->limit($limit)
            ->get()
            ->each(fn (Notification $n) => $n->guru?->makeHidden('id'));
    }

    public function unreadCount(): int
    {
        return Notification::where('isRead', false)->count();
    }

    public function markRead(string $id): Notification
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['isRead' => true]);

        return $notification;
    }

    public function markAllRead(): void
    {
        Notification::where('isRead', false)->update(['isRead' => true]);
    }
}
