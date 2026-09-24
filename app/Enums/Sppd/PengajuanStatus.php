<?php

namespace App\Enums\Sppd;

enum PengajuanStatus: string
{
    case Draft = 'draft';
    case DiajukanKeKepsek = 'diajukan_ke_kepsek';
    case Ditolak = 'ditolak';
    case DisetujuiKepsek = 'disetujui_kepsek';
    case SedangDitugaskan = 'sedang_ditugaskan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::DiajukanKeKepsek => 'Menunggu Persetujuan Kepala Sekolah',
            self::Ditolak => 'Ditolak',
            self::DisetujuiKepsek => 'Disetujui Kepala Sekolah',
            self::SedangDitugaskan => 'Sedang Ditugaskan',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * @return array<int, self>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::Draft => [self::DiajukanKeKepsek],
            self::DiajukanKeKepsek => [self::DisetujuiKepsek, self::Ditolak],
            self::Ditolak => [],
            self::DisetujuiKepsek => [self::SedangDitugaskan],
            self::SedangDitugaskan => [self::Selesai],
            self::Selesai => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextAllowed(), true);
    }
}
