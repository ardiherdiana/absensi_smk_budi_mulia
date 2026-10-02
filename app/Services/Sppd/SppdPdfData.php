<?php

namespace App\Services\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Support\Facades\Storage;

/**
 * Data tampilan SPPD untuk template PDF (resources/views/pdf/sppd.blade.php).
 */
class SppdPdfData
{
    public const SEKOLAH = 'SMK Budi Mulia Karawang';

    /**
     * @return array<string, mixed>
     */
    public function data(PengajuanSppd $pengajuan): array
    {
        $sppd = $pengajuan->sppd;
        return [
            'sppd' => $sppd,
            'pemohon' => $pengajuan->pemohon,
            'namaPejabat' => $pengajuan->penyetuju?->name ?? '..............................',
            'ttdPath' => $pengajuan->tanda_tangan_kepsek_path
                ? Storage::disk('public')->path($pengajuan->tanda_tangan_kepsek_path)
                : null,

            'tanggalTerbit' => $sppd->created_at->translatedFormat('j F Y'),
            'tanggalBerangkat' => $pengajuan->tanggal_berangkat->translatedFormat('j F Y'),
            'tanggalKembali' => $pengajuan->tanggal_kembali->translatedFormat('j F Y'),
            'jamBerangkat' => str_replace(':', '.', substr((string) $pengajuan->jam_berangkat, 0, 5)),
            'jamKembali' => str_replace(':', '.', substr((string) $pengajuan->jam_kembali, 0, 5)),
            'lamaHari' => (int) $pengajuan->tanggal_berangkat->copy()->startOfDay()
                ->diffInDays($pengajuan->tanggal_kembali->copy()->startOfDay()) + 1,

            'sekolah' => self::SEKOLAH,
            'tujuan' => $pengajuan->tujuan,

            'pengikuts' => $pengajuan->pengikuts,
            'alatAngkutan' => $pengajuan->alat_angkutan,
            'keterangan' => $pengajuan->keterangan,
            'akun' => $sppd->akun_anggaran,
        ];
    }
}
