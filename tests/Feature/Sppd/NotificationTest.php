<?php

namespace Tests\Feature\Sppd;

use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Notifications\Sppd\PengajuanStatusChanged;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private User $user;

    private PengajuanSppd $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->pengajuan = PengajuanSppd::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('sppd.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_page_lists_only_the_authenticated_users_notifications(): void
    {
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan pertama'));
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan kedua'));
        User::factory()->create()->notify(new PengajuanStatusChanged($this->pengajuan, 'Milik orang lain'));

        $this->actingAs($this->user)
            ->get(route('sppd.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sppd/notifikasi/index')
                ->has('items.data', 2)
                ->where('counts.all', 2)
                ->where('counts.unread', 2)
                ->where('filter', 'all')
                ->where('notifications.unreadCount', 2));
    }

    public function test_unread_filter_hides_read_notifications(): void
    {
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Sudah dibaca'));
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Belum dibaca'));
        $this->user->notifications()->first()->markAsRead();

        $this->actingAs($this->user)
            ->get(route('sppd.notifications.index', ['filter' => 'unread']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('counts.all', 2)
                ->where('counts.unread', 1)
                ->where('filter', 'unread'));
    }

    public function test_a_notification_can_be_marked_as_read(): void
    {
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan'));
        $notification = $this->user->unreadNotifications()->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('sppd.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $other = User::factory()->create();
        $other->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan'));
        $notification = $other->unreadNotifications()->firstOrFail();

        $this->actingAs($this->user)->patch(route('sppd.notifications.read', $notification->id));

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan 1'));
        $this->user->notify(new PengajuanStatusChanged($this->pengajuan, 'Pesan 2'));

        $this->actingAs($this->user)
            ->patch(route('sppd.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }
}
