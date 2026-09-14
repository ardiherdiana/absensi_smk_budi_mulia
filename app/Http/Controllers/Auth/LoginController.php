<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

// Mirrors backend/src/modules/auth/auth.service.ts's login() exactly -
// session-based instead of issuing a JWT, since Inertia pages are always
// requested by the same authenticated browser session.
class LoginController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return Inertia::render('login-page');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:1'],
            'password' => ['required', 'string', 'min:1'],
        ]);

        $user = User::where('username', $validated['username'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => 'Username atau password salah',
            ]);
        }

        $user->loadMissing('guru');
        if (in_array($user->role, ['GURU', 'KEPSEK'], true) && $user->guru && ! $user->guru->aktif) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => 'Akun guru ini sudah dinonaktifkan',
            ]);
        }

        // Always "remember" the login (no checkbox for it - this app is
        // meant to be a persistent-login school kiosk/staff tool, not a
        // shared public terminal) so the session survives past
        // SESSION_LIFETIME and even a closed browser via Laravel's
        // long-lived remember cookie, not just the session cookie itself.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Without this, Inertia's own client-side history cache can replay
        // the pre-login page (still showing the login form, or an old
        // account's dashboard) straight from browser memory when the user
        // taps back - no server round-trip happens at all, so this
        // controller's own auth checks never get a chance to run. Purges
        // that cache on the very next Inertia response (the dashboard this
        // redirects to).
        Inertia::clearHistory();

        return redirect('/dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Same reasoning as login: stops back-navigation after logout from
        // replaying a stale authenticated page straight out of Inertia's
        // client-side history cache.
        Inertia::clearHistory();

        return redirect('/login');
    }
}
