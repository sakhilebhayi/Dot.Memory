<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Knowledge-recommendation API: throttled per client address BEFORE the signature check, so an
        // unsigned flood is limited too.
        RateLimiter::for('knowledge', fn (Request $request) => Limit::perMinute(max(1, (int) config('knowledge.rate_limit_per_minute', 120)))->by('knowledge|'.$request->ip()));
    }
}
