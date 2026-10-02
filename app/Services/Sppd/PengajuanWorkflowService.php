<?php

namespace App\Services\Sppd;

use App\Enums\Sppd\JenisPencairan;
use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\LaporanPerjalanan;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\Pencairan;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Notifications\Sppd\PengajuanStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PengajuanWorkflowService
{
    public function __construct(private readonly NomorSuratGenerator $nomorSuratGenerator) {}

    public function ajukan(PengajuanSppd $pengajuan, User $pemohon): PengajuanSppd
    {
        $this->transition($pengajuan, PengajuanStatus::DiajukanKeKepsek, $pemohon, 'Pengajuan SPPD diajukan ke Kepala Sekolah.');

        $this->notifyRole(RoleName::KepalaSekolah, $pengajuan, 'Pengajuan SPPD baru menunggu persetujuan Anda.');

        return $pengajuan->fresh();
    }

    public function setujui(PengajuanSppd $pengajuan, User $kepsek, ?string $catatan): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::DisetujuiKepsek);

        $tandaTanganPath = $this->arsipkanTandaTangan($pengajuan, $kepsek);

        DB::transaction(function () use ($pengajuan, $kepsek, $catatan, $tandaTanganPath): void {
            $pengajuan->update([
                'disetujui_oleh' => $kepsek->id,
                'disetujui_at' => now(),
                'tanda_tangan_kepsek_path' => $tandaTanganPath,
                'catatan_kepsek' => $catatan,
            ]);

            $this->transition($pengajuan, PengajuanStatus::DisetujuiKepsek, $kepsek, 'Pengajuan disetujui dan ditandatangani oleh Kepala Sekolah.');
        });

        $this->notifyRole(RoleName::Tu, $pengajuan, 'Pengajuan disetujui, silakan terbitkan SPPD.');
        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Pengajuan SPPD Anda disetujui Kepala Sekolah.');

        return $pengajuan->fresh();
    }

    public function tolak(PengajuanSppd $pengajuan, User $kepsek, string $catatan): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::Ditolak);

        $pengajuan->update([
            'catatan_kepsek' => $catatan,
            'ditolak_at' => now(),
        ]);

        $this->transition($pengajuan, PengajuanStatus::Ditolak, $kepsek, "Pengajuan ditolak: {$catatan}");

        $this->notifyUser($pengajuan->pemohon, $pengajuan, "Pengajuan SPPD Anda ditolak: {$catatan}");

        return $pengajuan->fresh();
    }

    public function terbitkanSppd(PengajuanSppd $pengajuan, User $tu, ?string $akunAnggaran = null): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::SedangDitugaskan);

        DB::transaction(function () use ($pengajuan, $tu, $akunAnggaran): void {
            $pengajuan->sppd()->create([
                'nomor_sppd' => $this->nomorSuratGenerator->sppd(now()),
                'akun_anggaran' => $akunAnggaran,
                'diterbitkan_oleh' => $tu->id,
            ]);

            $this->transition($pengajuan, PengajuanStatus::SedangDitugaskan, $tu, 'SPPD diterbitkan oleh TU dan siap digunakan.');
        });

        $this->notifyRole(RoleName::Bendahara, $pengajuan, 'SPPD siap dicairkan uang mukanya (jika ada).');
        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'SPPD Anda sudah terbit dan siap diunduh.');

        return $pengajuan->fresh();
    }

    /**
     * Pemohon mengisi atau memperbarui laporan perjalanan: rangkuman hasil dinas dan foto dokumentasi.
     * Foto lama yang ada di $hapusFotoIds dibuang (berkasnya ikut dihapus); $fotoBaru adalah daftar [path, nama asli]
     * berkas yang sudah tersimpan di disk.
     *
     * @param  array<int, int|string>  $hapusFotoIds
     * @param  array<int, array{path: string, nama_asli: ?string}>  $fotoBaru
     */
    public function simpanLaporan(PengajuanSppd $pengajuan, User $pemohon, string $ringkasan, array $hapusFotoIds, array $fotoBaru): LaporanPerjalanan
    {
        $baru = ! $pengajuan->laporan()->exists();

        $laporan = DB::transaction(function () use ($pengajuan, $pemohon, $ringkasan, $hapusFotoIds, $fotoBaru): LaporanPerjalanan {
            $laporan = $pengajuan->laporan()->updateOrCreate([], [
                'ditulis_oleh' => $pemohon->id,
                'ringkasan' => $ringkasan,
            ]);

            $dibuang = $laporan->fotos()->whereIn('id', $hapusFotoIds)->get();
            $dibuang->each->delete();
            Storage::disk('public')->delete($dibuang->pluck('path')->all());

            foreach ($fotoBaru as $foto) {
                $laporan->fotos()->create($foto);
            }

            return $laporan;
        });

        $this->log($pengajuan, $pemohon, ($baru ? 'Laporan perjalanan diisi' : 'Laporan perjalanan diperbarui').' oleh pemohon ('.$laporan->fotos()->count().' foto dokumentasi).');

        if ($baru) {
            $this->notifyRole(RoleName::Tu, $pengajuan, 'Pemohon telah mengisi laporan perjalanan dinas.');
        }

        return $laporan;
    }

    public function cairkanUangMuka(PengajuanSppd $pengajuan, User $bendahara, float $jumlah, ?string $keterangan): Pencairan
    {
        $pencairan = $pengajuan->pencairans()->create([
            'jenis' => JenisPencairan::UangMuka,
            'jumlah' => $jumlah,
            'tanggal' => now()->toDateString(),
            'dicairkan_oleh' => $bendahara->id,
            'keterangan' => $keterangan,
        ]);

        $this->log($pengajuan, $bendahara, 'Pencairan '.JenisPencairan::UangMuka->label().' sebesar Rp'.number_format($jumlah, 0, ',', '.'));

        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Uang muka perjalanan dinas Anda telah dicairkan.');

        return $pencairan;
    }

    public function selesaikan(PengajuanSppd $pengajuan, User $tu): PengajuanSppd
    {
        $this->transition($pengajuan, PengajuanStatus::Selesai, $tu, 'Perjalanan dinas selesai & diarsipkan oleh TU.');

        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Perjalanan dinas Anda selesai & telah diarsipkan.');

        return $pengajuan->fresh();
    }

    /**
     * Salin tanda tangan Kepala Sekolah saat ini sebagai arsip pengajuan, supaya SPPD yang sudah disetujui
     * tidak berubah ketika tanda tangan di profil diganti kemudian.
     *
     * @throws RuntimeException
     */
    private function arsipkanTandaTangan(PengajuanSppd $pengajuan, User $kepsek): string
    {
        $disk = Storage::disk('public');

        if (! $kepsek->signature_path || ! $disk->exists($kepsek->signature_path)) {
            throw new RuntimeException('Simpan tanda tangan digital Anda terlebih dahulu (menu Tanda Tangan Saya) sebelum menyetujui pengajuan.');
        }

        $path = 'signatures/approved/'.$pengajuan->id.'-'.Str::random(10).'.png';
        $disk->copy($kepsek->signature_path, $path);

        return $path;
    }

    private function guardTransition(PengajuanSppd $pengajuan, PengajuanStatus $target): void
    {
        if (! $pengajuan->status->canTransitionTo($target)) {
            throw new RuntimeException("Status tidak dapat berpindah dari {$pengajuan->status->label()} ke {$target->label()}.");
        }
    }

    private function transition(PengajuanSppd $pengajuan, PengajuanStatus $target, User $actor, string $keterangan): void
    {
        $this->guardTransition($pengajuan, $target);

        $pengajuan->update(['status' => $target]);

        $this->log($pengajuan, $actor, $keterangan);
    }

    private function log(PengajuanSppd $pengajuan, User $actor, string $keterangan): void
    {
        LogAudit::create([
            'entitas_terkait' => PengajuanSppd::class,
            'entitas_id' => $pengajuan->id,
            'user_id' => $actor->id,
            'aksi' => $keterangan,
            'keterangan' => $keterangan,
        ]);
    }

    private function notifyRole(RoleName $role, PengajuanSppd $pengajuan, string $pesan): void
    {
        User::role($role->value)->get()->each(
            fn (User $user) => $user->notify(new PengajuanStatusChanged($pengajuan, $pesan))
        );
    }

    private function notifyUser(User $user, PengajuanSppd $pengajuan, string $pesan): void
    {
        $user->notify(new PengajuanStatusChanged($pengajuan, $pesan));
    }
}
