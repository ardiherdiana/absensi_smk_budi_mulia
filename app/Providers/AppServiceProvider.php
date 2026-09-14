<?php

namespace App\Providers;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Match the ported Prisma schema, which used VARCHAR(191) (Prisma's
        // own default) for every plain string column unless explicitly
        // overridden (endpoint/p256dh/auth on push_subscriptions).
        Builder::defaultStringLength(191);

        // Laravel doesn't sync Carbon's own locale with config('app.locale')
        // automatically - needed for translatedFormat() (e.g. "September
        // 2026") to actually render in Indonesian instead of falling back to
        // English.
        Carbon::setLocale(config('app.locale'));
    }
}
