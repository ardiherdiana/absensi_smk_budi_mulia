<?php

namespace App\Http\Controllers\Pguru;

use App\Models\Pguru\Kelas;
use App\Services\Pguru\NilaiXlsxExporter;
use App\Services\Pguru\PdfExporter;
use App\Support\Pguru\NilaiLembar;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ExportNilaiController extends PguruController
{
    public function __construct(private NilaiXlsxExporter $xlsx, private PdfExporter $pdf) {}

    /**
     * Tanpa `kelasId` = semua kelas pada tahun ajaran itu (satu lembar seperti template asli).
     * Tanpa `tahunAjaran` = tahun ajaran terbaru milik akun.
     */
    public function nilai(Request $request): Response
    {
        $data = $this->validasi($request, [
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'tahunAjaran' => ['nullable', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'kelasId' => ['nullable', 'string'],
        ], ['format' => 'Format', 'tahunAjaran' => 'Tahun ajaran', 'kelasId' => 'Kelas']);

        $akunId = $this->akun($request)->id;

        if (! empty($data['kelasId'])) {
            $kelas = new EloquentCollection([$this->kelasMilik($request, $data['kelasId'])]);
            $tahunAjaran = $kelas[0]->tahunAjaran;
        } else {
            $tahunAjaran = $data['tahunAjaran'] ?? Kelas::where('akunId', $akunId)->max('tahunAjaran');
            abort_if($tahunAjaran === null, 404, 'Belum ada kelas untuk diekspor');
            $kelas = Kelas::where('akunId', $akunId)->where('tahunAjaran', $tahunAjaran)->get();
            abort_if($kelas->isEmpty(), 404, 'Tidak ada kelas pada tahun ajaran ini');
        }

        $kelas = $kelas->load('siswa.nilai')->sort(Kelas::urutAlami(...))->values();
        $lembar = NilaiLembar::dari($kelas, $tahunAjaran);
        $nama = $this->namaBerkas($kelas, $tahunAjaran, $data['format']);

        return $data['format'] === 'xlsx'
            ? $this->xlsx->unduh($lembar, $nama)
            : $this->pdf->nilai($lembar, $nama);
    }

    /** Format kosong untuk diisi lalu diimpor kembali. */
    public function templateKosong(Request $request): Response
    {
        return $this->xlsx->unduh(NilaiLembar::kosong(now()->format('Y').'/'.(now()->format('Y') + 1)), 'Format-Nilai-Siswa.xlsx');
    }

    /** @param  Collection<int, Kelas>  $kelas */
    private function namaBerkas($kelas, string $tahunAjaran, string $format): string
    {
        $bagian = ['Nilai-Siswa'];
        if ($kelas->count() === 1) {
            $bagian[] = Str::slug($kelas[0]->nama);
        } elseif (($jurusan = $kelas->map->jurusan()->unique()->values())->count() === 1 && $jurusan[0] !== null) {
            $bagian[] = $jurusan[0];
        }
        $bagian[] = str_replace('/', '-', $tahunAjaran);

        return implode('-', $bagian).'.'.$format;
    }
}
