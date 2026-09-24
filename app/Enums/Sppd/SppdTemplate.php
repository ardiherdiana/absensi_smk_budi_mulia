<?php

namespace App\Enums\Sppd;

use App\Models\Sppd\Pengaturan;

enum SppdTemplate: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';
    case LandscapeNew = 'landscape_new';

    public const KUNCI_PENGATURAN = 'sppd_template';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'Landscape (format SPPD aktual)',
            self::Portrait => 'Portrait (lembar rapi)',
            self::LandscapeNew => 'Landscape New (lembar rapi)',
        };
    }

    public function deskripsi(): string
    {
        return match ($this) {
            self::Landscape => 'A4 mendatar, satu lembar depan-belakang berdampingan persis seperti format SPPD Yayasan (SPPD - Format New).',
            self::Portrait => 'A4 tegak, dua halaman: halaman 1 isian SPPD dan tanda tangan, halaman 2 pengesahan perjalanan. Isi sama, tata letak lebih lega.',
            self::LandscapeNew => 'A4 mendatar, satu lembar depan-belakang berdampingan seperti format aktual, tetapi tata letaknya dirapikan: tabel bersih, spasi konsisten, dan blok tanda tangan/QR lebih tertata.',
        };
    }

    public function view(): string
    {
        return match ($this) {
            self::Landscape => 'pdf.sppd',
            self::Portrait => 'pdf.sppd-portrait',
            self::LandscapeNew => 'pdf.sppd-landscape-new',
        };
    }

    public function orientasi(): string
    {
        return match ($this) {
            self::Landscape, self::LandscapeNew => 'landscape',
            self::Portrait => 'portrait',
        };
    }

    /**
     * Template yang sedang dipakai sekolah (default: landscape).
     */
    public static function saatIni(): self
    {
        return self::tryFrom((string) Pengaturan::ambil(self::KUNCI_PENGATURAN)) ?? self::Landscape;
    }
}
