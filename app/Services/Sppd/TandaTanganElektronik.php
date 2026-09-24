<?php

namespace App\Services\Sppd;

use App\Models\Sppd\PengajuanSppd;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Tanda Tangan Elektronik (TTE) untuk SPPD yang disetujui Kepala Sekolah.
 *
 * Saat disetujui, data inti pengajuan ditandatangani dengan HMAC-SHA256 memakai kunci rahasia server, lalu
 * disimpan bersama kode verifikasi acak (128+ bit) yang dimuat dalam QR. Halaman verifikasi publik
 * menghitung ulang tanda tangan dari data saat ini: jika data diubah setelah disetujui, tanda tangan
 * tidak cocok dan dokumen dinyatakan tidak valid.
 */
class TandaTanganElektronik
{
    /** Versi format data yang ditandatangani untuk persetujuan baru. */
    public const VERSI = 2;

    public function buatKode(): string
    {
        return Str::random(32);
    }

    /**
     * Hitung tanda tangan dari data pengajuan yang sudah tersimpan (harus sudah memiliki kode TTE).
     */
    public function tandatangani(PengajuanSppd $pengajuan): string
    {
        return hash_hmac('sha256', $this->payload($pengajuan), $this->kunci());
    }

    public function valid(PengajuanSppd $pengajuan): bool
    {
        if (! $pengajuan->tte_kode || ! $pengajuan->tte_signature) {
            return false;
        }

        $pengajuan->loadMissing(['pemohon', 'penyetuju']);

        if (! $pengajuan->pemohon || ! $pengajuan->penyetuju || ! $pengajuan->disetujui_at) {
            return false;
        }

        return hash_equals($pengajuan->tte_signature, $this->tandatangani($pengajuan));
    }

    /**
     * Sidik jari pendek yang dicetak di dokumen, mis. `1A2B-3C4D-5E6F-7A8B`.
     */
    public function sidikJari(PengajuanSppd $pengajuan): ?string
    {
        if (! $pengajuan->tte_signature) {
            return null;
        }

        return strtoupper(implode('-', str_split(substr($pengajuan->tte_signature, 0, 16), 4)));
    }

    public function urlVerifikasi(string $kode): string
    {
        return rtrim((string) config('app.url'), '/').route('sppd.tte.verifikasi', ['kode' => $kode], absolute: false);
    }

    /**
     * QR berisi URL verifikasi, sebagai data URI SVG siap dipakai pada `<img>`.
     */
    public function qrDataUri(string $kode): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 2), new SvgImageBackEnd));
        $svg = $writer->writeString($this->urlVerifikasi($kode), 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Data yang ditandatangani, sesuai versi yang tercatat pada pengajuan (`tte_versi`). Isi dan urutan kolom
     * tiap versi harus tetap stabil: mengubahnya membuat TTE lama tidak valid, jadi perubahan wajib membuat versi baru.
     */
    private function payload(PengajuanSppd $pengajuan): string
    {
        $pengajuan->loadMissing(['pemohon', 'penyetuju']);

        $waktuSetuju = $pengajuan->disetujui_at->copy()->utc()->format('Y-m-d\TH:i:s\Z');
        $berangkat = $pengajuan->tanggal_berangkat->toDateString().' '.substr((string) $pengajuan->jam_berangkat, 0, 5);
        $kembali = $pengajuan->tanggal_kembali->toDateString().' '.substr((string) $pengajuan->jam_kembali, 0, 5);

        if ((int) $pengajuan->tte_versi === 1) {
            $data = [
                'v' => 1,
                'kode' => $pengajuan->tte_kode,
                'pengajuan_id' => $pengajuan->id,
                'pemohon' => [$pengajuan->pemohon->id, $pengajuan->pemohon->name],
                'tujuan' => $pengajuan->tujuan,
                'maksud' => $pengajuan->maksud,
                'berangkat' => $berangkat,
                'kembali' => $kembali,
                'penyetuju' => [$pengajuan->penyetuju->id, $pengajuan->penyetuju->name],
                'disetujui_at' => $waktuSetuju,
            ];
        } else {
            $pengajuan->loadMissing('pengikuts');

            $data = [
                'v' => 2,
                'kode' => $pengajuan->tte_kode,
                'pengajuan_id' => $pengajuan->id,
                'pemohon_id' => $pengajuan->pemohon_id,
                'tujuan' => $pengajuan->tujuan,
                'maksud' => $pengajuan->maksud,
                'alat_angkutan' => $pengajuan->alat_angkutan,
                'keterangan' => $pengajuan->keterangan,
                'pengikut_ids' => $pengajuan->pengikuts->pluck('id')->sort()->values()->all(),
                'berangkat' => $berangkat,
                'kembali' => $kembali,
                'penyetuju_id' => $pengajuan->disetujui_oleh,
                'disetujui_at' => $waktuSetuju,
            ];
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function kunci(): string
    {
        return hash_hmac('sha256', 'tte-sppd-v1', (string) (config('tte.secret') ?: config('app.key')), true);
    }
}
