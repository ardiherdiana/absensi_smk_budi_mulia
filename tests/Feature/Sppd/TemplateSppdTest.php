<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Enums\Sppd\SppdTemplate;
use App\Models\Sppd\PengajuanSppd;
use App\Models\Sppd\Pengaturan;
use App\Models\User;
use App\Services\Sppd\TandaTanganElektronik;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TemplateSppdTest extends TestCase
{
    private User $tu;

    private User $pemohon;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);
        $this->pemohon = User::factory()->create(['name' => 'Doni Wijaya, S.H. Gr.', 'jabatan' => 'Guru'])->assignRole(RoleName::Pemohon->value);
        $this->kepsek = User::factory()->create(['name' => 'Teti Tresnawati, M.Pd'])->assignRole(RoleName::KepalaSekolah->value);
    }

    private function pengajuanTerbit(): PengajuanSppd
    {
        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ])->assertSessionHasNoErrors();

        $rekan = User::factory()->create(['name' => 'Rio Falentino, S.Pd. Gr.', 'jabatan' => 'Guru'])->assignRole(RoleName::Pemohon->value);

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', [
            'tujuan' => 'Universitas Singaperbangsa Karawang (UNSIKA)',
            'maksud' => 'MGMP Pendidikan Pancasila',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'keterangan' => 'Membawa laptop sekolah',
            'pengikut_ids' => [$rekan->id],
            'tanggal_berangkat' => now()->addDays(2)->toDateString(),
            'jam_berangkat' => '07:30',
            'tanggal_kembali' => now()->addDays(3)->toDateString(),
            'jam_kembali' => '16:00',
            'undangan' => UploadedFile::fake()->create('undangan.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $pengajuan = $this->pemohon->pengajuanSppds()->firstOrFail();

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan), [])->assertSessionHasNoErrors();
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.terbitkan-sppd', $pengajuan), ['akun_anggaran' => '5.2.02.01 Belanja Perjalanan Dinas']);
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.kedatangan', $pengajuan), [
            'pejabat_nama' => 'Dr. Ahmad Fauzi, M.Pd',
            'pejabat_jabatan' => 'Kepala Bagian Umum',
            'tiba_tanggal' => $pengajuan->tanggal_berangkat->toDateString(),
            'berangkat_tanggal' => $pengajuan->tanggal_kembali->toDateString(),
        ])->assertSessionHasNoErrors();

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts', 'kedatangan']);
    }

    public function test_landscape_is_the_default_template(): void
    {
        $this->assertSame(SppdTemplate::Landscape, SppdTemplate::saatIni());
    }

    public function test_unknown_setting_value_falls_back_to_landscape(): void
    {
        Pengaturan::simpan(SppdTemplate::KUNCI_PENGATURAN, 'diagonal');

        $this->assertSame(SppdTemplate::Landscape, SppdTemplate::saatIni());
    }

    public function test_only_tu_can_open_and_change_the_template_setting(): void
    {
        $this->actingAs($this->tu)->get(route('sppd.template-sppd.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sppd/pengaturan/template-sppd')
                ->where('saatIni', 'landscape')
                ->has('templates', 3));

        foreach ([$this->pemohon, $this->kepsek] as $bukanTu) {
            $this->actingAs($bukanTu)->get(route('sppd.template-sppd.edit'))->assertForbidden();
            $this->actingAs($bukanTu)->put(route('sppd.template-sppd.update'), ['template' => 'portrait'])->assertForbidden();
            $this->actingAs($bukanTu)->get(route('sppd.template-sppd.pratinjau', 'portrait'))->assertForbidden();
        }

        $this->assertSame(SppdTemplate::Landscape, SppdTemplate::saatIni());
    }

    public function test_tu_can_switch_the_template_and_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->tu)->put(route('sppd.template-sppd.update'), ['template' => 'portrait'])
            ->assertSessionHasNoErrors();
        $this->assertSame(SppdTemplate::Portrait, SppdTemplate::saatIni());

        $this->actingAs($this->tu)->put(route('sppd.template-sppd.update'), ['template' => 'diagonal'])
            ->assertSessionHasErrors('template');
        $this->actingAs($this->tu)->put(route('sppd.template-sppd.update'), [])
            ->assertSessionHasErrors('template');
        $this->assertSame(SppdTemplate::Portrait, SppdTemplate::saatIni());

        $this->actingAs($this->tu)->put(route('sppd.template-sppd.update'), ['template' => 'landscape']);
        $this->assertSame(SppdTemplate::Landscape, SppdTemplate::saatIni());
        $this->assertSame(1, Pengaturan::count());
    }

    public function test_sppd_download_follows_the_selected_template(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $ukuran = [
            'landscape' => ['/MediaBox [0.000 0.000 841.890 595.280]', '/Count 1'],
            'landscape_new' => ['/MediaBox [0.000 0.000 841.890 595.280]', '/Count 1'],
            'portrait' => ['/MediaBox [0.000 0.000 595.280 841.890]', '/Count 2'],
        ];

        // Tanpa pengaturan, dipakai landscape (format aktual).
        $bawaan = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));
        $bawaan->assertOk();
        $this->assertStringContainsString($ukuran['landscape'][0], $bawaan->getContent());

        foreach ($ukuran as $template => [$mediaBox, $halaman]) {
            Pengaturan::simpan(SppdTemplate::KUNCI_PENGATURAN, $template);

            $response = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString($mediaBox, $response->getContent(), "ukuran kertas template {$template}");
            $this->assertStringContainsString($halaman, $response->getContent(), "jumlah halaman template {$template}");
        }
    }

    public function test_all_templates_contain_exactly_the_same_content(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $isi = [
            // Kop dan judul
            'YAYASAN AL-ULYA KARAWANG', 'SMK BUDI MULIA', 'Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019',
            'Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361', 'Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org',
            'Lampiran', 'Surat Perjalanan Dinas', 'SURAT PERJALANAN DINAS (SPPD)', $pengajuan->sppd->nomor_sppd,
            // Label baris 1-11
            'Pengguna Anggaran/kuasa Pengguna Anggaran', 'Nama/NIP pegawai yang melaksanakan Perjalanan Dinas',
            'Pangkat dan Golongan', 'Tingkat Biaya Perjalanan Dinas', 'Disesuaikan', 'Maksud Perjalanan Dinas',
            'Alat angkutan yang digunakan', 'Tempat Berangkat', 'Tempat tujuan', 'Lama berangkat', 'Tanggal berangkat',
            'Tanggal harus kembali/tiba di tempat baru*)', 'Pengikut : Nama', 'Pembebanan Anggaran', 'Instansi', 'Akun',
            'Keterangan lain-lain', '*)coret yang tidak perlu',
            // Isi data
            'Teti Tresnawati, M.Pd', 'Doni Wijaya, S.H. Gr.', 'MGMP Pendidikan Pancasila', 'Sepeda motor pribadi',
            'Universitas Singaperbangsa Karawang (UNSIKA)', 'SMK Budi Mulia Karawang', '07.30 WIB', '16.00 WIB', '2 Hari',
            'Rio Falentino, S.Pd. Gr.', '5.2.02.01 Belanja Perjalanan Dinas', 'Membawa laptop sekolah',
            // Tanda tangan dan sisi belakang
            'Dikeluarkan di', 'Kuasa Pengguna Anggaran', 'NIP. -,-', 'Berangkat dari', 'Tiba di', 'Pada Tanggal', 'Kepala',
            'Dr. Ahmad Fauzi, M.Pd', 'Kepala Bagian Umum', 'Tiba kembali di', 'Pada tanggal',
            'Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya',
            'kepentingan jabatan dalam waktu sesingkat-singkatnya',
            'Dikonfirmasi elektronik oleh pemohon', $pengajuan->tte_kode, strtoupper(substr($pengajuan->tte_signature, 0, 4)),
        ];

        foreach (SppdTemplate::cases() as $template) {
            $html = preg_replace('/\s+/', ' ', view($template->view(), ['pengajuan' => $pengajuan])->render());

            foreach ($isi as $potongan) {
                $this->assertStringContainsString($potongan, $html, "template {$template->value} tidak memuat: {$potongan}");
            }
        }
    }

    public function test_all_templates_render_a_document_without_tte_pengikut_or_arrival(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::SedangDitugaskan,
            'disetujui_oleh' => $this->kepsek->id,
            'disetujui_at' => now(),
        ]);
        $pengajuan->sppd()->create(['nomor_sppd' => '001/SMK-BM/IX/2026', 'diterbitkan_oleh' => $this->tu->id]);
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts', 'kedatangan']);

        foreach (SppdTemplate::cases() as $template) {
            $pdf = Pdf::loadView($template->view(), ['pengajuan' => $pengajuan])->setPaper('a4', $template->orientasi())->output();

            $this->assertStringStartsWith('%PDF', $pdf);
            $this->assertStringContainsString($template === SppdTemplate::Portrait ? '/Count 2' : '/Count 1', $pdf, "halaman template {$template->value}");
            $this->assertStringNotContainsString('data:image/svg+xml', view($template->view(), ['pengajuan' => $pengajuan])->render(), 'tanpa TTE tidak ada QR');
        }
    }

    public function test_landscape_templates_stay_on_one_page_with_the_longest_content(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $rekan = collect(['Faradilla Ferhat Arina Shandy, S.Kom., S.M., Gr.', 'Marsono, S.Kom., Gr.'])
            ->map(fn (string $nama) => User::factory()->create(['name' => $nama, 'jabatan' => 'Guru Produktif Rekayasa Perangkat Lunak'])->assignRole(RoleName::Pemohon->value));
        $pengajuan->pengikuts()->attach($rekan->pluck('id'));
        $pengajuan->forceFill([
            'alat_angkutan' => str_repeat('Kendaraan ', 4),
            'keterangan' => substr(str_repeat('Membawa peralatan ', 4), 0, 70),
        ])->save();
        $pengajuan->sppd->forceFill(['akun_anggaran' => str_repeat('5.2.02.01 Belanja ', 5)])->save();
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts', 'kedatangan']);

        $this->assertSame(3, $pengajuan->pengikuts->count());

        $satuHalaman = fn (SppdTemplate $template) => Pdf::loadView($template->view(), ['pengajuan' => $pengajuan])->setPaper('a4', 'landscape')->output();

        $this->assertStringContainsString('/Count 1', $satuHalaman(SppdTemplate::Landscape), 'template landscape harus satu halaman');

        // Landscape New menyesuaikan ukuran font, jadi tetap satu halaman bahkan dengan maksud sepanjang batas (300 karakter).
        $pengajuan->maksud = substr(str_repeat('Mengikuti kegiatan MGMP ', 20), 0, 300);
        $this->assertStringContainsString('/Count 1', $satuHalaman(SppdTemplate::LandscapeNew), 'Landscape New harus satu halaman');

        // Kasus paling ekstrem: tujuan sepanjang batas (255 karakter) bersama semua isian panjang lainnya.
        $pengajuan->tujuan = substr(str_repeat('Universitas Singaperbangsa Karawang ', 8), 0, 255);
        $this->assertStringContainsString('/Count 1', $satuHalaman(SppdTemplate::LandscapeNew), 'Landscape New harus satu halaman pada isi ekstrem');
    }

    public function test_preview_renders_both_templates_with_sample_data_only(): void
    {
        foreach (SppdTemplate::cases() as $template) {
            $response = $this->actingAs($this->tu)->get(route('sppd.template-sppd.pratinjau', $template->value));

            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $this->assertSame(0, PengajuanSppd::count(), 'pratinjau tidak boleh menyimpan data');
        $this->actingAs($this->tu)->get('/sppd/pengaturan/template-sppd/pratinjau/diagonal')->assertNotFound();
    }

    public function test_status_does_not_change_when_only_the_template_changes(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $this->actingAs($this->tu)->put(route('sppd.template-sppd.update'), ['template' => 'portrait']);

        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->fresh()->status);
        $this->assertTrue(app(TandaTanganElektronik::class)->valid($pengajuan->fresh()), 'TTE tidak terpengaruh template');
    }
}
