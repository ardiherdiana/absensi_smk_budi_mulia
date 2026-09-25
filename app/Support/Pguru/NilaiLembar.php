<?php

namespace App\Support\Pguru;

use App\Models\Pguru\Kelas;
use App\Models\Pguru\Nilai;
use Illuminate\Support\Collection;

/**
 * Isi lembar "DAFTAR NAMA SISWA" yang sama untuk export xlsx dan PDF. Penomoran berlanjut antar
 * kelas (1..N) seperti di template asli.
 */
final class NilaiLembar
{
    /**
     * @param  list<array{no: int, nama: string, kelas: string, tes: list<?float>, praktik: list<?float>}>  $baris
     */
    public function __construct(
        public readonly string $judul,
        public readonly string $sekolah,
        public readonly string $tahunAjaran,
        public readonly array $baris,
    ) {}

    /** @param  Collection<int, Kelas>  $daftarKelas  siswa.nilai sudah di-eager-load, sudah berurutan */
    public static function dari(Collection $daftarKelas, string $tahunAjaran): self
    {
        $jurusan = $daftarKelas->map(fn (Kelas $k) => $k->jurusan())->unique()->values();
        $judul = 'DAFTAR NAMA SISWA'.($jurusan->count() === 1 && $jurusan[0] !== null ? ' '.$jurusan[0] : '');

        $baris = [];
        $no = 0;
        foreach ($daftarKelas as $kelas) {
            foreach ($kelas->siswa as $siswa) {
                $nilai = $siswa->nilai;
                $baris[] = [
                    'no' => ++$no,
                    'nama' => $siswa->nama,
                    'kelas' => $kelas->nama,
                    'tes' => array_map(fn (string $k) => $nilai?->getAttribute($k), Nilai::kolomTes()),
                    'praktik' => array_map(fn (string $k) => $nilai?->getAttribute($k), Nilai::kolomPraktik()),
                ];
            }
        }

        return new self($judul, config('pguru.nama_sekolah'), 'TAHUN AJARAN '.$tahunAjaran, $baris);
    }

    /** Template kosong: hanya judul dan kepala tabel. */
    public static function kosong(string $tahunAjaran): self
    {
        return new self('DAFTAR NAMA SISWA', config('pguru.nama_sekolah'), 'TAHUN AJARAN '.$tahunAjaran, []);
    }

    /** 85 -> "85", 85.5 -> "85,5" (koma desimal, seperti Excel berlokal Indonesia). */
    public static function angka(?float $nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        return str_replace('.', ',', rtrim(rtrim(number_format($nilai, 2, '.', ''), '0'), '.'));
    }
}
