<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\Memory\DurabilityController;
use App\Http\Controllers\Memory\IndexController;
use App\Models\DurabilityOutcome;
use App\Models\Index;
use App\Models\RetrievalClass;
use Illuminate\Support\Facades\Route;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $classes = RetrievalClass::with(['observations' => fn ($q) => $q->orderByDesc('window_end')->limit(1)])->get();

        return view('dashboard', [
            'retrievalClassCount' => $classes->count(),
            'classesMeetingSla' => $classes->filter(fn ($c) => $c->observations->first()?->sla_met === true)->count(),
            'activeIndexCount' => Index::where('status', 'active')->count(),
            'recentDurabilityFailures' => DurabilityOutcome::where('result', '!=', DurabilityOutcome::RESULT_PASS)
                ->where('verified_at', '>=', now()->subDays(90))
                ->count(),
        ]);
    })->name('dashboard');

    Route::get('/indexes', [IndexController::class, 'index'])->name('indexes.index');
    Route::get('/durability', [DurabilityController::class, 'index'])->name('durability.index');
});
