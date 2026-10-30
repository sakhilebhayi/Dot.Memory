<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\Memory\DurabilityController;
use App\Http\Controllers\Memory\IndexController;
use App\Http\Controllers\SchedulerProbeController;
use App\Models\DurabilityOutcome;
use App\Models\Index;
use App\Models\RetrievalClass;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

Route::post('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\PreventRequestForgery', 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])
    ->name('ecosystem.auth');

// Content-Security-Policy violation reports. The browser posts these itself, so there is no form, no session and no
// CSRF token: the whole web group (session, cookies, CSRF) is dropped and the route is throttled instead.
Route::post('/csp-report', CspReportController::class)
    ->withoutMiddleware('web')
    ->middleware('throttle:60,1,csp-report')
    ->name('csp.report');

// Links minted before the token moved into a POST body carried it in the URL. They are no longer honoured:
// the visitor is sent to the normal sign-in page and the token in the URL is never read.
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'legacy']);

// Public liveness probe (no auth, no secrets): answers 503 once the cron
// scheduler has not been heard from. Watched from GitHub by
// .github/workflows/scheduler-watchdog.yml.
Route::get('/up/scheduler', SchedulerProbeController::class)->name('up.scheduler');

Route::get('/', fn () => view('welcome'));

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively (registered at /terms-of-service and /privacy-policy, reading resources/markdown/
// terms.md and policy.md). There's no Jetstream equivalent for a Cookie Policy, so this one is
// wired by hand, following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // The landing view is the knowledge platform: what the ecosystem has
    // learned, in human language. The storage-reliability telemetry that
    // used to live here moved to /reliability, unchanged.
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    Route::get('/knowledge', fn () => view('knowledge.index'))->name('knowledge.index');
    Route::get('/knowledge/{incidentUid}', fn (string $incidentUid) => view('knowledge.show', [
        'incidentUid' => $incidentUid,
    ]))->name('knowledge.show');
    Route::get('/timeline', fn () => view('knowledge.timeline'))->name('knowledge.timeline');
    Route::get('/patterns', fn () => view('knowledge.patterns'))->name('knowledge.patterns');

    // Insights was two half-pages: recurrence (now the Patterns ledger) and
    // the gaps beside it. Both live on /patterns, so this only forwards.
    Route::get('/insights', fn () => redirect()->route('knowledge.patterns'))->name('knowledge.insights');

    Route::get('/reliability', function () {
        $classes = RetrievalClass::with(['observations' => fn ($q) => $q->orderByDesc('window_end')->limit(1)])->get();

        return view('reliability.index', [
            'retrievalClassCount' => $classes->count(),
            'classesMeetingSla' => $classes->filter(fn ($c) => $c->observations->first()?->sla_met === true)->count(),
            'activeIndexCount' => Index::where('status', 'active')->count(),
            'recentDurabilityFailures' => DurabilityOutcome::where('result', '!=', DurabilityOutcome::RESULT_PASS)
                ->where('verified_at', '>=', now()->subDays(90))
                ->count(),
        ]);
    })->name('reliability.index');

    Route::get('/indexes', [IndexController::class, 'index'])->name('indexes.index');
    Route::get('/durability', [DurabilityController::class, 'index'])->name('durability.index');
});

// Public sign-up is closed (accounts are created by InfoDot at first sign-in). Keep the named route so any link or
// invitation email that points at it lands on the login page instead of failing.
if (! Features::enabled(Features::registration())) {
    Route::redirect('/register', '/login')->name('register');
}
