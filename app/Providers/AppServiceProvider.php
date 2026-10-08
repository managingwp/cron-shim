<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        RateLimiter::for('ingest', function (Request $request): Limit {
            return Limit::perMinute((int) config('cronshim.ingest.throttle_per_minute'))
                ->by($request->header('X-Shim-Site') ?: (string) $request->ip());
        });

        if (config('cronshim.force_https')) {
            URL::forceScheme('https');
        }
    }
}
