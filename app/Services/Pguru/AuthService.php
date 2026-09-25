<?php

namespace App\Services\Pguru;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login aplikasi SIMAK memakai akun absensi (`users`: username + password), sama dengan login web absensi.
 * Tidak ada pendaftaran: akun dibuat admin di absensi.
 */
class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** Peran `users.role` yang boleh memakai SIMAK. Staf SPPD tanpa peran absensi (role null) tidak termasuk. */
    private const PERAN = ['ADMIN', 'GURU', 'KEPSEK'];

    /**
     * Alasan akun tidak boleh memakai SIMAK, atau null bila boleh. Dipakai saat masuk dan di setiap permintaan
     * (middleware `pguru.aktif`), supaya akun yang dinonaktifkan langsung ditolak walau tokennya belum kedaluwarsa.
     */
    public function alasanDitolak(User $user): ?string
    {
        if (! in_array($user->role, self::PERAN, true)) {
            return 'Akun ini tidak memiliki akses SIMAK. Hubungi admin sekolah.';
        }

        if (! $user->is_active) {
            return 'Akun dinonaktifkan. Hubungi admin sekolah.';
        }

        // Sama dengan login web absensi: guru/kepsek yang profil gurunya dinonaktifkan tidak boleh masuk.
        $user->loadMissing('guru');
        if (in_array($user->role, ['GURU', 'KEPSEK'], true) && $user->guru && ! $user->guru->aktif) {
            return 'Akun guru ini sudah dinonaktifkan. Hubungi admin sekolah.';
        }

        return null;
    }

    public function masuk(string $username, string $password, string $ip): User
    {
        $kunci = 'pguru-login:'.sha1(mb_strtolower($username).'|'.$ip);

        if (RateLimiter::tooManyAttempts($kunci, self::MAX_LOGIN_ATTEMPTS)) {
            abort(429, 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik');
        }

        $user = User::where('username', $username)->first();

        if ($user === null) {
            // Tetap menghitung hash supaya waktu respons tidak membocorkan username mana yang terdaftar.
            Hash::make($password);
            $cocok = false;
        } else {
            $cocok = Hash::check($password, $user->password);
        }

        if (! $cocok) {
            RateLimiter::hit($kunci, 60);
            abort(401, 'Username atau kata sandi salah');
        }

        $alasan = $this->alasanDitolak($user);
        abort_if($alasan !== null, 403, (string) $alasan);

        RateLimiter::clear($kunci);

        return $user;
    }

    public function buatToken(User $user): string
    {
        return $user->createToken('mobile')->plainTextToken;
    }
}
