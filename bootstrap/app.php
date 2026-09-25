<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Pguru\EnsureAkunAktif as PguruEnsureAkunAktif;
use App\Http\Middleware\PreventBackHistoryCache;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\Sppd\RoleMiddleware as SppdRoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            PreventBackHistoryCache::class,
        ]);

        $middleware->alias([
            'role' => RequireRole::class,
            'sppd.role' => SppdRoleMiddleware::class,
            'pguru.aktif' => PguruEnsureAkunAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API mobile (/api/*) selalu dijawab JSON, walau klien lupa mengirim header Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->expectsJson() && ! $request->header('X-Inertia')),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return response()->json([
                    'message' => 'Data tidak valid',
                    'errors' => $e->errors(),
                ], 400);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();
            if ($request->header('X-Inertia') && $status >= 400 && $status < 500) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }

            // Hanya pesan yang kita tulis sendiri yang dikembalikan; jejak tumpukan tidak pernah ikut,
            // walau APP_DEBUG menyala di server.
            if ($request->is('api/*')) {
                $pesan = match (true) {
                    $status === 429 && str_starts_with($e->getMessage(), 'Too Many') => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.',
                    $e->getMessage() !== '' => $e->getMessage(),
                    $status === 404 => 'Data tidak ditemukan',
                    default => 'Terjadi kesalahan',
                };

                return response()->json(['message' => $pesan], $status, $e->getHeaders());
            }
        });
    })->create();
