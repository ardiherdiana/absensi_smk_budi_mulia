<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Guru;
use Tests\TestCase;

class GuruManagementTest extends TestCase
{
    public function test_admin_can_create_a_guru(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/data-guru', [
            'nama' => 'Budi Santoso, S.Pd.',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('users', ['username' => 'budi.santoso', 'role' => 'GURU']);
        $this->assertDatabaseHas('guru', ['nama' => 'Budi Santoso, S.Pd.']);
    }

    public function test_username_collision_gets_a_numeric_suffix(): void
    {
        $this->actingAsAdmin();

        $this->post('/data-guru', ['nama' => 'Budi Santoso', 'password' => 'rahasia123'])->assertSessionHas('toast');
        $this->post('/data-guru', ['nama' => 'Budi Santoso', 'password' => 'rahasia123'])->assertSessionHas('toast');

        $this->assertDatabaseHas('users', ['username' => 'budi.santoso']);
        $this->assertDatabaseHas('users', ['username' => 'budi.santoso2']);
    }

    public function test_admin_can_update_a_guru(): void
    {
        $guru = Guru::factory()->create(['nama' => 'Nama Lama']);
        $this->actingAsAdmin();

        $response = $this->patch("/data-guru/{$guru->id}", [
            'nama' => 'Nama Baru',
            'aktif' => false,
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('guru', ['id' => $guru->id, 'nama' => 'Nama Baru', 'aktif' => false]);
    }

    public function test_admin_can_delete_a_guru_and_it_cascades(): void
    {
        $guru = Guru::factory()->create();
        Attendance::create([
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'statusMasuk' => 'HADIR',
        ]);
        $this->actingAsAdmin();

        $response = $this->delete("/data-guru/{$guru->id}");

        $response->assertSessionHas('toast');
        $this->assertDatabaseMissing('guru', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->userId]);
        $this->assertDatabaseMissing('attendance', ['guruId' => $guru->id]);
    }

    public function test_admin_can_regenerate_a_guru_qr_token(): void
    {
        $guru = Guru::factory()->create();
        $oldToken = $guru->qrToken;
        $this->actingAsAdmin();

        $response = $this->post("/data-guru/{$guru->id}/qr/regenerate");

        $response->assertOk();
        $guru->refresh();
        $this->assertNotSame($oldToken, $guru->qrToken);
    }

    public function test_kepsek_can_view_but_not_create_guru(): void
    {
        $this->actingAsKepsek();

        $this->get('/data-guru')->assertOk();

        $response = $this->post('/data-guru', [
            'nama' => 'Guru Baru',
            'password' => 'rahasia123',
        ]);
        $response->assertStatus(403);
    }

    public function test_guru_role_cannot_reach_guru_management_at_all(): void
    {
        $this->actingAsGuru();

        $this->get('/data-guru')->assertStatus(403);
    }
}
