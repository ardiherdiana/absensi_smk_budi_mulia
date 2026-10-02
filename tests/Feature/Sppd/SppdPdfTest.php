<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SppdPdfTest extends TestCase
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

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);
    }

    public function test_sppd_download_is_a_one_page_landscape_pdf(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $response = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', $response->getContent());
        $this->assertStringContainsString('/Count 1', $response->getContent());
    }

    public function test_template_contains_all_fixed_labels_and_data(): void
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
            'Tiba kembali di', 'Pada tanggal',
            'Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya',
            'kepentingan jabatan dalam waktu sesingkat-singkatnya',
        ];

        $html = preg_replace('/\s+/', ' ', view('pdf.sppd', ['pengajuan' => $pengajuan])->render());

        foreach ($isi as $potongan) {
            $this->assertStringContainsString($potongan, $html, "template tidak memuat: {$potongan}");
        }
    }

    public function test_document_renders_without_pengikut(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::SedangDitugaskan,
            'disetujui_oleh' => $this->kepsek->id,
            'disetujui_at' => now(),
        ]);
        $pengajuan->sppd()->create(['nomor_sppd' => '001/SMK-BM/IX/2026', 'diterbitkan_oleh' => $this->tu->id]);
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);

        $pdf = Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])->setPaper('a4', 'landscape')->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Count 1', $pdf);
    }

    public function test_document_stays_on_one_page_with_the_longest_content(): void
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
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);

        $this->assertSame(3, $pengajuan->pengikuts->count());

        $satuHalaman = fn () => Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])->setPaper('a4', 'landscape')->output();

        // Template menyesuaikan ukuran font, jadi tetap satu halaman bahkan dengan maksud sepanjang batas (300 karakter).
        $pengajuan->maksud = substr(str_repeat('Mengikuti kegiatan MGMP ', 20), 0, 300);
        $this->assertStringContainsString('/Count 1', $satuHalaman(), 'harus satu halaman');

        // Kasus paling ekstrem: tujuan sepanjang batas (255 karakter) bersama semua isian panjang lainnya.
        $pengajuan->tujuan = substr(str_repeat('Universitas Singaperbangsa Karawang ', 8), 0, 255);
        $this->assertStringContainsString('/Count 1', $satuHalaman(), 'harus satu halaman pada isi ekstrem');
    }

    public function test_template_setting_page_is_gone(): void
    {
        $this->actingAs($this->tu)->get('/sppd/pengaturan/template-sppd')->assertNotFound();
    }
}
