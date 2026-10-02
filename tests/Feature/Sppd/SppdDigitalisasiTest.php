<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SppdDigitalisasiTest extends TestCase
{
    /** JPEG 1x1 piksel; GD tidak selalu terpasang di mesin tes, jadi tidak memakai UploadedFile::image(). */
    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

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

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataLaporan(array $overrides = []): array
    {
        return array_merge([
            'ringkasan' => 'Membahas kurikulum merdeka dan menyepakati tindak lanjut untuk sekolah.',
            'fotos' => [
                UploadedFile::fake()->createWithContent('a.jpg', base64_decode(self::JPEG_1X1)),
                UploadedFile::fake()->createWithContent('b.jpg', base64_decode(self::JPEG_1X1)),
            ],
        ], $overrides);
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

    public function test_pdf_has_no_tte_qr_nor_arrival_confirmation_and_leaves_destination_fields_blank(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $html = view('pdf.sppd', ['pengajuan' => $pengajuan])->render();

        $this->assertStringNotContainsString('data:image/svg+xml', $html);
        $this->assertStringNotContainsString('ditandatangani elektronik', $html);
        $this->assertStringNotContainsString('Dikonfirmasi elektronik', $html);
        $this->assertStringNotContainsString('/sppd/verifikasi/', $html);
    }

    public function test_tte_verification_route_and_arrival_confirmation_route_are_gone(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->get('/sppd/verifikasi/'.str_repeat('a', 32))->assertNotFound();
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan/'.$pengajuan->id.'/kedatangan', [])->assertNotFound();
    }

    public function test_pemohon_fills_trip_report_with_summary_and_photos(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())
            ->assertSessionHasNoErrors();

        $laporan = $pengajuan->fresh()->laporan;
        $this->assertStringContainsString('kurikulum merdeka', $laporan->ringkasan);
        $this->assertSame($this->pemohon->id, $laporan->ditulis_oleh);
        $this->assertCount(2, $laporan->fotos);
        $laporan->fotos->each(fn ($foto) => Storage::disk('public')->assertExists($foto->path));
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'like', 'Laporan perjalanan diisi%')->exists());
        $this->assertSame(1, $this->tu->notifications()->where('data->pesan', 'Pemohon telah mengisi laporan perjalanan dinas.')->count());
    }

    public function test_trip_report_requires_summary_and_at_least_one_photo(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan(['ringkasan' => '']))
            ->assertSessionHasErrors('ringkasan');
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan(['fotos' => []]))
            ->assertSessionHasErrors('fotos');
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan([
            'fotos' => [UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasErrors('fotos.0');

        $this->assertNull($pengajuan->fresh()->laporan);
    }

    public function test_trip_report_can_be_updated_replacing_and_removing_photos(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan());

        [$pertama, $kedua] = $pengajuan->fresh()->laporan->fotos->all();

        // Hapus foto pertama, tambah satu foto baru, ubah rangkuman: tanpa foto baru pun boleh selama masih ada sisa.
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), [
            'ringkasan' => 'Rangkuman revisi',
            'hapus_foto_ids' => [$pertama->id],
            'fotos' => [UploadedFile::fake()->createWithContent('c.jpg', base64_decode(self::JPEG_1X1))],
        ])->assertSessionHasNoErrors();

        $laporan = $pengajuan->fresh()->laporan;
        $this->assertSame('Rangkuman revisi', $laporan->ringkasan);
        $this->assertCount(2, $laporan->fotos);
        $this->assertNull($laporan->fotos->firstWhere('id', $pertama->id));
        Storage::disk('public')->assertMissing($pertama->path);
        Storage::disk('public')->assertExists($kedua->path);
        $this->assertSame(1, $this->tu->notifications()->where('data->pesan', 'Pemohon telah mengisi laporan perjalanan dinas.')->count(), 'notifikasi hanya saat pertama kali diisi');

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), ['ringkasan' => 'Hanya teks'])
            ->assertSessionHasNoErrors();

        // Menghapus semua foto tanpa menggantinya ditolak.
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), [
            'ringkasan' => 'Hanya teks',
            'hapus_foto_ids' => $pengajuan->fresh()->laporan->fotos->pluck('id')->all(),
        ])->assertSessionHasErrors('fotos');
        $this->assertCount(2, $pengajuan->fresh()->laporan->fotos);
    }

    public function test_trip_report_is_limited_to_the_owner_while_the_trip_is_ongoing(): void
    {
        $pengajuan = $this->ajukan();

        // Belum disetujui / diterbitkan.
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertForbidden();

        $pengajuan = $this->setujuiDanTerbitkan($pengajuan);

        $lain = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->actingAs($lain)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertForbidden();
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertForbidden();
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertForbidden();

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertSessionHasNoErrors();

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan));
        $this->assertSame(PengajuanStatus::Selesai, $pengajuan->fresh()->status);
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan())->assertForbidden();
    }

    public function test_show_page_exposes_the_report_and_permissions(): void
    {
        $pengajuan = $this->setujuiDanTerbitkan($this->ajukan());

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.isiLaporan', true)
                ->where('laporan', null)
                ->where('pengajuan.alat_angkutan', 'Sepeda motor pribadi')
                ->missing('kedatangan'));

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.laporan', $pengajuan), $this->dataLaporan());

        $this->actingAs($this->tu)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.isiLaporan', false)
                ->where('laporan.penulis', $this->pemohon->name)
                ->has('laporan.fotos', 2));
    }
}
