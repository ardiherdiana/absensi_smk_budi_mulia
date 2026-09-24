<?php

namespace App\Services\Sppd;

use App\Models\Sppd\Sppd;
use Illuminate\Support\Carbon;

class NomorSuratGenerator
{
    /** @var array<int, string> */
    private const BULAN_ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function sppd(Carbon $tanggal): string
    {
        $urut = Sppd::query()->whereYear('created_at', $tanggal->year)->count() + 1;

        return sprintf('%03d/SMK-BM/%s/%d', $urut, self::BULAN_ROMAWI[$tanggal->month], $tanggal->year);
    }
}
