<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Stops browsers from restoring a stale login/dashboard page straight from
// bfcache (back-forward cache) when the user taps the device's back/forward
// gesture. bfcache restoration is a full page freeze/thaw done entirely by
// the browser - zero JS or PHP runs - so a login page snapshotted *before*
// logging in (or a dashboard snapshotted under a since-switched account)
// can get shown as-is, completely bypassing LoginController@create's
// `if (Auth::check()) redirect('/dashboard')` guard and every other
// server-side auth check this app relies on. `Cache-Control: no-store` is
// the standard, browser-respected signal to opt every response here out of
// both bfcache and normal HTTP caching, forcing back/forward to always hit
// the server fresh.
class PreventBackHistoryCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
