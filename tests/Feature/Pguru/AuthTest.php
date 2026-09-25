<?php

namespace Tests\Feature\Pguru;

use App\Models\Guru;
use App\Models\Pguru\Kelas;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthTest extends PguruTestCase
{
    private function masuk(string $username, string $password)
    {
        return $this->postJson(self::API.'/auth/login', ['username' => $username, 'password' => $password]);
    }

    private function guru(array $atribut = []): User
    {
        return $this->akun($atribut + ['username' => 'guru.satu', 'password' => Hash::make('rahasia123')]);
    }

    public function test_login_dengan_username_dan_kata_sandi_akun_absensi(): void
    {
        $user = $this->guru(['name' => 'Budi Santoso']);

        $res = $this->masuk('guru.satu', 'rahasia123')->assertOk();

        $res->assertJsonPath('akun.id', $user->id)
            ->assertJsonPath('akun.username', 'guru.satu')
            ->assertJsonPath('akun.name', 'Budi Santoso')
            ->assertJsonPath('akun.role', 'GURU');
        $this->assertNotEmpty($res->json('token'));
        // Bentuk jawaban tidak pernah memuat kata sandi atau data SPPD.
        $this->assertSame(['id', 'name', 'username', 'role'], array_keys($res->json('akun')));

        $this->withToken($res->json('token'))->getJson(self::API.'/auth/me')
            ->assertOk()->assertJsonPath('akun.username', 'guru.satu');
    }

    public function test_nama_tampilan_jatuh_ke_nama_guru_lalu_username(): void
    {
        $tanpaNama = $this->guru(['name' => null]);
        Guru::factory()->create(['userId' => $tanpaNama->id, 'nama' => 'Siti Aminah']);
        $this->masuk('guru.satu', 'rahasia123')->assertJsonPath('akun.name', 'Siti Aminah');

        $this->akun(['username' => 'staf.tu', 'name' => null, 'password' => Hash::make('rahasia123'), 'role' => 'ADMIN']);
        $this->masuk('staf.tu', 'rahasia123')->assertJsonPath('akun.name', 'staf.tu');
    }

    public function test_admin_dan_kepsek_absensi_boleh_masuk(): void
    {
        User::factory()->admin()->create(['username' => 'admin.a', 'password' => Hash::make('rahasia123')]);
        User::factory()->kepsek()->create(['username' => 'kepsek.a', 'password' => Hash::make('rahasia123')]);

        $this->masuk('admin.a', 'rahasia123')->assertOk()->assertJsonPath('akun.role', 'ADMIN');
        $this->masuk('kepsek.a', 'rahasia123')->assertOk()->assertJsonPath('akun.role', 'KEPSEK');
    }

    public function test_login_gagal_tidak_membedakan_username_tidak_ada_dan_kata_sandi_salah(): void
    {
        $this->guru();

        $a = $this->masuk('guru.satu', 'salah-salah');
        $b = $this->masuk('tidak.ada', 'salah-salah');

        $a->assertStatus(401);
        $b->assertStatus(401);
        $this->assertSame($a->json('message'), $b->json('message'));
        $this->assertSame('Username atau kata sandi salah', $a->json('message'));
    }

    public function test_login_menolak_isian_kosong_dengan_pesan_indonesia(): void
    {
        $this->postJson(self::API.'/auth/login', [])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Data tidak valid')
            ->assertJsonPath('errors.username.0', 'Username wajib diisi')
            ->assertJsonPath('errors.password.0', 'Kata sandi wajib diisi');
    }

    public function test_login_dibatasi_setelah_lima_kali_gagal(): void
    {
        RateLimiter::clear('pguru-login:'.sha1('guru.satu|127.0.0.1'));
        $this->guru();

        foreach (range(1, 5) as $_) {
            $this->masuk('guru.satu', 'salah-salah')->assertStatus(401);
        }

        $this->masuk('guru.satu', 'rahasia123')->assertStatus(429);
    }

    public function test_akun_tanpa_peran_absensi_tidak_boleh_memakai_simak(): void
    {
        // Staf SPPD saja (TU, bendahara): ada di users tetapi tidak punya role absensi.
        $this->guru(['role' => null]);

        $this->masuk('guru.satu', 'rahasia123')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun ini tidak memiliki akses SIMAK. Hubungi admin sekolah.');
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        $this->guru(['is_active' => false]);

        $this->masuk('guru.satu', 'rahasia123')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun dinonaktifkan. Hubungi admin sekolah.');
    }

    public function test_guru_yang_profilnya_dinonaktifkan_tidak_bisa_masuk_seperti_di_web(): void
    {
        $user = $this->guru();
        Guru::factory()->create(['userId' => $user->id, 'aktif' => false]);

        $this->masuk('guru.satu', 'rahasia123')->assertStatus(403);
    }

    public function test_token_langsung_ditolak_setelah_akun_dinonaktifkan_atau_perannya_dicabut(): void
    {
        $user = $this->akun();
        $token = $user->createToken('mobile')->plainTextToken;
        $this->withToken($token)->getJson(self::API.'/kelas')->assertOk();

        $user->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(self::API.'/kelas')->assertStatus(403);

        $user->update(['is_active' => true, 'role' => null]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(self::API.'/kelas')->assertStatus(403);
    }

    public function test_logout_mencabut_token(): void
    {
        $this->guru();
        $token = $this->masuk('guru.satu', 'rahasia123')->json('token');

        $this->withToken($token)->postJson(self::API.'/auth/logout')->assertOk();

        // Guard di-cache per proses uji; paksa dibaca ulang dari token.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(self::API.'/auth/me')->assertStatus(401);
    }

    public function test_pendaftaran_verifikasi_dan_kelola_akun_sudah_tidak_ada(): void
    {
        $akun = $this->akun();

        $this->postJson(self::API.'/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'rahasia123'])->assertStatus(404);
        $this->sebagai($akun)->postJson(self::API.'/auth/verifikasi-email', ['kode' => '123456'])->assertStatus(404);
        $this->sebagai($akun)->postJson(self::API.'/auth/kirim-ulang-kode')->assertStatus(404);
        $this->sebagai(User::factory()->admin()->create())->getJson(self::API.'/admin/akun')->assertStatus(404);
    }

    public function test_akun_baru_di_absensi_langsung_bisa_masuk_tanpa_verifikasi(): void
    {
        // Akun dibuat admin di absensi (bukan lewat API): tidak ada langkah email atau persetujuan lain.
        User::factory()->create(['username' => 'baru.dibuat', 'password' => Hash::make('rahasia123')]);

        $token = $this->masuk('baru.dibuat', 'rahasia123')->assertOk()->json('token');

        $this->withToken($token)->getJson(self::API.'/kelas')->assertOk()->assertJsonPath('data', []);
    }

    public function test_data_terikat_ke_users_dan_ikut_terhapus_bersama_akunnya(): void
    {
        $user = $this->akun();
        $kelas = Kelas::factory()->create(['akunId' => $user->id]);

        $this->assertDatabaseHas('pguru_kelas', ['id' => $kelas->id, 'akunId' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('pguru_kelas', ['id' => $kelas->id]);
    }

    public function test_tanpa_token_dijawab_401_json_berbahasa_indonesia(): void
    {
        $this->getJson(self::API.'/kelas')->assertStatus(401)->assertJsonPath('message', 'Sesi berakhir. Silakan masuk kembali.');
        // Klien yang lupa header Accept tetap mendapat JSON, bukan redirect ke halaman login.
        $this->get(self::API.'/kelas')->assertStatus(401)->assertJsonPath('message', 'Sesi berakhir. Silakan masuk kembali.');
    }

    public function test_sesi_login_absensi_tidak_berlaku_di_api(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson(self::API.'/auth/me')->assertStatus(401);
    }
}
