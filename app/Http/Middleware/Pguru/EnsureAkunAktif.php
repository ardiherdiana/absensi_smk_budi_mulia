<?php

namespace App\Http\Middleware\Pguru;

use App\Services\Pguru\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan di absensi (users.is_active, atau profil guru tidak aktif) atau yang perannya dicabut
 * langsung ditolak walau token-nya belum kedaluwarsa.
 */
class EnsureAkunAktif
{
    public function __construct(private AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $alasan = $user ? $this->auth->alasanDitolak($user) : 'Anda tidak memiliki akses';
        abort_if($alasan !== null, 403, (string) $alasan);

        return $next($request);
    }
}
