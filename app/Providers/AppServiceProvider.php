<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // /up answered 200 whenever PHP booted, even with the database down. The framework dispatches DiagnosingHealth
        // before it renders the page: a failing query turns the answer into a 500 (the exception text is never shown).
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        // Knowledge-recommendation API: throttled per client address BEFORE the signature check, so an
        // unsigned flood is limited too.
        RateLimiter::for('knowledge', fn (Request $request) => Limit::perMinute(max(1, (int) config('knowledge.rate_limit_per_minute', 120)))->by('knowledge|'.$request->ip()));
    }
}
