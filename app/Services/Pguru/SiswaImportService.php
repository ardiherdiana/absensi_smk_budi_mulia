<?php

namespace App\Services\Pguru;

use App\Models\Pguru\Kelas;
use App\Models\Pguru\Nilai;
use App\Models\Pguru\Siswa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Membaca daftar siswa dari Excel berformat template "FORMAT NILAI SISWA": kolom NO, NAMA SISWA,
 * KELAS, lalu (opsional) 10 nilai tes teori (D-M) dan 10 nilai praktik (O-X). Berkas hasil export
 * aplikasi ini juga bisa diimpor kembali.
 */
class SiswaImportService
{
    private const MAKS_BARIS = 3000;

    private const KOLOM_TES = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];

    private const KOLOM_PRAKTIK = ['O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'];

    /**
     * @return array{tahunAjaran: string, kelas: list<array{nama: string, ditambah: int, dilewati: int}>, totalDitambah: int, totalDilewati: int, barisDiabaikan: int}
     */
    public function impor(User $akun, UploadedFile $berkas, ?string $tahunAjaran): array
    {
        $sheet = $this->bukaLembar($berkas);
        $tahunAjaran ??= $this->tahunAjaranDariJudul($sheet) ?? $this->tahunAjaranSekarang();

        [$barisJudul, $kolomNama, $kolomKelas] = $this->cariJudulKolom($sheet);
        abort_if($barisJudul === null, 400, 'Kolom "NAMA SISWA" dan "KELAS" tidak ditemukan. Gunakan format template.');

        $denganNilai = $this->punyaKolomNilai($sheet, $barisJudul + 1);
        [$perKelas, $diabaikan] = $this->bacaBaris($sheet, $barisJudul, $kolomNama, $kolomKelas, $denganNilai);

        abort_if($perKelas === [], 400, 'Tidak ada data siswa yang bisa dibaca di berkas ini.');
        abort_if(array_sum(array_map('count', $perKelas)) > self::MAKS_BARIS, 400, 'Terlalu banyak baris (maksimal '.self::MAKS_BARIS.' siswa).');

        return DB::transaction(function () use ($akun, $perKelas, $tahunAjaran, $diabaikan) {
            $laporan = [];
            $totalDitambah = 0;
            $totalDilewati = 0;

            foreach ($perKelas as $namaKelas => $daftar) {
                $kelas = Kelas::firstOrCreate([
                    'akunId' => $akun->id,
                    'tahunAjaran' => $tahunAjaran,
                    'nama' => (string) $namaKelas,
                ]);

                $sudahAda = $kelas->siswa()->pluck('nama')->map(fn ($n) => mb_strtolower($n))->flip();
                $nomor = (int) $kelas->siswa()->max('nomor');
                $ditambah = 0;
                $dilewati = 0;

                foreach ($daftar as $baris) {
                    if ($sudahAda->has(mb_strtolower($baris['nama']))) {
                        $dilewati++;

                        continue;
                    }

                    $siswa = Siswa::create(['kelasId' => $kelas->id, 'nomor' => ++$nomor, 'nama' => $baris['nama']]);
                    if ($baris['nilai'] !== []) {
                        Nilai::create(['siswaId' => $siswa->id, ...$baris['nilai']]);
                    }
                    $sudahAda->put(mb_strtolower($baris['nama']), true);
                    $ditambah++;
                }

                $laporan[] = ['nama' => (string) $namaKelas, 'ditambah' => $ditambah, 'dilewati' => $dilewati];
                $totalDitambah += $ditambah;
                $totalDilewati += $dilewati;
            }

            return [
                'tahunAjaran' => $tahunAjaran,
                'kelas' => $laporan,
                'totalDitambah' => $totalDitambah,
                'totalDilewati' => $totalDilewati,
                'barisDiabaikan' => $diabaikan,
            ];
        });
    }

    private function bukaLembar(UploadedFile $berkas): Worksheet
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            return $reader->load($berkas->getRealPath())->getActiveSheet();
        } catch (Throwable) {
            abort(400, 'Berkas tidak bisa dibaca. Gunakan file Excel (.xlsx).');
        }
    }

    /** @return array{0: ?int, 1: string, 2: string} baris judul, huruf kolom nama, huruf kolom kelas */
    private function cariJudulKolom(Worksheet $sheet): array
    {
        $maks = min(30, $sheet->getHighestDataRow());
        $hurufTertinggi = min(30, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($baris = 1; $baris <= $maks; $baris++) {
            $nama = $kelas = null;
            for ($kol = 1; $kol <= $hurufTertinggi; $kol++) {
                $huruf = Coordinate::stringFromColumnIndex($kol);
                $teks = mb_strtoupper(trim((string) $sheet->getCell("{$huruf}{$baris}")->getValue()));
                if ($teks === 'NAMA SISWA' || $teks === 'NAMA') {
                    $nama = $huruf;
                } elseif ($teks === 'KELAS') {
                    $kelas = $huruf;
                }
            }
            if ($nama !== null && $kelas !== null) {
                return [$baris, $nama, $kelas];
            }
        }

        return [null, 'B', 'C'];
    }

    /** Sub-judul "Nilai Tes 1" di D menandakan kolom nilai ada di posisi template. */
    private function punyaKolomNilai(Worksheet $sheet, int $barisSubJudul): bool
    {
        return str_contains(mb_strtolower((string) $sheet->getCell("D{$barisSubJudul}")->getValue()), 'nilai');
    }

    /**
     * @return array{0: array<string, list<array{nama: string, nilai: array<string, float>}>>, 1: int}
     */
    private function bacaBaris(Worksheet $sheet, int $barisJudul, string $kolomNama, string $kolomKelas, bool $denganNilai): array
    {
        $perKelas = [];
        $diabaikan = 0;
        $akhir = min($sheet->getHighestDataRow(), $barisJudul + self::MAKS_BARIS + 50);

        for ($baris = $barisJudul + 1; $baris <= $akhir; $baris++) {
            $nama = $this->rapikan((string) $sheet->getCell("{$kolomNama}{$baris}")->getValue(), 150);
            $kelas = $this->rapikan((string) $sheet->getCell("{$kolomKelas}{$baris}")->getValue(), 100);

            // Baris sub-judul (sel gabungan) dan baris kosong tidak punya nama.
            if ($nama === '') {
                continue;
            }
            if ($kelas === '') {
                $diabaikan++;

                continue;
            }

            $perKelas[$kelas][] = [
                'nama' => $nama,
                'nilai' => $denganNilai ? $this->bacaNilai($sheet, $baris) : [],
            ];
        }

        return [$perKelas, $diabaikan];
    }

    /** @return array<string, float> hanya sel yang berisi angka 0-100 */
    private function bacaNilai(Worksheet $sheet, int $baris): array
    {
        $hasil = [];
        foreach ([['tes', self::KOLOM_TES], ['praktik', self::KOLOM_PRAKTIK]] as [$jenis, $kolomKolom]) {
            foreach ($kolomKolom as $i => $huruf) {
                $nilai = $sheet->getCell("{$huruf}{$baris}")->getValue();
                if (is_numeric($nilai) && $nilai >= 0 && $nilai <= 100) {
                    $hasil[$jenis.($i + 1)] = round((float) $nilai, 2);
                }
            }
        }

        return $hasil;
    }

    private function tahunAjaranDariJudul(Worksheet $sheet): ?string
    {
        for ($baris = 1; $baris <= 6; $baris++) {
            $teks = (string) $sheet->getCell("A{$baris}")->getValue();
            if (preg_match('/(\d{4})\s*\/\s*(\d{4})/', $teks, $m)) {
                return "{$m[1]}/{$m[2]}";
            }
        }

        return null;
    }

    /** Tahun ajaran SMK dimulai Juli. */
    private function tahunAjaranSekarang(): string
    {
        $tahun = (int) now()->format('Y');
        $awal = (int) now()->format('n') >= 7 ? $tahun : $tahun - 1;

        return $awal.'/'.($awal + 1);
    }

    private function rapikan(string $teks, int $maks): string
    {
        return mb_substr(preg_replace('/\s+/u', ' ', trim($teks)), 0, $maks);
    }
}
