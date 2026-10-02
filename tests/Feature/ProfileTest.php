<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_admin_sees_the_admin_profile_page(): void
    {
        $this->actingAsAdmin();

        $this->get('/profil')->assertOk();
    }

    public function test_guru_sees_the_guru_profile_page(): void
    {
        $this->actingAsGuru();

        $this->get('/profil')->assertOk();
    }

    public function test_profile_pages_expose_account_info_and_monthly_attendance(): void
    {
        $this->actingAsAdmin();
        $this->get('/profil')->assertInertia(fn ($page) => $page
            ->where('akun.peranAbsensi', 'ADMIN')
            ->where('guru', null)
            ->where('kehadiranBulanIni', null)
            ->has('akun.username'));

        $this->actingAsGuru();
        $this->get('/profil')->assertInertia(fn ($page) => $page
            ->where('akun.peranAbsensi', 'GURU')
            ->has('guru.nama')
            ->has('kehadiranBulanIni', fn ($k) => $k->hasAll(['HADIR', 'TELAT', 'IZIN', 'SAKIT', 'ALPA'])));
    }

    public function test_profile_inside_the_sppd_module_uses_the_sppd_page(): void
    {
        $this->actingAsGuru();
        $this->get('/sppd/profil')->assertOk()->assertInertia(fn ($page) => $page
            ->component('sppd/profil')
            ->where('punyaGuru', true));

        $this->actingAsAdmin();
        $this->get('/sppd/profil')->assertOk()->assertInertia(fn ($page) => $page->where('punyaGuru', false));
    }

    public function test_sppd_only_staff_without_an_absensi_role_can_open_profile_and_change_password(): void
    {
        $staf = User::factory()->create(['role' => null, 'password' => Hash::make('lama123')]);

        $this->actingAs($staf)->get('/sppd/profil')->assertOk();

        $this->actingAs($staf)->post('/profil/password', [
            'oldPassword' => 'lama123',
            'newPassword' => 'baru1234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('baru1234', $staf->fresh()->password));
        $this->actingAs($staf)->get('/profil')->assertForbidden();
    }

    public function test_guru_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $this->actingAsGuru();

        $response = $this->post('/profil/foto', ['foto' => UploadedFile::fake()->image('profil.png')]);

        $response->assertSessionHas('toast');
        Storage::disk('public')->assertExists(
            'guru/'.basename(Guru::first()->fotoUrl)
        );
    }

    public function test_user_can_change_password_with_correct_old_password(): void
    {
        $user = $this->actingAsAdmin(['password' => Hash::make('lama123')]);

        $response = $this->post('/profil/password', [
            'oldPassword' => 'lama123',
            'newPassword' => 'baru123',
        ]);

        $response->assertSessionHas('toast');
        $this->assertTrue(Hash::check('baru123', $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_old_password(): void
    {
        $this->actingAsAdmin(['password' => Hash::make('lama123')]);

        $response = $this->post('/profil/password', [
            'oldPassword' => 'salah',
            'newPassword' => 'baru123',
        ]);

        $response->assertSessionHasErrors('oldPassword');
    }
}
