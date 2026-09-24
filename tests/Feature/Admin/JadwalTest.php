<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class JadwalTest extends TestCase
{
    public function test_admin_can_update_a_days_schedule(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/1', [
            'aktif' => true,
            'jamMasuk' => '07:15',
            'batasTelat' => '07:30',
            'jamPulang' => '16:00',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('jadwal_hari', ['hari' => 1, 'jamMasuk' => '07:15', 'batasTelat' => '07:30']);
    }

    public function test_invalid_hari_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/7', ['aktif' => true]);

        $response->assertStatus(422);
    }

    public function test_invalid_time_format_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/1', ['jamMasuk' => '7am']);

        // bootstrap/app.php renders ValidationException as 400 (not Laravel's
        // default 422) for plain JSON/XHR requests.
        $response->assertStatus(400);
    }

    public function test_guru_cannot_update_schedule(): void
    {
        $this->actingAsGuru();

        $response = $this->patchJson('/pengaturan/jadwal/1', ['aktif' => true]);

        $response->assertStatus(403);
    }
}
