<?php

namespace Tests\Feature\Attendance;

use App\Models\Guru;
use Tests\TestCase;

class CheckinKioskTest extends TestCase
{
    /** 2025-01-06 is a Monday - a normal, active school day. */
    private const MONDAY = 1;

    public function test_admin_scanning_a_valid_qr_token_records_checkin(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'statusMasuk' => 'HADIR', 'nama' => $guru->nama]);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusMasuk' => 'HADIR']);
    }

    public function test_scanning_unknown_qr_token_returns_404(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => 'does-not-exist']);

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_reach_kiosk_scan_endpoint(): void
    {
        $this->actingAsGuru();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => 'whatever']);

        $response->assertStatus(403);
    }

    public function test_regenerated_qr_token_invalidates_the_old_one(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = Guru::factory()->create();
        $oldToken = $guru->qrToken;
        $this->actingAsAdmin();

        $this->post("/data-guru/{$guru->id}/qr/regenerate")->assertOk();

        $this->postJson('/kiosk/scan-qr', ['qrToken' => $oldToken])->assertStatus(404);

        $guru->refresh();
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();
    }
}
