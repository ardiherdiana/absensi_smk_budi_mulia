<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventBackHistoryCache;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\Sppd\RoleMiddleware as SppdRoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() && ! $request->header('X-Inertia'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Data tidak valid',
                    'errors' => $e->errors(),
                ], 400);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();
            if ($request->header('X-Inertia') && $status >= 400 && $status < 500) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
        });
    })->create();
