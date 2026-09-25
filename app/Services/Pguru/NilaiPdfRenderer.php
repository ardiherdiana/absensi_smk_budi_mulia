<?php

namespace App\Services\Pguru;

use App\Support\Pguru\NilaiLembar;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Menggambar lembar nilai langsung di canvas dompdf (garis + teks), tanpa layout HTML. Lembar berisi
 * hingga ratusan baris x 24 kolom; lewat HTML/CSS dompdf butuh ~150 MB untuk 273 baris, lewat canvas
 * memorinya hampir konstan. Geometri meniru Excel "Fit to 1 page wide": proporsi lebar kolom sama
 * dengan template, Times New Roman (Times-Roman), garis tipis, kepala tabel dua tingkat yang diulang
 * di tiap halaman.
 */
class NilaiPdfRenderer
{
    private const HALAMAN_LEBAR = 936.0;    // folio landscape (pt)

    private const HALAMAN_TINGGI = 612.0;

    private const MARGIN = 24.0;

    /**
     * Lebar kolom template dalam piksel Excel (lebar-karakter x 7). Kolom A memakai lebar bawaan (64 px);
     * Q-X dilebarkan sama dengan O-P (lihat NilaiXlsxExporter) agar judul kolom tidak terpotong.
     */
    private const LEBAR_PX = [64, 256.6, 83.2, 75.4, 77, 77, 77, 77, 77, 77, 77, 77, 84.8, 10.1, 103.4, 103.4, 103.4, 103.4, 103.4, 103.4, 103.4, 103.4, 103.4, 103.4];

    private const TINGGI_BARIS_EXCEL = 14.4;

    private const UKURAN_HURUF_EXCEL = 11.0;

    private Canvas $kanvas;

    private string $huruf;

    private float $ukuran;

    private float $tinggi;

    /** @var list<float> batas kiri tiap kolom + batas kanan terakhir */
    private array $x = [];

    private float $y = 0;

    public function render(NilaiLembar $lembar): string
    {
        $dompdf = new Dompdf((new Options)->set('isRemoteEnabled', false));
        $dompdf->setPaper([0, 0, self::HALAMAN_LEBAR, self::HALAMAN_TINGGI]);
        $dompdf->loadHtml('<html><body></body></html>');
        $dompdf->render();

        $this->kanvas = $dompdf->getCanvas();
        $this->huruf = $dompdf->getFontMetrics()->getFont('times', 'normal');
        $this->hitungUkuran();

        $this->y = self::MARGIN;
        foreach ([$lembar->judul, $lembar->sekolah, $lembar->tahunAjaran, ''] as $baris) {
            $this->teks($baris, $this->x[0], $this->y, $this->x[24] - $this->x[0], 'kiri', 0);
            $this->y += $this->tinggi;
        }
        $this->kepalaTabel();

        $data = $lembar->baris !== [] ? $lembar->baris : $this->barisKosong();
        foreach ($data as $baris) {
            if ($this->y + $this->tinggi > self::HALAMAN_TINGGI - self::MARGIN + 0.01) {
                $this->halamanBaru();
            }
            $this->barisData($baris);
        }

        return $dompdf->output();
    }

    private function hitungUkuran(): void
    {
        $lebarExcelPt = array_sum(self::LEBAR_PX) * 0.75;
        $skala = (self::HALAMAN_LEBAR - 2 * self::MARGIN) / $lebarExcelPt;
        $this->ukuran = self::UKURAN_HURUF_EXCEL * $skala;
        $this->tinggi = self::TINGGI_BARIS_EXCEL * $skala;

        $this->x = [self::MARGIN];
        foreach (self::LEBAR_PX as $px) {
            $this->x[] = end($this->x) + $px * 0.75 * $skala;
        }
    }

    private function halamanBaru(): void
    {
        $this->kanvas->new_page();
        $this->y = self::MARGIN;
        $this->kepalaTabel();
    }

    private function kepalaTabel(): void
    {
        $atas = $this->y;
        $bawah = $atas + $this->tinggi;

        // Baris 1: NO / NAMA SISWA / KELAS setinggi dua baris, lalu judul kelompok yang digabung.
        foreach ([[0, 'NO'], [1, 'NAMA SISWA'], [2, 'KELAS']] as [$kolom, $teks]) {
            $this->sel($kolom, $kolom, $atas, 2 * $this->tinggi, $teks, 'tengah');
        }
        $this->sel(3, 12, $atas, $this->tinggi, 'Nilai TES TEORI', 'tengah');
        $this->sel(13, 13, $atas, $this->tinggi, '', 'tengah');
        $this->sel(14, 23, $atas, $this->tinggi, 'Nilai Praktik', 'tengah');

        // Baris 2: kolom nilai bernomor.
        for ($i = 0; $i < 10; $i++) {
            $this->sel(3 + $i, 3 + $i, $bawah, $this->tinggi, 'Nilai Tes '.($i + 1), 'tengah');
            $this->sel(14 + $i, 14 + $i, $bawah, $this->tinggi, 'Nilai Praktik '.($i + 1), 'tengah');
        }
        $this->sel(13, 13, $bawah, $this->tinggi, '', 'tengah');

        $this->y = $atas + 2 * $this->tinggi;
    }

    /** @param  array{no: int, nama: string, kelas: string, tes: list<?float>, praktik: list<?float>}  $baris */
    private function barisData(array $baris): void
    {
        $atas = $this->y;
        $bawah = $atas + $this->tinggi;

        $this->kanvas->line($this->x[0], $bawah, $this->x[24], $bawah, [0, 0, 0], 0.5);
        foreach ($this->x as $garis) {
            $this->kanvas->line($garis, $atas, $garis, $bawah, [0, 0, 0], 0.5);
        }

        $this->teks((string) $baris['no'], $this->x[0], $atas, $this->x[1] - $this->x[0], 'kanan');
        $this->teks($baris['nama'], $this->x[1], $atas, $this->x[2] - $this->x[1], 'kiri', 9 * 0.75 * $this->skalaKolom());
        $this->teks($baris['kelas'], $this->x[2], $atas, $this->x[3] - $this->x[2], 'kiri');
        foreach ($baris['tes'] as $i => $nilai) {
            $this->teks(NilaiLembar::angka($nilai), $this->x[3 + $i], $atas, $this->x[4 + $i] - $this->x[3 + $i], 'kanan');
        }
        foreach ($baris['praktik'] as $i => $nilai) {
            $this->teks(NilaiLembar::angka($nilai), $this->x[14 + $i], $atas, $this->x[15 + $i] - $this->x[14 + $i], 'kanan');
        }

        $this->y = $bawah;
    }

    private function skalaKolom(): float
    {
        return $this->tinggi / self::TINGGI_BARIS_EXCEL;
    }

    /** Sel berbingkai yang membentang dari kolom $dari sampai $sampai. */
    private function sel(int $dari, int $sampai, float $atas, float $tinggi, string $teks, string $rata): void
    {
        $kiri = $this->x[$dari];
        $lebar = $this->x[$sampai + 1] - $kiri;
        $this->kanvas->rectangle($kiri, $atas, $lebar, $tinggi, [0, 0, 0], 0.5);
        $this->teks($teks, $kiri, $atas, $lebar, $rata, 0, $tinggi);
    }

    /** Teks satu baris, terpusat vertikal di sel; dipotong bila lebih lebar dari sel (seperti Excel). */
    private function teks(string $teks, float $kiri, float $atas, float $lebar, string $rata, float $indent = 0, ?float $tinggiSel = null): void
    {
        if ($teks === '') {
            return;
        }

        $padding = 1.5;
        $tersedia = $lebar - 2 * $padding - $indent;
        while ($teks !== '' && $this->kanvas->get_text_width($teks, $this->huruf, $this->ukuran) > $tersedia) {
            $teks = mb_substr($teks, 0, -1);
        }
        if ($teks === '') {
            return;
        }

        $w = $this->kanvas->get_text_width($teks, $this->huruf, $this->ukuran);
        $x = match ($rata) {
            'kanan' => $kiri + $lebar - $padding - $w,
            'tengah' => $kiri + ($lebar - $w) / 2,
            default => $kiri + $padding + $indent,
        };
        $tinggiSel ??= $this->tinggi;
        $y = $atas + ($tinggiSel - $this->kanvas->get_font_height($this->huruf, $this->ukuran)) / 2;

        $this->kanvas->text($x, $y, $teks, $this->huruf, $this->ukuran, [0, 0, 0]);
    }

    /** @return list<array{no: string, nama: string, kelas: string, tes: list<null>, praktik: list<null>}> */
    private function barisKosong(): array
    {
        return array_fill(0, 30, ['no' => '', 'nama' => '', 'kelas' => '', 'tes' => array_fill(0, 10, null), 'praktik' => array_fill(0, 10, null)]);
    }
}
