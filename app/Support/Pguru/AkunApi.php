<?php

namespace App\Support\Pguru;

use App\Models\User;

/** Bentuk akun (`users`) yang dikirim ke aplikasi SIMAK. Tidak pernah memuat kata sandi atau data SPPD. */
class AkunApi
{
    /** @return array{id: string, name: string, username: string, role: string|null} */
    public static function dari(User $user): array
    {
        $user->loadMissing('guru');

        return [
            'id' => $user->id,
            'name' => $user->name ?: ($user->guru?->nama ?: $user->username),
            'username' => $user->username,
            'role' => $user->role,
        ];
    }
}
