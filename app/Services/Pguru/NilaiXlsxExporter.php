<?php

namespace App\Services\Pguru;

use App\Support\Pguru\NilaiLembar;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengisi template resmi "FORMAT NILAI SISWA" (resources/pguru/format-nilai-siswa.xlsx) apa adanya:
 * judul, kepala tabel bertingkat, lebar kolom, sel gabungan, dan font Times New Roman berasal dari
 * file aslinya. Baris data di template tidak seragam gayanya (sisa salin-tempel), jadi baris data
 * digambar ulang dengan satu gaya yang sama dengan mayoritas baris template.
 */
class NilaiXlsxExporter
{
    private const BARIS_PERTAMA = 7;

    private const KOLOM_TES_AWAL = 4;      // D

    private const KOLOM_PRAKTIK_AWAL = 15; // O

    private const BARIS_TEMPLATE_KOSONG = 30;

    public function buat(NilaiLembar $lembar): Spreadsheet
    {
        $spreadsheet = IOFactory::load(config('pguru.templates.nilai'));
        $sheet = $spreadsheet->getActiveSheet();

        // Buang baris data contoh (7..akhir); judul dan kepala tabel (1-6) tetap dari template.
        $sheet->removeRow(self::BARIS_PERTAMA, max(1, $sheet->getHighestRow() - self::BARIS_PERTAMA + 1));

        $this->teks($sheet, 'A1', $lembar->judul);
        $this->teks($sheet, 'A3', $lembar->tahunAjaran);
        // Template menulis "Nilai Praktik 1" di sel gabungan O5:X5; itu judul kelompok, bukan kolom pertama.
        $this->teks($sheet, 'O5', 'Nilai Praktik');

        // Kolom Q-X di template tidak pernah dilebarkan (hanya O-P), sehingga judul "Nilai Praktik 3..10"
        // terpotong. Samakan dengan O-P.
        $lebarPraktik = $sheet->getColumnDimension('P')->getWidth();
        foreach (range('Q', 'X') as $kolom) {
            $sheet->getColumnDimension($kolom)->setWidth($lebarPraktik);
        }

        $baris = self::BARIS_PERTAMA;
        foreach ($lembar->baris as $item) {
            $sheet->setCellValue("A{$baris}", $item['no']);
            $this->teks($sheet, "B{$baris}", $item['nama']);
            $this->teks($sheet, "C{$baris}", $item['kelas']);
            foreach ($item['tes'] as $i => $nilai) {
                if ($nilai !== null) {
                    $sheet->setCellValue([self::KOLOM_TES_AWAL + $i, $baris], $nilai);
                }
            }
            foreach ($item['praktik'] as $i => $nilai) {
                if ($nilai !== null) {
                    $sheet->setCellValue([self::KOLOM_PRAKTIK_AWAL + $i, $baris], $nilai);
                }
            }
            $baris++;
        }

        $terakhir = $lembar->baris === []
            ? self::BARIS_PERTAMA + self::BARIS_TEMPLATE_KOSONG - 1
            : $baris - 1;
        $this->gayaBaris($spreadsheet, $terakhir);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(5, 6);

        $sheet->setSelectedCell('A1');
        $spreadsheet->getProperties()
            ->setCreator('Perangkat Guru SMK Budi Mulia')
            ->setLastModifiedBy('Perangkat Guru SMK Budi Mulia')
            ->setTitle($lembar->judul.' '.$lembar->tahunAjaran);

        return $spreadsheet;
    }

    public function unduh(NilaiLembar $lembar, string $namaBerkas): StreamedResponse
    {
        $spreadsheet = $this->buat($lembar);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $namaBerkas, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Teks selalu disimpan sebagai string, bukan rumus: nama yang diawali "=" tidak boleh dieksekusi. */
    private function teks(Worksheet $sheet, string $sel, string $nilai): void
    {
        $sheet->setCellValueExplicit($sel, $nilai, DataType::TYPE_STRING);
    }

    private function gayaBaris(Spreadsheet $spreadsheet, int $terakhir): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getStyle('A'.self::BARIS_PERTAMA.":X{$terakhir}")->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'size' => 11, 'color' => ['argb' => 'FF000000']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        ]);

        $sheet->getStyle('B'.self::BARIS_PERTAMA.":B{$terakhir}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setIndent(1);
    }
}
