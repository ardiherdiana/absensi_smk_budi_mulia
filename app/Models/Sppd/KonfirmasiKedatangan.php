<?php

namespace App\Models\Sppd;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pengajuan_id', 'dikonfirmasi_oleh', 'pejabat_nama', 'pejabat_jabatan',
    'tiba_tanggal', 'berangkat_tanggal', 'bukti_path',
])]
class KonfirmasiKedatangan extends Model
{
    protected function casts(): array
    {
        return [
            'tiba_tanggal' => 'date',
            'berangkat_tanggal' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PengajuanSppd, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSppd::class, 'pengajuan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengonfirmasi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh');
    }
}
