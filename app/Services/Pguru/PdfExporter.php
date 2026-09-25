<?php

namespace App\Services\Pguru;

use App\Models\Pguru\Supervisi;
use App\Support\Pguru\NilaiLembar;
use App\Support\Pguru\SupervisiInstrumen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * PDF dibuat dengan dompdf (sudah dipakai modul SPPD), bukan dikonversi dari docx/xlsx lewat
 * LibreOffice: hosting produksi (shared hosting) tidak menyediakan LibreOffice.
 */
class PdfExporter
{
    public function __construct(private NilaiPdfRenderer $lembarNilai) {}

    public function nilai(NilaiLembar $lembar, string $namaBerkas): Response
    {
        return new Response($this->lembarNilai->render($lembar), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $namaBerkas),
        ]);
    }

    public function supervisi(Supervisi $supervisi, string $namaBerkas): Response
    {
        return Pdf::loadView('pdf.pguru.supervisi', [
            'supervisi' => array_merge($supervisi->toApi(), ['tanggal' => $supervisi->tanggal]),
            'bagian' => SupervisiInstrumen::bagian(),
            'refleksi' => SupervisiInstrumen::refleksi(),
        ])->setPaper('a4', 'portrait')->download($namaBerkas);
    }
}
