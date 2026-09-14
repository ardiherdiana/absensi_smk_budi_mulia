<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Mirrors backend/src/middleware/auth.ts's requireRole() - route model:
// Route::middleware('role:ADMIN,KEPSEK') for a route reachable by either.
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses');
        }

        return $next($request);
    }
}
