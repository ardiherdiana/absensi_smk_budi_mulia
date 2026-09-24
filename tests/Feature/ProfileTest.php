<?php

namespace Tests\Feature;

use App\Models\Guru;
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
