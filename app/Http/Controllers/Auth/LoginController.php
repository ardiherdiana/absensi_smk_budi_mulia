<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect('/menu-utama');
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
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah',
            ]);
        }

        $user->loadMissing('guru');
        if (in_array($user->role, ['GURU', 'KEPSEK'], true) && $user->guru && ! $user->guru->aktif) {
            throw ValidationException::withMessages([
                'username' => 'Akun guru ini sudah dinonaktifkan',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        Inertia::clearHistory();

        return redirect('/menu-utama');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::clearHistory();

        return redirect('/login');
    }
}
