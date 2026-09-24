<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PengajuanSppdWorkflowTest extends TestCase
{
    private User $pemohon;

    private User $kepsek;

    private User $tu;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->pemohon = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->kepsek = User::factory()->create()->assignRole(RoleName::KepalaSekolah->value);
        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);
        $this->bendahara = User::factory()->create()->assignRole(RoleName::Bendahara->value);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pengajuanPayload(array $overrides = []): array
    {
        return array_merge([
            'tujuan' => 'Dinas Pendidikan Kabupaten Karawang',
            'maksud' => 'Mengikuti pelatihan kurikulum',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'tanggal_berangkat' => now()->addDays(3)->toDateString(),
            'jam_berangkat' => '07:30',
            'tanggal_kembali' => now()->addDays(5)->toDateString(),
            'jam_kembali' => '16:00',
            'undangan' => UploadedFile::fake()->create('undangan.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function pengajuanMenungguKepsek(): PengajuanSppd
    {
        return PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::DiajukanKeKepsek,
        ]);
    }

    private function kepsekMenyimpanTandaTangan(): void
    {
        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($this->kepsek->fresh()->signature_path, 'signature was not saved');
    }

    public function test_full_sppd_lifecycle_from_pengajuan_to_selesai(): void
    {
        // 1. Pemohon mengajukan SPPD dengan tujuan diketik sendiri beserta jam berangkat/kembali.
        $response = $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload());

        $pengajuan = $this->pemohon->pengajuanSppds()->firstOrFail();
        $response->assertRedirect(route('sppd.pengajuan.show', $pengajuan));
        $this->assertSame('Dinas Pendidikan Kabupaten Karawang', $pengajuan->tujuan);
        $this->assertSame('07:30', substr($pengajuan->jam_berangkat, 0, 5));
        $this->assertSame('16:00', substr($pengajuan->jam_kembali, 0, 5));
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->status);

        // 2. Kepala Sekolah menyimpan tanda tangan lalu menyetujui; tanda tangan otomatis melekat pada pengajuan.
        $this->kepsekMenyimpanTandaTangan();

        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.approve', $pengajuan), ['catatan' => 'Disetujui, silakan berangkat.'])
            ->assertRedirect();
        $pengajuan->refresh();
        $this->assertSame(PengajuanStatus::DisetujuiKepsek, $pengajuan->status);
        $this->assertNotNull($pengajuan->tanda_tangan_kepsek_path);
        Storage::disk('public')->assertExists($pengajuan->tanda_tangan_kepsek_path);

        // 3. TU menerbitkan SPPD; langsung berstatus Sedang Ditugaskan tanpa langkah tanda tangan terpisah.
        $this->actingAs($this->tu)
            ->post(route('sppd.pengajuan.terbitkan-sppd', $pengajuan))
            ->assertRedirect();
        $pengajuan->refresh();
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->status);
        $this->assertNotNull($pengajuan->sppd);

        // 4. PDF SPPD memuat tanda tangan Kepala Sekolah yang tersimpan saat persetujuan.
        $html = view('pdf.sppd', ['pengajuan' => $pengajuan->load(['pemohon', 'penyetuju', 'sppd'])])->render();
        $this->assertStringContainsString(Storage::disk('public')->path($pengajuan->tanda_tangan_kepsek_path), $html);
        $this->assertStringContainsString($this->kepsek->name, $html);

        // 5. SPPD bisa diunduh sebagai PDF.
        $this->assertMatchesRegularExpression(
            '#^001/SMK-BM/(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)/\d{4}$#',
            $pengajuan->sppd->nomor_sppd,
        );
        $pdfResponse = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('sppd-001-SMK-BM-', $pdfResponse->headers->get('Content-Disposition'));

        // 6. Bendahara mencairkan uang muka.
        $this->actingAs($this->bendahara)
            ->post(route('sppd.pengajuan.cairkan-uang-muka', $pengajuan), ['jumlah' => 500_000])
            ->assertRedirect();
        $this->assertCount(1, $pengajuan->fresh()->pencairans);

        // 7. TU menandai perjalanan dinas selesai.
        $this->actingAs($this->tu)
            ->post(route('sppd.pengajuan.selesai', $pengajuan))
            ->assertRedirect();
        $this->assertSame(PengajuanStatus::Selesai, $pengajuan->fresh()->status);
    }

    public function test_status_filter_is_null_without_a_query_and_ignores_unknown_values(): void
    {
        $this->actingAs($this->pemohon)->get('/sppd/pengajuan')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', null));

        $this->actingAs($this->pemohon)->get('/sppd/pengajuan?status=bukan-status')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', null));

        $this->actingAs($this->pemohon)->get('/sppd/pengajuan?status=selesai')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'selesai'));
    }

    public function test_tujuan_and_jam_are_required(): void
    {
        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload(['tujuan' => '', 'jam_berangkat' => '', 'jam_kembali' => '']))
            ->assertSessionHasErrors(['tujuan', 'jam_berangkat', 'jam_kembali']);

        $this->assertSame(0, PengajuanSppd::count());
    }

    public function test_jam_kembali_must_be_after_jam_berangkat_on_the_same_day(): void
    {
        $tanggal = now()->addDays(3)->toDateString();

        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload([
                'tanggal_berangkat' => $tanggal,
                'tanggal_kembali' => $tanggal,
                'jam_berangkat' => '10:00',
                'jam_kembali' => '09:00',
            ]))
            ->assertSessionHasErrors('jam_kembali');

        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload([
                'tanggal_berangkat' => $tanggal,
                'tanggal_kembali' => $tanggal,
                'jam_berangkat' => '10:00',
                'jam_kembali' => '15:00',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_kepala_sekolah_can_reject_pengajuan(): void
    {
        $pengajuan = $this->pengajuanMenungguKepsek();

        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'Anggaran belum tersedia'])
            ->assertRedirect();

        $this->assertSame(PengajuanStatus::Ditolak, $pengajuan->fresh()->status);
    }

    public function test_kepala_sekolah_cannot_approve_without_a_saved_signature(): void
    {
        $pengajuan = $this->pengajuanMenungguKepsek();

        $response = $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan), []);

        $response->assertRedirect();
        $this->assertStringContainsString('Tanda Tangan Saya', (string) $response->getSession()->get('error'));
        $pengajuan->refresh();
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->status);
        $this->assertNull($pengajuan->tanda_tangan_kepsek_path);
    }

    public function test_approved_pengajuan_keeps_its_signature_when_kepsek_changes_the_signature_later(): void
    {
        $pengajuan = $this->pengajuanMenungguKepsek();
        $this->kepsekMenyimpanTandaTangan();
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan), []);

        $archived = $pengajuan->fresh()->tanda_tangan_kepsek_path;
        $profileSignature = $this->kepsek->fresh()->signature_path;
        $this->assertNotSame($profileSignature, $archived);

        $this->kepsekMenyimpanTandaTangan();

        $this->assertNotSame($profileSignature, $this->kepsek->fresh()->signature_path);
        $this->assertSame($archived, $pengajuan->fresh()->tanda_tangan_kepsek_path);
        Storage::disk('public')->assertExists($archived);
    }

    public function test_pemohon_cannot_approve_own_pengajuan(): void
    {
        $pengajuan = $this->pengajuanMenungguKepsek();

        $this->actingAs($this->pemohon)
            ->post(route('sppd.pengajuan.approve', $pengajuan), [])
            ->assertForbidden();
    }

    public function test_only_tu_can_finish_a_pengajuan_that_is_being_carried_out(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::SedangDitugaskan,
        ]);

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
        $this->actingAs($this->bendahara)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->fresh()->status);
    }

    public function test_tu_cannot_finish_a_pengajuan_that_has_not_been_carried_out(): void
    {
        $pengajuan = $this->pengajuanMenungguKepsek();

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
    }
}
