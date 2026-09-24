<?php

namespace App\Policies\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;

class PengajuanSppdPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        if ($user->hasAnyRole([RoleName::KepalaSekolah->value, RoleName::Tu->value, RoleName::Bendahara->value])) {
            return true;
        }

        return $user->id === $pengajuanSppd->pemohon_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Pemohon->value);
    }

    public function approve(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::KepalaSekolah->value) && $pengajuanSppd->status === PengajuanStatus::DiajukanKeKepsek;
    }

    public function terbitkanSppd(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Tu->value) && $pengajuanSppd->status === PengajuanStatus::DisetujuiKepsek;
    }

    public function konfirmasiKedatangan(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->id === $pengajuanSppd->pemohon_id && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }

    public function cairkanUangMuka(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Bendahara->value) && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }

    public function selesaikan(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Tu->value) && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }
}
