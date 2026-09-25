<?php

namespace Tests\Feature\Pguru;

use App\Models\Pguru\Supervisi;
use DOMDocument;
use DOMXPath;
use ZipArchive;

class SupervisiTest extends PguruTestCase
{
    private function payload(array $override = []): array
    {
        return $override + [
            'guruDinilai' => 'Asep Wahyudin, S.Kom',
            'pemberiUmpanBalik' => 'Latif Ashari',
            'tanggal' => '2026-09-24',
            'butir' => [
                ['nomor' => 1, 'bukti' => 'RPP bagian awal dilaksanakan', 'catatan' => 'Sudah selaras'],
                ['nomor' => 15, 'bukti' => '', 'catatan' => 'Rubrik jelas'],
            ],
            'refleksi' => ['Pelajaran: diskusi berjalan baik', '', 'Tindak lanjut:'."\n".'perbaiki waktu'],
        ];
    }

    /** @return array{0: string, 1: DOMDocument, 2: DOMXPath, 3: string} xml mentah, dokumen, xpath, core.xml */
    private function bukaDocx(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-').'.docx';
        file_put_contents($path, $isi);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('word/document.xml');
        $core = $zip->getFromName('docProps/core.xml');
        $entri = $zip->numFiles;
        $zip->close();
        $this->assertSame(15, $entri, 'jumlah berkas di dalam docx sama dengan template');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'document.xml harus XML yang valid');
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        return [$xml, $dom, $xp, $core];
    }

    public function test_instrumen_memuat_15_butir_dalam_4_bagian_dan_3_refleksi(): void
    {
        $data = $this->sebagai($this->akun())->getJson(self::API.'/supervisi/instrumen')->assertOk()->json();

        $this->assertSame(['Keselarasan', 'Implementasi Kerangka Pembelajaran', 'Langkah Pembelajaran', 'Asesmen'], array_column($data['bagian'], 'judul'));
        $this->assertSame(range(1, 15), collect($data['bagian'])->flatMap(fn ($b) => array_column($b['butir'], 'nomor'))->all());
        $this->assertSame([16, 17, 18], array_column($data['refleksi'], 'nomor'));
        $this->assertSame('awal pembelajaran', $data['bagian'][0]['butir'][0]['paragraf'][1]['teks']);
        $this->assertTrue($data['bagian'][0]['butir'][0]['paragraf'][1]['daftar']);
    }

    public function test_buat_lihat_ubah_hapus_supervisi_selalu_berbentuk_lengkap(): void
    {
        $akun = $this->akun();

        $id = $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload())->assertCreated()
            ->assertJsonCount(15, 'data.butir')->assertJsonCount(3, 'data.refleksi')
            ->assertJsonPath('data.butir.0.bukti', 'RPP bagian awal dilaksanakan')
            ->assertJsonPath('data.butir.1.bukti', '')
            ->assertJsonPath('data.butir.14.catatan', 'Rubrik jelas')
            ->assertJsonPath('data.tanggal', '2026-09-24')->json('data.id');

        $this->sebagai($akun)->getJson(self::API.'/supervisi')->assertOk()->assertJsonPath('data.0.guruDinilai', 'Asep Wahyudin, S.Kom');

        $this->sebagai($akun)->putJson(self::API."/supervisi/{$id}", $this->payload(['guruDinilai' => 'Guru Baru', 'butir' => [], 'refleksi' => []]))
            ->assertOk()->assertJsonPath('data.guruDinilai', 'Guru Baru')->assertJsonPath('data.butir.0.bukti', '');

        $this->sebagai($akun)->deleteJson(self::API."/supervisi/{$id}")->assertOk();
        $this->sebagai($akun)->getJson(self::API."/supervisi/{$id}")->assertStatus(404);
    }

    public function test_validasi_supervisi(): void
    {
        $akun = $this->akun();

        $this->sebagai($akun)->postJson(self::API.'/supervisi', [])->assertStatus(400)
            ->assertJsonPath('errors.guruDinilai.0', 'Penyusun perencanaan wajib diisi');
        $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload(['tanggal' => '24/09/2026']))->assertStatus(400);
        $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload(['butir' => [['nomor' => 16, 'bukti' => 'x']]]))->assertStatus(400);
        $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload(['refleksi' => ['a', 'b', 'c', 'd']]))->assertStatus(400);
    }

    public function test_supervisi_hanya_bisa_diakses_pemiliknya(): void
    {
        $milik = Supervisi::factory()->create();
        $lain = $this->akun();

        $this->sebagai($lain)->getJson(self::API.'/supervisi')->assertJsonCount(0, 'data');
        foreach (['getJson' => '', 'putJson' => '', 'deleteJson' => ''] as $metode => $_) {
            $this->sebagai($lain)->{$metode}(self::API."/supervisi/{$milik->id}", $metode === 'putJson' ? $this->payload() : [])->assertStatus(404);
        }
        $this->sebagai($lain)->getJson(self::API."/supervisi/{$milik->id}/export?format=docx")->assertStatus(404);
        $this->assertDatabaseHas('pguru_supervisi', ['id' => $milik->id]);
    }

    public function test_export_docx_mengisi_template_asli_tanpa_mengubah_strukturnya(): void
    {
        $akun = $this->akun();
        $id = $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload())->json('data.id');

        $res = $this->sebagai($akun)->get(self::API."/supervisi/{$id}/export?format=docx")->assertOk();
        $this->assertStringContainsString('wordprocessingml.document', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Supervisi-asep-wahyudin-skom-2026-09-24.docx', $res->headers->get('Content-Disposition'));

        [$xml, $dom, $xp, $core] = $this->bukaDocx($res->streamedContent());
        $teks = $dom->documentElement->textContent;

        // Isian masuk.
        foreach (['Asep Wahyudin, S.Kom', 'Latif Ashari', 'Kamis, 24 September 2026', 'RPP bagian awal dilaksanakan', 'Sudah selaras', 'Rubrik jelas', 'Pelajaran: diskusi berjalan baik', 'perbaiki waktu', 'Karawang, 24 September 2026'] as $harapan) {
            $this->assertStringContainsString($harapan, $teks, $harapan);
        }
        // Baris titik-titik diganti nama penanda tangan.
        $this->assertStringNotContainsString('..........................', $teks);
        // Teks baku template tetap ada dan strukturnya utuh: 24 baris tabel, 15 butir + 3 refleksi bernomor.
        $this->assertStringContainsString('INSTRUMEN OBSERVASI IMPLEMENTASI DAN REFLEKSI', $teks);
        $this->assertStringContainsString('Bukti Pembelajaran', $teks);
        $this->assertSame(24, $xp->query('//w:tbl/w:tr')->length);
        $this->assertSame(1, $xp->query('//w:tbl')->length);
        // Kolom Bukti butir 1 diisi di paragraf kosong bawaan (tidak menambah paragraf), dengan font template.
        $bukti1 = $xp->query('//w:tbl/w:tr[3]/w:tc[3]/w:p')->item(0);
        $this->assertSame('RPP bagian awal dilaksanakan', trim($bukti1->textContent));
        $this->assertSame('Arial', $xp->query('.//w:rFonts/@w:ascii', $bukti1)->item(0)->nodeValue);
        // Baris baru pada isian menjadi <w:br/>.
        $this->assertSame(1, $xp->query('//w:tbl/w:tr[24]//w:br')->length);
        // Nama pembuat asli template tidak ikut terbawa.
        $this->assertStringNotContainsString('Asep Wahyudin', $core);
        $this->assertStringNotContainsString('LATIF_AS', $core);
        $this->assertStringContainsString('Perangkat Guru SMK Budi Mulia', $core);
    }

    public function test_export_docx_aman_untuk_karakter_khusus_dan_karakter_kontrol(): void
    {
        $akun = $this->akun();
        $id = $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload([
            'guruDinilai' => 'Tom & Jerry <b>"x"</b>',
            'butir' => [['nomor' => 2, 'bukti' => "A & B <script>\x01\x08 ok", 'catatan' => '=1+1']],
        ]))->assertCreated()->json('data.id');

        $res = $this->sebagai($akun)->get(self::API."/supervisi/{$id}/export?format=docx")->assertOk();
        [, $dom] = $this->bukaDocx($res->streamedContent());

        $this->assertStringContainsString('Tom & Jerry <b>"x"</b>', $dom->documentElement->textContent);
        $this->assertStringContainsString('A & B <script> ok', $dom->documentElement->textContent);
    }

    public function test_export_pdf_supervisi(): void
    {
        $akun = $this->akun();
        $id = $this->sebagai($akun)->postJson(self::API.'/supervisi', $this->payload())->json('data.id');

        $res = $this->sebagai($akun)->get(self::API."/supervisi/{$id}/export?format=pdf")->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->sebagai($akun)->getJson(self::API."/supervisi/{$id}/export?format=odt")->assertStatus(400);
    }
}
