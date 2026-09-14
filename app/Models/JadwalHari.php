<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Weekly recurring schedule, one fixed row per day - `hari` follows
// JS/SQL's day-of-week convention (0=Minggu ... 6=Sabtu) so it lines up
// directly with Carbon::dayOfWeek used throughout the app.
#[Fillable(['hari', 'aktif', 'jamMulaiAbsen', 'jamMasuk', 'batasTelat', 'jamPulang', 'jamBriefing', 'jamSelesaiBriefing'])]
class JadwalHari extends Model
{
    protected $table = 'jadwal_hari';

    protected $primaryKey = 'hari';

    public $incrementing = false;

    protected $keyType = 'int';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
