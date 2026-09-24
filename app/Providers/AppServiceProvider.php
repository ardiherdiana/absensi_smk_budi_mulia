<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::defaultStringLength(191);

        Carbon::setLocale(config('app.locale'));

        // Modul SPPD: akun absensi ADMIN lolos semua pengecekan Policy tanpa perlu role SPPD.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
    }
}
