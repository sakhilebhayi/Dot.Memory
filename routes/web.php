<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\Memory\DurabilityController;
use App\Http\Controllers\Memory\IndexController;
use App\Models\DurabilityOutcome;
use App\Models\Index;
use App\Models\RetrievalClass;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

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
