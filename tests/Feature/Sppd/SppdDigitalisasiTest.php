<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\KonfirmasiKedatangan;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\TandaTanganElektronik;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SppdDigitalisasiTest extends TestCase
{
    private User $pemohon;

    private User $kepsek;

    private User $tu;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->pemohon = User::factory()->create(['name' => 'Doni Wijaya', 'jabatan' => 'Guru'])->assignRole(RoleName::Pemohon->value);
        $this->kepsek = User::factory()->create(['name' => 'Teti Tresnawati, M.Pd'])->assignRole(RoleName::KepalaSekolah->value);
        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);

        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ])->assertSessionHasNoErrors();
    }

    /**
     * Rekan kerja yang sah dipilih sebagai pengikut: pegawai aktif yang punya role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function rekan(array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole(RoleName::Pemohon->value);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tujuan' => 'Universitas Singaperbangsa Karawang',
            'maksud' => 'MGMP Pendidikan Pancasila',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'keterangan' => 'Membawa laptop sekolah',
            'tanggal_berangkat' => now()->addDays(2)->toDateString(),
            'jam_berangkat' => '07:30',
            'tanggal_kembali' => now()->addDays(3)->toDateString(),
            'jam_kembali' => '16:00',
            'undangan' => UploadedFile::fake()->create('undangan.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ajukan(array $overrides = []): PengajuanSppd
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload($overrides))->assertSessionHasNoErrors();

        return $this->pemohon->pengajuanSppds()->latest('id')->firstOrFail();
    }

    private function setujuiDanTerbitkan(PengajuanSppd $pengajuan, ?string $akun = null): PengajuanSppd
    {
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan), [])->assertSessionHasNoErrors();
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.terbitkan-sppd', $pengajuan), ['akun_anggaran' => $akun])->assertSessionHasNoErrors();

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts', 'kedatangan']);
    }

    /**
     * @return array<string, string>
     */
    private function dataKedatangan(PengajuanSppd $pengajuan): array
    {
        return [
            'pejabat_nama' => 'Dr. Ahmad Fauzi',
            'pejabat_jabatan' => 'Kepala Bagian Umum',
            'tiba_tanggal' => $pengajuan->tanggal_berangkat->toDateString(),
            'berangkat_tanggal' => $pengajuan->tanggal_kembali->toDateString(),
        ];
    }

    public function test_pengajuan_stores_alat_angkutan_keterangan_and_pengikut(): void
    {
        $rekan = collect([$this->rekan(), $this->rekan()]);

        $pengajuan = $this->ajukan(['pengikut_ids' => $rekan->pluck('id')->all()]);

        $this->assertSame('Sepeda motor pribadi', $pengajuan->alat_angkutan);
        $this->assertSame('Membawa laptop sekolah', $pengajuan->keterangan);
        $this->assertEqualsCanonicalizing($rekan->pluck('id')->all(), $pengajuan->pengikuts->pluck('id')->all());
    }

    public function test_alat_angkutan_is_required_and_pengikut_is_validated(): void
    {
        $aktif = $this->rekan();
        $nonaktif = $this->rekan(['is_active' => false]);
        $tanpaRole = User::factory()->create();

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['alat_angkutan' => '']))
            ->assertSessionHasErrors('alat_angkutan');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => collect(range(1, 4))->map(fn () => $this->rekan()->id)->all()]))
            ->assertSessionHasErrors('pengikut_ids');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => [$this->pemohon->id]]))
            ->assertSessionHasErrors('pengikut_ids.0');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => [$nonaktif->id]]))
            ->assertSessionHasErrors('pengikut_ids.0');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => [$tanpaRole->id]]))
            ->assertSessionHasErrors('pengikut_ids.0');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => [999999]]))
            ->assertSessionHasErrors('pengikut_ids.0');

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['pengikut_ids' => [$aktif->id, $aktif->id]]))
            ->assertSessionHasErrors('pengikut_ids.1');

        $this->assertSame(0, PengajuanSppd::count());
    }

    public function test_maksud_is_limited_to_300_characters_so_it_fits_the_form(): void
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['maksud' => str_repeat('a', 301)]))
            ->assertSessionHasErrors('maksud');
        $this->assertSame(0, PengajuanSppd::count());

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->payload(['maksud' => str_repeat('a', 300)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, PengajuanSppd::count());
    }

    public function test_create_page_lists_active_pegawai_except_the_pemohon(): void
    {
        $this->rekan(['is_active' => false]);
        $tanpaRole = User::factory()->create();
        $diharapkan = User::pegawaiAktif()->where('id', '!=', $this->pemohon->id)->count();

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sppd/pengajuan/create')
                ->has('pegawai', $diharapkan)
                ->where('pegawai', fn ($pegawai) => ! collect($pegawai)->pluck('id')->contains($this->pemohon->id)
                    && ! collect($pegawai)->pluck('id')->contains($tanpaRole->id)));
    }

    public function test_tu_can_fill_akun_anggaran_when_issuing_the_sppd(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan(), '5.2.02.01 Belanja Perjalanan Dinas');

        $this->assertSame('5.2.02.01 Belanja Perjalanan Dinas', $pengajuan->sppd->akun_anggaran);
    }

    public function test_pdf_shows_all_digital_fields(): void
    {
        $rekan = $this->rekan(['name' => 'Rio Falentino', 'jabatan' => 'Guru']);
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan(['pengikut_ids' => [$rekan->id]]), '5.2.02.01');

        $html = view('pdf.sppd', ['pengajuan' => $pengajuan])->render();

        $this->assertStringContainsString('Sepeda motor pribadi', $html);
        $this->assertStringContainsString('Membawa laptop sekolah', $html);
        $this->assertStringContainsString('Rio Falentino', $html);
        $this->assertStringContainsString('5.2.02.01', $html);
        $this->assertStringNotContainsString('........', $html, 'titik-titik bagian I/II tidak boleh dicetak');
    }

    public function test_tte_covers_the_new_fields(): void
    {
        $tte = app(TandaTanganElektronik::class);
        $rekan = $this->rekan();
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan(['pengikut_ids' => [$rekan->id]]));

        $this->assertSame(TandaTanganElektronik::VERSI, $pengajuan->tte_versi);
        $this->assertTrue($tte->valid($pengajuan));

        $pengajuan->forceFill(['alat_angkutan' => 'Mobil dinas'])->save();
        $this->assertFalse($tte->valid($pengajuan->fresh()));

        $pengajuan->forceFill(['alat_angkutan' => 'Sepeda motor pribadi'])->save();
        $this->assertTrue($tte->valid($pengajuan->fresh()));

        $pengajuan->pengikuts()->detach();
        $this->assertFalse($tte->valid($pengajuan->fresh()), 'pengikut diubah setelah disetujui');
    }

    public function test_legacy_v1_tte_still_verifies(): void
    {
        $tte = app(TandaTanganElektronik::class);
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $pengajuan->forceFill(['tte_versi' => 1])->save();
        $pengajuan->forceFill(['tte_signature' => $tte->tandatangani($pengajuan->fresh())])->save();

        $this->assertTrue($tte->valid($pengajuan->fresh()));
    }

    public function test_pemohon_confirms_arrival_and_pdf_shows_the_receiving_official(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $this->dataKedatangan($pengajuan) + [
            'bukti' => UploadedFile::fake()->create('bukti.pdf', 80, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $kedatangan = $pengajuan->fresh()->kedatangan;
        $this->assertSame('Dr. Ahmad Fauzi', $kedatangan->pejabat_nama);
        Storage::disk('public')->assertExists($kedatangan->bukti_path);
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'like', '%Dr. Ahmad Fauzi%')->exists());
        $this->assertSame(1, $this->tu->notifications()->where('data->pesan', 'Pemohon telah mengonfirmasi kedatangan di tempat tujuan.')->count());

        $html = view('pdf.sppd', ['pengajuan' => $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts', 'kedatangan'])])->render();
        $this->assertStringContainsString('Dr. Ahmad Fauzi', $html);
        $this->assertStringContainsString('Kepala Bagian Umum', $html);
        $this->assertStringContainsString('Dikonfirmasi elektronik', $html);
    }

    public function test_arrival_confirmation_can_be_updated_and_replaces_the_old_proof(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());
        $data = $this->dataKedatangan($pengajuan);

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data + ['bukti' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $pertama = $pengajuan->fresh()->kedatangan->bukti_path;

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), ['pejabat_nama' => 'Dra. Siti'] + $data + ['bukti' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])
            ->assertSessionHasNoErrors();

        $kedatangan = $pengajuan->fresh()->kedatangan;
        $this->assertSame(1, KonfirmasiKedatangan::count());
        $this->assertSame('Dra. Siti', $kedatangan->pejabat_nama);
        $this->assertNotSame($pertama, $kedatangan->bukti_path);
        Storage::disk('public')->assertMissing($pertama);
        Storage::disk('public')->assertExists($kedatangan->bukti_path);
    }

    public function test_arrival_confirmation_is_limited_to_the_owner_while_the_trip_is_ongoing(): void
    {
        $pengajuan = $this->ajukan();
        $data = $this->dataKedatangan($pengajuan);

        // Belum disetujui / diterbitkan.
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertForbidden();

        $pengajuan = $this->setujuiDanTerbitkan($pengajuan);

        $lain = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->actingAs($lain)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertForbidden();
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertForbidden();
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertForbidden();

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertSessionHasNoErrors();

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan));
        $this->assertSame(PengajuanStatus::Selesai, $pengajuan->fresh()->status);
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), $data)->assertForbidden();
    }

    public function test_arrival_dates_are_validated(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), [
            'pejabat_nama' => '',
            'pejabat_jabatan' => '',
            'tiba_tanggal' => $pengajuan->tanggal_berangkat->copy()->subDay()->toDateString(),
            'berangkat_tanggal' => $pengajuan->tanggal_berangkat->copy()->subDays(2)->toDateString(),
        ])->assertSessionHasErrors(['pejabat_nama', 'pejabat_jabatan', 'tiba_tanggal', 'berangkat_tanggal']);

        $this->assertNull($pengajuan->fresh()->kedatangan);
    }

    public function test_show_page_exposes_arrival_data_and_permissions(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.konfirmasiKedatangan', true)
                ->where('kedatangan', null)
                ->where('kedatanganDefault.tiba_tanggal', $pengajuan->tanggal_berangkat->toDateString())
                ->where('pengajuan.alat_angkutan', 'Sepeda motor pribadi'));

        $this->actingAs($this->tu)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page->where('can.konfirmasiKedatangan', false));
    }
}
