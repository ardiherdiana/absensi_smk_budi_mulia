<?php

namespace Tests\Feature\Pguru;

use App\Models\Pguru\Kelas;
use App\Models\Pguru\Nilai;
use App\Models\Pguru\Siswa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportExportNilaiTest extends PguruTestCase
{
    private function berkasAsli(): UploadedFile
    {
        return new UploadedFile($this->templateNilaiAsli(), 'FORMAT NILAI SISWA TKJ ALL.xlsx', null, null, true);
    }

    private function impor(User $akun, UploadedFile $berkas, array $tambahan = [])
    {
        return $this->sebagai($akun)->post(self::API.'/import/siswa', ['berkas' => $berkas] + $tambahan, ['Accept' => 'application/json']);
    }

    private function bukaXlsx($respons): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-').'.xlsx';
        file_put_contents($path, $respons->streamedContent());

        return IOFactory::load($path);
    }

    /** Berkas Excel kecil berformat template, dibuat di tes. */
    private function berkasBuatan(array $baris, string $judulTa = 'TAHUN AJARAN 2025/2026'): UploadedFile
    {
        $sp = new Spreadsheet;
        $s = $sp->getActiveSheet();
        $s->setCellValue('A1', 'DAFTAR NAMA SISWA TKJ')->setCellValue('A3', $judulTa);
        $s->setCellValue('A5', 'NO')->setCellValue('B5', 'NAMA SISWA')->setCellValue('C5', 'KELAS')->setCellValue('D5', 'Nilai TES TEORI');
        $s->setCellValue('D6', 'Nilai Tes 1')->setCellValue('O6', 'Nilai Praktik 1');
        $r = 7;
        foreach ($baris as $b) {
            foreach ($b as $sel => $nilai) {
                $s->setCellValueExplicit($sel.$r, $nilai, is_numeric($nilai) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            $r++;
        }
        $path = tempnam(sys_get_temp_dir(), 'uji-').'.xlsx';
        (new Xlsx($sp))->save($path);

        return new UploadedFile($path, 'siswa.xlsx', null, null, true);
    }

    // ------------------------------------------------------------------ import

    public function test_import_template_asli_pak_latif_memuat_273_siswa_di_9_kelas(): void
    {
        $akun = $this->akun();

        $res = $this->impor($akun, $this->berkasAsli())->assertOk();

        $res->assertJsonPath('tahunAjaran', '2026/2027')->assertJsonPath('totalDitambah', 273)->assertJsonPath('totalDilewati', 0);
        $perKelas = collect($res->json('kelas'))->pluck('ditambah', 'nama')->all();
        $this->assertSame([
            'X TKJ 1' => 28, 'X TKJ 2' => 29, 'X TKJ 3' => 29,
            'XI TKJ 1' => 29, 'XI TKJ 2' => 35, 'XI TKJ 3' => 23,
            'XII TKJ 1' => 30, 'XII TKJ 2' => 34, 'XII TKJ 3' => 36,
        ], $perKelas);
        $this->assertSame(273, Siswa::count());
        $this->assertSame(0, Nilai::count());
        $this->assertSame('Ahmad Fauzi Rahman', Siswa::whereHas('kelas', fn ($q) => $q->where('nama', 'X TKJ 1'))->orderBy('nomor')->first()->nama);
    }

    public function test_impor_ulang_tidak_menggandakan_siswa(): void
    {
        $akun = $this->akun();
        $this->impor($akun, $this->berkasAsli())->assertOk();

        $this->impor($akun, $this->berkasAsli())->assertOk()
            ->assertJsonPath('totalDitambah', 0)->assertJsonPath('totalDilewati', 273);
        $this->assertSame(273, Siswa::count());
        $this->assertSame(9, Kelas::count());
    }

    public function test_impor_dua_akun_menghasilkan_data_terpisah(): void
    {
        $this->impor($this->akun(), $this->berkasAsli())->assertOk();
        $this->impor($this->akun(), $this->berkasAsli())->assertOk()->assertJsonPath('totalDitambah', 273);

        $this->assertSame(546, Siswa::count());
    }

    public function test_impor_membaca_nilai_dan_mengabaikan_yang_di_luar_0_100(): void
    {
        $akun = $this->akun();
        $berkas = $this->berkasBuatan([
            ['A' => 1, 'B' => 'Siti', 'C' => 'XI TKJ 1', 'D' => 80, 'E' => 101, 'O' => 92.5, 'P' => 'abc'],
            ['A' => 2, 'B' => 'Budi', 'C' => 'XI TKJ 1'],
            ['A' => 3, 'B' => 'Tanpa Kelas', 'C' => ''],
        ]);

        $this->impor($akun, $berkas)->assertOk()
            ->assertJsonPath('tahunAjaran', '2025/2026')->assertJsonPath('totalDitambah', 2)->assertJsonPath('barisDiabaikan', 1);

        $siswa = Siswa::where('nama', 'Siti')->first();
        $this->assertSame(80.0, $siswa->nilai->tes1);
        $this->assertNull($siswa->nilai->tes2);
        $this->assertSame(92.5, $siswa->nilai->praktik1);
        $this->assertNull($siswa->nilai->praktik2);
        $this->assertNull(Siswa::where('nama', 'Budi')->first()->nilai);
    }

    public function test_tahun_ajaran_bisa_ditentukan_lewat_parameter(): void
    {
        $this->impor($this->akun(), $this->berkasBuatan([['A' => 1, 'B' => 'Siti', 'C' => 'X TKJ 1']]), ['tahunAjaran' => '2030/2031'])
            ->assertOk()->assertJsonPath('tahunAjaran', '2030/2031');
    }

    public function test_impor_menolak_berkas_yang_bukan_format_template(): void
    {
        $akun = $this->akun();

        $this->impor($akun, UploadedFile::fake()->createWithContent('siswa.txt', 'bukan excel'))->assertStatus(400);

        $tanpaJudul = new Spreadsheet;
        $tanpaJudul->getActiveSheet()->setCellValue('A1', 'sembarang');
        $path = tempnam(sys_get_temp_dir(), 'uji-').'.xlsx';
        (new Xlsx($tanpaJudul))->save($path);
        $this->impor($akun, new UploadedFile($path, 'x.xlsx', null, null, true))
            ->assertStatus(400)->assertJsonPath('message', 'Kolom "NAMA SISWA" dan "KELAS" tidak ditemukan. Gunakan format template.');
        $this->assertSame(0, Siswa::count());
    }

    // ------------------------------------------------------------------ export xlsx

    public function test_export_xlsx_semua_kelas_mengikuti_template_asli(): void
    {
        $akun = $this->akun();
        $this->impor($akun, $this->berkasAsli())->assertOk();

        $res = $this->sebagai($akun)->get(self::API.'/nilai/export?format=xlsx');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Nilai-Siswa-TKJ-2026-2027.xlsx', $res->headers->get('Content-Disposition'));

        $hasil = $this->bukaXlsx($res)->getActiveSheet();
        $asli = IOFactory::load($this->templateNilaiAsli())->getActiveSheet();

        // Judul dan kepala tabel bertingkat = template.
        $this->assertSame('DAFTAR NAMA SISWA TKJ', $hasil->getCell('A1')->getValue());
        $this->assertSame('SMK BUDI MULIA KARAWANG', $hasil->getCell('A2')->getValue());
        $this->assertSame('TAHUN AJARAN 2026/2027', $hasil->getCell('A3')->getValue());
        foreach (['A5', 'B5', 'C5', 'D5', 'D6', 'M6', 'O6', 'X6'] as $sel) {
            $this->assertSame($asli->getCell($sel)->getValue(), $hasil->getCell($sel)->getValue(), $sel);
        }
        $this->assertSame('Nilai Praktik', $hasil->getCell('O5')->getValue());
        $this->assertEqualsCanonicalizing(['A5:A6', 'B5:B6', 'C5:C6', 'D5:M5', 'O5:X5'], array_keys($hasil->getMergeCells()));

        // Lebar kolom dan font berasal dari template.
        foreach (['B', 'C', 'D', 'N', 'O'] as $kol) {
            $this->assertEqualsWithDelta($asli->getColumnDimension($kol)->getWidth(), $hasil->getColumnDimension($kol)->getWidth(), 0.001, "lebar {$kol}");
        }
        // Q-X disamakan dengan O-P (di template terlalu sempit sehingga judulnya terpotong).
        foreach (range('Q', 'X') as $kol) {
            $this->assertEqualsWithDelta($asli->getColumnDimension('P')->getWidth(), $hasil->getColumnDimension($kol)->getWidth(), 0.001, "lebar {$kol}");
        }
        $this->assertSame('Times New Roman', $hasil->getStyle('B7')->getFont()->getName());

        // 273 siswa, nomor 1..273 berlanjut antar kelas, baris ke-274 (=280) tidak ada lagi.
        $this->assertSame([1, 'Ahmad Fauzi Rahman', 'X TKJ 1'], [$hasil->getCell('A7')->getValue(), $hasil->getCell('B7')->getValue(), $hasil->getCell('C7')->getValue()]);
        $this->assertSame(29, $hasil->getCell('A35')->getValue());
        $this->assertSame('X TKJ 2', $hasil->getCell('C35')->getValue());
        $this->assertSame(273, $hasil->getCell('A279')->getValue());
        $this->assertSame('XII TKJ 3', $hasil->getCell('C279')->getValue());
        $this->assertSame(279, $hasil->getHighestRow());
        $this->assertNull($hasil->getCell('B280')->getValue());

        // Garis sel tipis di seluruh baris data.
        $this->assertSame('thin', $hasil->getStyle('X279')->getBorders()->getBottom()->getBorderStyle());
        $this->assertSame('thin', $hasil->getStyle('N100')->getBorders()->getLeft()->getBorderStyle());
    }

    public function test_export_xlsx_memuat_nilai_dan_penomoran_per_kelas(): void
    {
        $akun = $this->akun();
        $kelasA = Kelas::factory()->create(['akunId' => $akun->id, 'nama' => 'X TKJ 1']);
        $kelasB = Kelas::factory()->create(['akunId' => $akun->id, 'nama' => 'X TKJ 2']);
        $a = Siswa::factory()->create(['kelasId' => $kelasA->id, 'nomor' => 1, 'nama' => 'Ani']);
        Siswa::factory()->create(['kelasId' => $kelasB->id, 'nomor' => 1, 'nama' => 'Budi']);
        Nilai::create(['siswaId' => $a->id, 'tes1' => 80, 'tes10' => 75.5, 'praktik1' => 90, 'praktik10' => 100]);

        $semua = $this->bukaXlsx($this->sebagai($akun)->get(self::API.'/nilai/export?format=xlsx'))->getActiveSheet();
        $this->assertEquals([80, 75.5, 90, 100], [$semua->getCell('D7')->getValue(), $semua->getCell('M7')->getValue(), $semua->getCell('O7')->getValue(), $semua->getCell('X7')->getValue()]);
        $this->assertNull($semua->getCell('E7')->getValue());
        $this->assertSame(2, $semua->getCell('A8')->getValue());

        $res = $this->sebagai($akun)->get(self::API.'/nilai/export?format=xlsx&kelasId='.$kelasB->id)->assertOk();
        $this->assertStringContainsString('Nilai-Siswa-x-tkj-2-2026-2027.xlsx', $res->headers->get('Content-Disposition'));
        $satu = $this->bukaXlsx($res)->getActiveSheet();
        $this->assertSame([1, 'Budi', 'X TKJ 2'], [$satu->getCell('A7')->getValue(), $satu->getCell('B7')->getValue(), $satu->getCell('C7')->getValue()]);
        $this->assertNull($satu->getCell('B8')->getValue());
    }

    public function test_nama_siswa_yang_diawali_sama_dengan_tidak_dijalankan_sebagai_rumus(): void
    {
        $akun = $this->akun();
        $kelas = Kelas::factory()->create(['akunId' => $akun->id, 'nama' => 'X TKJ 1']);
        Siswa::factory()->create(['kelasId' => $kelas->id, 'nomor' => 1, 'nama' => '=HYPERLINK("http://jahat.example","klik")']);

        $sel = $this->bukaXlsx($this->sebagai($akun)->get(self::API.'/nilai/export?format=xlsx'))->getActiveSheet()->getCell('B7');

        $this->assertSame(DataType::TYPE_STRING, $sel->getDataType());
        $this->assertSame('=HYPERLINK("http://jahat.example","klik")', $sel->getValue());
    }

    public function test_export_tidak_mengikutkan_kelas_akun_lain_dan_404_bila_kosong(): void
    {
        $lain = Kelas::factory()->create();
        $akun = $this->akun();

        $this->sebagai($akun)->getJson(self::API.'/nilai/export?format=xlsx')->assertStatus(404)->assertJsonPath('message', 'Belum ada kelas untuk diekspor');
        $this->sebagai($akun)->getJson(self::API.'/nilai/export?format=xlsx&kelasId='.$lain->id)->assertStatus(404);
        $this->sebagai($akun)->getJson(self::API.'/nilai/export?format=csv')->assertStatus(400);
    }

    public function test_format_kosong_berisi_kepala_tabel_dan_baris_kosong_berbingkai(): void
    {
        $sheet = $this->bukaXlsx($this->sebagai($this->akun())->get(self::API.'/nilai/format-kosong')->assertOk())->getActiveSheet();

        $this->assertSame('NAMA SISWA', $sheet->getCell('B5')->getValue());
        $this->assertNull($sheet->getCell('B7')->getValue());
        $this->assertSame('thin', $sheet->getStyle('X36')->getBorders()->getBottom()->getBorderStyle());
    }

    // ------------------------------------------------------------------ export pdf

    public function test_export_pdf_nilai_menghasilkan_pdf(): void
    {
        $akun = $this->akun();
        $this->impor($akun, $this->berkasAsli())->assertOk();

        $res = $this->sebagai($akun)->get(self::API.'/nilai/export?format=pdf')->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('Nilai-Siswa-TKJ-2026-2027.pdf', $res->headers->get('Content-Disposition'));
    }
}
