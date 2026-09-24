<?php

namespace Tests\Feature\Admin;

use App\Models\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_unread_count_reflects_unread_notifications(): void
    {
        Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen Masuk', 'pesan' => 'Test', 'isRead' => false]);
        Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen Masuk', 'pesan' => 'Test', 'isRead' => true]);
        $this->actingAsAdmin();

        $response = $this->getJson('/notifikasi/unread-count');

        $response->assertOk();
        $response->assertJson(['count' => 1]);
    }

    public function test_admin_can_mark_one_notification_read(): void
    {
        $notification = Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen Masuk', 'pesan' => 'Test', 'isRead' => false]);
        $this->actingAsAdmin();

        $response = $this->patchJson("/notifikasi/{$notification->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'isRead' => true]);
    }

    public function test_admin_can_mark_all_notifications_read(): void
    {
        Notification::create(['type' => 'CHECKIN', 'judul' => 'A', 'pesan' => 'Test', 'isRead' => false]);
        Notification::create(['type' => 'CHECKOUT', 'judul' => 'B', 'pesan' => 'Test', 'isRead' => false]);
        $this->actingAsAdmin();

        $response = $this->post('/notifikasi/read-all');

        $response->assertNoContent();
        $this->assertSame(0, Notification::where('isRead', false)->count());
    }

    public function test_guru_cannot_access_notifications(): void
    {
        $this->actingAsGuru();

        $this->get('/notifikasi')->assertStatus(403);
    }
}
