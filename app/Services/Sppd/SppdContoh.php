<?php

namespace App\Services\Sppd;

use App\Enums\Sppd\RoleName;
use App\Models\Sppd\KonfirmasiKedatangan;
use App\Models\Sppd\PengajuanSppd;
use App\Models\Sppd\Sppd;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Membuat SPPD contoh di memori (tidak disimpan ke database) untuk pratinjau template PDF.
 */
class SppdContoh
{
    public function pengajuan(): PengajuanSppd
    {
        $kepsek = User::role(RoleName::KepalaSekolah->value)->whereNotNull('signature_path')->first();
        $tanggal = now()->addDays(2)->startOfDay();

        $pemohon = (new User)->forceFill(['name' => 'Doni Wijaya, S.H. Gr.', 'jabatan' => 'Guru']);
        $penyetuju = (new User)->forceFill(['name' => $kepsek?->name ?? 'Teti Tresnawati, M.Pd', 'jabatan' => 'Kepala Sekolah']);

        $pengajuan = (new PengajuanSppd)->forceFill([
            'tujuan' => 'Universitas Singaperbangsa Karawang (UNSIKA)',
            'maksud' => 'MGMP Pendidikan Pancasila',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'keterangan' => 'Membawa laptop sekolah',
            'tanggal_berangkat' => $tanggal,
            'jam_berangkat' => '08:00:00',
            'tanggal_kembali' => $tanggal->copy()->addDay(),
            'jam_kembali' => '15:00:00',
            'tte_kode' => 'CONTOHPRATINJAUTEMPLATESPPD00000',
            'tte_signature' => hash('sha256', 'contoh-pratinjau'),
            'tanda_tangan_kepsek_path' => $kepsek && Storage::disk('public')->exists((string) $kepsek->signature_path)
                ? $kepsek->signature_path
                : null,
        ]);

        $pengajuan->setRelation('pemohon', $pemohon);
        $pengajuan->setRelation('penyetuju', $penyetuju);
        $pengajuan->setRelation('sppd', (new Sppd)->forceFill([
            'nomor_sppd' => '001/SMK-BM/'.$this->romawi(now()->month).'/'.now()->year,
            'akun_anggaran' => '5.2.02.01 Belanja Perjalanan Dinas',
            'created_at' => now(),
        ]));
        $pengajuan->setRelation('pengikuts', new Collection([
            (new User)->forceFill(['name' => 'Rio Falentino, S.Pd. Gr.', 'jabatan' => 'Guru']),
            (new User)->forceFill(['name' => 'Marsono, S.Kom., Gr.', 'jabatan' => 'Guru']),
        ]));
        $pengajuan->setRelation('kedatangan', (new KonfirmasiKedatangan)->forceFill([
            'pejabat_nama' => 'Dr. Ahmad Fauzi, M.Pd',
            'pejabat_jabatan' => 'Kepala Bagian Umum',
            'tiba_tanggal' => $tanggal,
            'berangkat_tanggal' => $tanggal->copy()->addDay(),
            'updated_at' => now(),
        ]));

        return $pengajuan;
    }

    private function romawi(int $bulan): string
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$bulan - 1];
    }
}
