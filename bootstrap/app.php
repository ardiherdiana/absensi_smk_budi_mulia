<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventBackHistoryCache;
use App\Http\Middleware\RequireRole;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia renders its own error pages via the shared "errors" prop
        // (see HandleInertiaRequests) instead of Laravel's JSON/HTML error
        // responses - only XHR/JSON callers (the small polling/scan
        // endpoints) get raw JSON.
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

        // Every Service class throws business-rule violations via abort($status,
        // $message) (mirroring the original's HttpError) - Inertia only knows
        // how to surface ValidationException (422 + errors) into a form's
        // `errors`, so on an Inertia request/visit, re-shape any 4xx abort()
        // into that same session-flashed-errors mechanism instead of letting
        // it fall through as Inertia's generic (non-form) error handling.
        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();
            if ($request->header('X-Inertia') && $status >= 400 && $status < 500) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
        });
    })->create();
