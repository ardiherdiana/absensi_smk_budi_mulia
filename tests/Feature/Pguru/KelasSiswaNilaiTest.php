<?php

namespace Tests\Feature\Pguru;

use App\Models\Pguru\Kelas;
use App\Models\Pguru\Nilai;
use App\Models\Pguru\Siswa;

class KelasSiswaNilaiTest extends PguruTestCase
{
    public function test_kelas_diurutkan_tahun_ajaran_terbaru_lalu_nama_alami(): void
    {
        $akun = $this->akun();
        foreach ([['XII TKJ 1', '2026/2027'], ['X TKJ 2', '2026/2027'], ['XI TKJ 1', '2026/2027'], ['X TKJ 10', '2026/2027'], ['X TKJ 1', '2026/2027'], ['X TKJ 1', '2025/2026']] as [$nama, $ta]) {
            Kelas::factory()->create(['akunId' => $akun->id, 'nama' => $nama, 'tahunAjaran' => $ta]);
        }

        $urutan = collect($this->sebagai($akun)->getJson(self::API.'/kelas')->assertOk()->json('data'))
            ->map(fn ($k) => $k['tahunAjaran'].' '.$k['nama'])->all();

        $this->assertSame([
            '2026/2027 X TKJ 1', '2026/2027 X TKJ 2', '2026/2027 X TKJ 10', '2026/2027 XI TKJ 1', '2026/2027 XII TKJ 1', '2025/2026 X TKJ 1',
        ], $urutan);
    }

    public function test_kelas_hanya_terlihat_oleh_pemiliknya(): void
    {
        $milikA = Kelas::factory()->create();
        $b = $this->akun();

        $this->sebagai($b)->getJson(self::API.'/kelas')->assertOk()->assertJsonCount(0, 'data');
        $this->sebagai($b)->getJson(self::API.'/kelas/'.$milikA->id.'/siswa')->assertStatus(404);
        $this->sebagai($b)->putJson(self::API.'/kelas/'.$milikA->id, ['nama' => 'X TKJ 9', 'tahunAjaran' => '2026/2027'])->assertStatus(404);
        $this->sebagai($b)->deleteJson(self::API.'/kelas/'.$milikA->id)->assertStatus(404);
        $this->assertDatabaseHas('pguru_kelas', ['id' => $milikA->id]);
    }

    public function test_buat_ubah_hapus_kelas(): void
    {
        $akun = $this->akun();

        $id = $this->sebagai($akun)->postJson(self::API.'/kelas', ['nama' => '  X   TKJ 1 ', 'tahunAjaran' => '2026/2027'])
            ->assertCreated()->assertJsonPath('data.nama', 'X TKJ 1')->json('data.id');

        $this->sebagai($akun)->putJson(self::API.'/kelas/'.$id, ['nama' => 'X TKJ 1', 'tahunAjaran' => '2026/2027'])->assertOk();
        $this->sebagai($akun)->putJson(self::API.'/kelas/'.$id, ['nama' => 'X TKJ 2', 'tahunAjaran' => '2027/2028'])
            ->assertOk()->assertJsonPath('data.tahunAjaran', '2027/2028');
        $this->sebagai($akun)->deleteJson(self::API.'/kelas/'.$id)->assertOk();
        $this->assertDatabaseMissing('pguru_kelas', ['id' => $id]);
    }

    public function test_nama_kelas_unik_per_tahun_ajaran_dan_format_tahun_diperiksa(): void
    {
        $akun = $this->akun();
        Kelas::factory()->create(['akunId' => $akun->id, 'nama' => 'X TKJ 1', 'tahunAjaran' => '2026/2027']);

        $this->sebagai($akun)->postJson(self::API.'/kelas', ['nama' => 'X TKJ 1', 'tahunAjaran' => '2026/2027'])
            ->assertStatus(400)->assertJsonPath('errors.nama.0', 'Nama kelas sudah terdaftar');
        $this->sebagai($akun)->postJson(self::API.'/kelas', ['nama' => 'X TKJ 1', 'tahunAjaran' => '2027/2028'])->assertCreated();
        $this->sebagai($akun)->postJson(self::API.'/kelas', ['nama' => 'X TKJ 2', 'tahunAjaran' => '2026-2027'])
            ->assertStatus(400)->assertJsonPath('errors.tahunAjaran.0', 'Format Tahun ajaran tidak valid');
        // Akun lain boleh memakai nama yang sama.
        $this->sebagai($this->akun())->postJson(self::API.'/kelas', ['nama' => 'X TKJ 1', 'tahunAjaran' => '2026/2027'])->assertCreated();
    }

    public function test_siswa_ditambah_berurutan_diubah_dan_dihapus(): void
    {
        $akun = $this->akun();
        $kelas = Kelas::factory()->create(['akunId' => $akun->id]);

        $a = $this->sebagai($akun)->postJson(self::API."/kelas/{$kelas->id}/siswa", ['nama' => ' Ahmad  Fauzi '])
            ->assertCreated()->assertJsonPath('data.nama', 'Ahmad Fauzi')->assertJsonPath('data.nomor', 1)->json('data.id');
        $this->sebagai($akun)->postJson(self::API."/kelas/{$kelas->id}/siswa", ['nama' => 'Bagas'])->assertJsonPath('data.nomor', 2);

        $this->sebagai($akun)->putJson(self::API."/siswa/{$a}", ['nama' => 'Ahmad F.'])->assertOk()->assertJsonPath('data.nama', 'Ahmad F.');
        $this->sebagai($akun)->deleteJson(self::API."/siswa/{$a}")->assertOk();

        $this->sebagai($akun)->getJson(self::API."/kelas/{$kelas->id}/siswa")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Bagas');
    }

    public function test_siswa_milik_akun_lain_tidak_bisa_diubah(): void
    {
        $siswa = Siswa::factory()->create();

        $this->sebagai($this->akun())->putJson(self::API."/siswa/{$siswa->id}", ['nama' => 'X'])->assertStatus(404);
        $this->sebagai($this->akun())->deleteJson(self::API."/siswa/{$siswa->id}")->assertStatus(404);
        $this->sebagai($this->akun())->postJson(self::API."/kelas/{$siswa->kelasId}/siswa", ['nama' => 'X'])->assertStatus(404);
    }

    public function test_nilai_satu_kolom_untuk_banyak_siswa_disimpan_dan_bisa_dikosongkan(): void
    {
        $akun = $this->akun();
        $kelas = Kelas::factory()->create(['akunId' => $akun->id]);
        [$a, $b] = Siswa::factory()->count(2)->create(['kelasId' => $kelas->id]);

        $this->sebagai($akun)->putJson(self::API."/kelas/{$kelas->id}/nilai", [
            'kolom' => 'tes3',
            'nilai' => [['siswaId' => $a->id, 'nilai' => 85.5], ['siswaId' => $b->id, 'nilai' => '90']],
        ])->assertOk()->assertJsonPath('diperbarui', 2);

        $this->sebagai($akun)->putJson(self::API."/kelas/{$kelas->id}/nilai", [
            'kolom' => 'praktik10', 'nilai' => [['siswaId' => $a->id, 'nilai' => 100]],
        ])->assertOk();

        $data = collect($this->sebagai($akun)->getJson(self::API."/kelas/{$kelas->id}/siswa")->json('data'))->keyBy('id');
        // JSON tidak membedakan 90 dan 90.0, jadi bandingkan nilainya saja.
        $this->assertEquals(85.5, $data[$a->id]['nilai']['tes3']);
        $this->assertEquals(90, $data[$b->id]['nilai']['tes3']);
        $this->assertEquals(100, $data[$a->id]['nilai']['praktik10']);
        $this->assertNull($data[$b->id]['nilai']['praktik10']);
        $this->assertNull($data[$a->id]['nilai']['tes1']);

        $this->sebagai($akun)->putJson(self::API."/kelas/{$kelas->id}/nilai", [
            'kolom' => 'tes3', 'nilai' => [['siswaId' => $a->id, 'nilai' => null]],
        ])->assertOk();
        $this->assertNull(Nilai::where('siswaId', $a->id)->first()->tes3);
        // Satu baris nilai per siswa, tidak menumpuk.
        $this->assertSame(1, Nilai::where('siswaId', $a->id)->count());
    }

    public function test_nilai_ditolak_bila_tidak_valid(): void
    {
        $akun = $this->akun();
        $kelas = Kelas::factory()->create(['akunId' => $akun->id]);
        $siswa = Siswa::factory()->create(['kelasId' => $kelas->id]);
        $lain = Siswa::factory()->create();
        $url = self::API."/kelas/{$kelas->id}/nilai";

        $this->sebagai($akun)->putJson($url, ['kolom' => 'tes11', 'nilai' => [['siswaId' => $siswa->id, 'nilai' => 80]]])
            ->assertStatus(400)->assertJsonPath('errors.kolom.0', 'Kolom nilai tidak valid');
        $this->sebagai($akun)->putJson($url, ['kolom' => 'tes1; DROP TABLE x', 'nilai' => [['siswaId' => $siswa->id, 'nilai' => 80]]])
            ->assertStatus(400);
        $this->sebagai($akun)->putJson($url, ['kolom' => 'tes1', 'nilai' => [['siswaId' => $siswa->id, 'nilai' => 101]]])
            ->assertStatus(400);
        $this->sebagai($akun)->putJson($url, ['kolom' => 'tes1', 'nilai' => [['siswaId' => $siswa->id, 'nilai' => 'abc']]])
            ->assertStatus(400);
        $this->sebagai($akun)->putJson($url, ['kolom' => 'tes1', 'nilai' => [['siswaId' => $lain->id, 'nilai' => 80]]])
            ->assertStatus(400)->assertJsonPath('message', 'Ada siswa yang bukan bagian dari kelas ini');
        $this->assertDatabaseCount('pguru_nilai', 0);
    }

    public function test_nilai_kelas_akun_lain_ditolak(): void
    {
        $siswa = Siswa::factory()->create();

        $this->sebagai($this->akun())->putJson(self::API."/kelas/{$siswa->kelasId}/nilai", [
            'kolom' => 'tes1', 'nilai' => [['siswaId' => $siswa->id, 'nilai' => 80]],
        ])->assertStatus(404);
        $this->assertDatabaseCount('pguru_nilai', 0);
    }
}
