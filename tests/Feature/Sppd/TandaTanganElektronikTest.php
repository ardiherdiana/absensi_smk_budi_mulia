<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\TandaTanganElektronik;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TandaTanganElektronikTest extends TestCase
{
    private User $kepsek;

    private User $tu;

    private PengajuanSppd $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->kepsek = User::factory()->create(['name' => 'Teti Tresnawati, M.Pd', 'jabatan' => 'Kepala Sekolah'])
            ->assignRole(RoleName::KepalaSekolah->value);
        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);

        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ])->assertSessionHasNoErrors();

        $this->pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => User::factory()->create(['name' => 'Doni Wijaya'])->assignRole(RoleName::Pemohon->value)->id,
            'tujuan' => 'Universitas Singaperbangsa Karawang',
            'status' => PengajuanStatus::DiajukanKeKepsek,
        ]);
    }

    private function setujui(?PengajuanSppd $pengajuan = null): PengajuanSppd
    {
        $pengajuan ??= $this->pengajuan;

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan), [])->assertSessionHasNoErrors();

        return $pengajuan->fresh();
    }

    public function test_approval_issues_a_signed_tte_code(): void
    {
        $pengajuan = $this->setujui();

        $this->assertSame(32, strlen($pengajuan->tte_kode));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $pengajuan->getRawOriginal('tte_signature'));
        $this->assertTrue(app(TandaTanganElektronik::class)->valid($pengajuan));
    }

    public function test_each_approval_gets_a_unique_code(): void
    {
        $kedua = PengajuanSppd::factory()->create(['status' => PengajuanStatus::DiajukanKeKepsek]);

        $this->assertNotSame($this->setujui()->tte_kode, $this->setujui($kedua)->tte_kode);
    }

    public function test_pengajuan_that_is_rejected_or_pending_has_no_tte(): void
    {
        $this->assertNull($this->pengajuan->tte_kode);

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.reject', $this->pengajuan), ['catatan' => 'Tidak sesuai']);

        $this->assertNull($this->pengajuan->fresh()->tte_kode);
    }

    public function test_public_verification_page_shows_the_document_without_login(): void
    {
        $pengajuan = $this->setujui();

        $this->get(route('sppd.tte.verifikasi', $pengajuan->tte_kode))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('sppd/verifikasi/show')
                ->where('hasil', 'valid')
                ->where('dokumen.pemohon', 'Doni Wijaya')
                ->where('dokumen.tujuan', 'Universitas Singaperbangsa Karawang')
                ->where('dokumen.penyetuju', 'Teti Tresnawati, M.Pd')
                ->where('dokumen.sidik_jari', app(TandaTanganElektronik::class)->sidikJari($pengajuan))
                ->missing('dokumen.tte_signature'));
    }

    public function test_verification_fails_when_the_data_is_changed_after_approval(): void
    {
        $pengajuan = $this->setujui();

        // Perubahan langsung di database (mis. oleh pihak yang tidak berwenang) tanpa tanda tangan baru.
        $pengajuan->forceFill(['tujuan' => 'Tempat Lain'])->save();

        $this->get(route('sppd.tte.verifikasi', $pengajuan->tte_kode))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hasil', 'tidak_valid')->where('dokumen', null));
    }

    public function test_verification_fails_when_the_signature_is_forged(): void
    {
        $pengajuan = $this->setujui();
        $pengajuan->forceFill(['tte_signature' => str_repeat('a', 64)])->save();

        $this->get(route('sppd.tte.verifikasi', $pengajuan->tte_kode))
            ->assertInertia(fn (Assert $page) => $page->where('hasil', 'tidak_valid'));
    }

    public function test_verification_fails_when_the_server_secret_differs(): void
    {
        $pengajuan = $this->setujui();

        config(['tte.secret' => 'kunci-lain-yang-bukan-milik-server']);

        $this->assertFalse(app(TandaTanganElektronik::class)->valid($pengajuan->fresh()));
    }

    public function test_unknown_and_malformed_codes_are_not_found(): void
    {
        $this->get(route('sppd.tte.verifikasi', str_repeat('A', 32)))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->where('hasil', 'tidak_ditemukan')->where('dokumen', null));

        $this->get('/sppd/verifikasi/kode-pendek')->assertNotFound();
    }

    public function test_verification_route_is_rate_limited(): void
    {
        $kode = str_repeat('B', 32);

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('sppd.tte.verifikasi', $kode))->assertNotFound();
        }

        $this->get(route('sppd.tte.verifikasi', $kode))->assertStatus(429);
    }

    public function test_signature_hash_is_not_exposed_to_the_frontend(): void
    {
        $pengajuan = $this->setujui();

        $this->actingAs($this->kepsek)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pengajuan.tte_kode', $pengajuan->tte_kode)
                ->missing('pengajuan.tte_signature'));
    }

    public function test_sppd_pdf_contains_the_qr_and_verification_url(): void
    {
        $pengajuan = $this->setujui();
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.terbitkan-sppd', $pengajuan))->assertRedirect();

        $html = view('pdf.sppd', ['pengajuan' => $pengajuan->fresh()->load(['pemohon', 'penyetuju', 'sppd'])])->render();

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('/verifikasi/', $html);
        $this->assertStringContainsString($pengajuan->tte_kode, $html);
        $this->assertStringContainsString(app(TandaTanganElektronik::class)->sidikJari($pengajuan), $html);

        $this->actingAs($this->kepsek)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->assertOk();
    }
}
