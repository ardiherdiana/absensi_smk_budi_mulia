<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Models\Sppd\PengajuanSppd;
use App\Services\Sppd\TandaTanganElektronik;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class VerifikasiTteController extends Controller
{
    public function __construct(private readonly TandaTanganElektronik $tte) {}

    /**
     * Halaman publik hasil pemindaian QR TTE. Data dokumen hanya ditampilkan bila tanda tangan cocok
     * dengan data yang tersimpan saat ini.
     */
    public function show(Request $request, string $kode): Response
    {
        $pengajuan = PengajuanSppd::query()
            ->with(['pemohon', 'penyetuju', 'sppd', 'pengikuts'])
            ->where('tte_kode', $kode)
            ->first();

        $hasil = match (true) {
            $pengajuan === null => 'tidak_ditemukan',
            $this->tte->valid($pengajuan) => 'valid',
            default => 'tidak_valid',
        };

        $response = Inertia::render('sppd/verifikasi/show', [
            'hasil' => $hasil,
            'dokumen' => $hasil === 'valid' ? [
                'nomor_sppd' => $pengajuan->sppd?->nomor_sppd,
                'pemohon' => $pengajuan->pemohon->name,
                'jabatan_pemohon' => $pengajuan->pemohon->jabatan,
                'tujuan' => $pengajuan->tujuan,
                'maksud' => $pengajuan->maksud,
                'alat_angkutan' => $pengajuan->alat_angkutan,
                'pengikut' => $pengajuan->pengikuts->pluck('name')->all(),
                'tanggal_berangkat' => $pengajuan->tanggal_berangkat->toDateString(),
                'jam_berangkat' => substr((string) $pengajuan->jam_berangkat, 0, 5),
                'tanggal_kembali' => $pengajuan->tanggal_kembali->toDateString(),
                'jam_kembali' => substr((string) $pengajuan->jam_kembali, 0, 5),
                'penyetuju' => $pengajuan->penyetuju->name,
                'jabatan_penyetuju' => $pengajuan->penyetuju->jabatan,
                'disetujui_at' => $pengajuan->disetujui_at->toIso8601String(),
                'status' => $pengajuan->status->label(),
                'sidik_jari' => $this->tte->sidikJari($pengajuan),
            ] : null,
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response->setStatusCode($hasil === 'tidak_ditemukan' ? 404 : 200);
    }
}
