<?php

namespace App\Services\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Support\Facades\Storage;

/**
 * Data tampilan SPPD yang dipakai bersama oleh semua template PDF (landscape dan portrait), supaya isi
 * dokumen persis sama dan hanya tata letaknya yang berbeda.
 */
class SppdPdfData
{
    public const SEKOLAH = 'SMK Budi Mulia Karawang';

    public function __construct(private readonly TandaTanganElektronik $tte) {}

    /**
     * @return array<string, mixed>
     */
    public function data(PengajuanSppd $pengajuan): array
    {
        $sppd = $pengajuan->sppd;
        $kedatangan = $pengajuan->kedatangan;

        $tteQr = $pengajuan->tte_kode ? $this->tte->qrDataUri($pengajuan->tte_kode) : null;

        return [
            'sppd' => $sppd,
            'pemohon' => $pengajuan->pemohon,
            'namaPejabat' => $pengajuan->penyetuju?->name ?? '..............................',
            'ttdPath' => $pengajuan->tanda_tangan_kepsek_path
                ? Storage::disk('public')->path($pengajuan->tanda_tangan_kepsek_path)
                : null,

            'tteQr' => $tteQr,
            'tteSidik' => $tteQr ? $this->tte->sidikJari($pengajuan) : null,
            'tteUrl' => $tteQr ? $this->tte->urlVerifikasi($pengajuan->tte_kode) : null,

            'tanggalTerbit' => $sppd->created_at->translatedFormat('j F Y'),
            'tanggalBerangkat' => $pengajuan->tanggal_berangkat->translatedFormat('j F Y'),
            'tanggalKembali' => $pengajuan->tanggal_kembali->translatedFormat('j F Y'),
            'jamBerangkat' => str_replace(':', '.', substr((string) $pengajuan->jam_berangkat, 0, 5)),
            'jamKembali' => str_replace(':', '.', substr((string) $pengajuan->jam_kembali, 0, 5)),
            'lamaHari' => (int) $pengajuan->tanggal_berangkat->copy()->startOfDay()
                ->diffInDays($pengajuan->tanggal_kembali->copy()->startOfDay()) + 1,

            'sekolah' => self::SEKOLAH,
            'tujuan' => $pengajuan->tujuan,

            'kedatangan' => $kedatangan,
            'pengikuts' => $pengajuan->pengikuts,
            'alatAngkutan' => $pengajuan->alat_angkutan,
            'keterangan' => $pengajuan->keterangan,
            'akun' => $sppd->akun_anggaran,
            'tibaTanggal' => ($kedatangan?->tiba_tanggal ?? $pengajuan->tanggal_berangkat)->translatedFormat('j F Y'),
            'pulangTanggal' => ($kedatangan?->berangkat_tanggal ?? $pengajuan->tanggal_kembali)->translatedFormat('j F Y'),
            'catatanKonfirmasi' => $kedatangan
                ? 'Dikonfirmasi elektronik oleh pemohon melalui sistem, '.$kedatangan->updated_at->translatedFormat('j F Y H.i').' WIB.'
                : null,
        ];
    }
}
