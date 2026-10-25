<?php

namespace Tests\Feature\Intelligence;

use App\Http\Middleware\VerifyKnowledgeSignature;
use App\Http\Middleware\VerifyParticipationSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guard: nothing may return loop_records data without being either consent-gated or incapable of
 * touching a person subject. It fails when a route or a class is added that has not been classified,
 * so a new read path cannot quietly reopen the person-data gap.
 */
class LoopRecordAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every API route, classified. 'gated' routes go through LoopRecordAccess and are exercised below;
     * 'signed' routes are the participation API (HMAC, consent checked inside); 'none' routes never
     * return loop_records rows. 'knowledge' routes are the signed knowledge-recommendations API: it reads decision
     * rows only through KnowledgeRecommendations (LoopRecordAccess::readable(), one subject type) and never
     * returns a detail blob.
     */
    private const ROUTES = [
        'GET api/user' => 'none',
        'POST api/intelligence/events' => 'gated',
        'POST api/intelligence/decisions' => 'gated',
        'POST api/intelligence/actions' => 'gated',
        'POST api/intelligence/outcomes' => 'gated',
        'GET api/intelligence/context' => 'gated',
        'POST api/ops/incidents' => 'none',
        'PATCH api/ops/incidents/{incidentUid}' => 'none',
        'GET api/ops/incidents' => 'none',
        'GET api/ops/recall' => 'none',
        'GET api/guardian/health' => 'none',
        'POST api/participation/events' => 'signed',
        'PUT api/participation/{userUuid}/consent' => 'signed',
        'GET api/participation/{userUuid}/context' => 'signed',
        'DELETE api/participation/{userUuid}' => 'signed',
        'GET api/knowledge-recommendations' => 'knowledge',
        'POST api/knowledge-recommendations/{decisionEventId}/outcome' => 'knowledge',
        'GET api/knowledge-recommendations/outcomes' => 'knowledge',
    ];

    /**
     * Every class under app/ that mentions loop_records or the LoopRecord model, and why it is safe.
     */
    private const TOUCHES_LOOP_RECORDS = [
        'Models/LoopRecord.php' => 'the model',
        'Http/Controllers/Intelligence/LoopController.php' => 'generic API: must use LoopRecordAccess',
        'Services/Memory/LoopContextBuilder.php' => 'generic reads: must use LoopRecordAccess',
        'Services/Memory/LoopRecordAccess.php' => 'the gate',
        'Http/Controllers/Participation/ParticipationController.php' => 'signed participation API',
        'Services/Participation/ParticipationStore.php' => 'signed participation API',
        'Services/Guardian/Checks/IntelligenceIngestCheck.php' => 'aggregate age and count only, no rows',
        'Services/Knowledge/KnowledgeRecommendations.php' => 'signed knowledge API: must use LoopRecordAccess::readable()',
    ];

    public function test_every_api_route_is_classified(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $found[] = $method.' '.$route->uri();
            }
        }

        $this->assertEqualsCanonicalizing(
            array_keys(self::ROUTES),
            $found,
            'An /api route was added or removed. Classify it in LoopRecordAccessGuardTest::ROUTES: does it return '
            .'loop_records data, and if so does it go through LoopRecordAccess (gated) or the signed participation API?',
        );
    }

    public function test_signed_routes_use_the_signature_middleware_and_gated_routes_use_sanctum(): void
    {
        foreach (Route::getRoutes() as $route) {
            $key = collect($route->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))->first().' '.$route->uri();
            $class = self::ROUTES[$key] ?? null;

            if ($class === 'signed') {
                $this->assertContains(VerifyParticipationSignature::class, $route->gatherMiddleware(), $key);
            }

            if ($class === 'knowledge') {
                $middleware = $route->gatherMiddleware();
                $this->assertContains('throttle:knowledge', $middleware, $key);
                $this->assertNotEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, VerifyKnowledgeSignature::class.':')), $key);
                $this->assertNotContains('auth:sanctum', $middleware, $key);
            }

            if ($class === 'gated') {
                $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $key);
            }
        }
    }

    public function test_only_known_classes_touch_loop_records(): void
    {
        $found = [];
        $root = app_path();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (preg_match('/\bLoopRecord\b|loop_records/', $source) === 1) {
                $found[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
            }
        }

        $this->assertEqualsCanonicalizing(
            array_keys(self::TOUCHES_LOOP_RECORDS),
            $found,
            'A class started (or stopped) reading loop_records. If it returns rows to a caller it must go through '
            .'LoopRecordAccess::readable(); then list it in LoopRecordAccessGuardTest::TOUCHES_LOOP_RECORDS.',
        );
    }

    public function test_the_generic_read_and_write_paths_use_the_gate(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/Intelligence/LoopController.php'));
        $builder = (string) file_get_contents(app_path('Services/Memory/LoopContextBuilder.php'));

        $this->assertStringContainsString('assertReadable(', $controller);
        $this->assertStringContainsString('assertWritable(', $controller);
        $this->assertStringContainsString('->readable(', $builder);
        // The query that feeds the evidence pack is the only LoopRecord read in the builder, and it is filtered.
        $this->assertSame(1, substr_count($builder, 'LoopRecord::query()'));
        $this->assertMatchesRegularExpression('/readable\(LoopRecord::query\(\)\)/', $builder);
    }

    public function test_the_knowledge_service_reads_decisions_only_through_the_gate(): void
    {
        $service = (string) file_get_contents(app_path('Services/Knowledge/KnowledgeRecommendations.php'));

        // The only LoopRecord query is the one wrapped by the gate, and it is narrowed to decisions of one subject type.
        $this->assertSame(1, substr_count($service, 'LoopRecord::query()'));
        $this->assertMatchesRegularExpression('/readable\(LoopRecord::query\(\)\)/', $service);
        $this->assertStringContainsString("->where('stage', 'decision')", $service);
        $this->assertStringContainsString("config('knowledge.subject_type')", $service);

        // The controller and middleware never touch loop_records themselves.
        foreach (['Http/Controllers/Knowledge/KnowledgeRecommendationController.php', 'Http/Middleware/VerifyKnowledgeSignature.php'] as $file) {
            $this->assertDoesNotMatchRegularExpression('/\bLoopRecord\b|loop_records/', (string) file_get_contents(app_path($file)), $file);
        }
    }

    public function test_every_gated_route_refuses_a_person_subject_without_consent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $uuid = (string) Str::uuid();
        $envelope = [
            'loop_id' => 'l', 'event_id' => 'e', 'platform' => 'dot-mines', 'subject_type' => 'person', 'subject_id' => $uuid,
            'occurred_at' => now()->toISOString(), 'confidence' => 0.5, 'risk' => 0.5, 'autonomy_level' => 'observe',
            'action_kind' => 'k', 'executor_platform' => 'p', 'execution_status' => 'pending', 'verdict' => 'improved',
        ];

        $gated = array_keys(array_filter(self::ROUTES, fn ($class) => $class === 'gated'));
        $this->assertCount(5, $gated);

        foreach ($gated as $route) {
            [$method, $uri] = explode(' ', $route);
            $response = $method === 'GET'
                ? $this->getJson('/'.$uri.'?subject_type=person&subject_id='.$uuid)
                : $this->postJson('/'.$uri, $envelope);

            $response->assertForbidden();
            $this->assertContains($response->json('code'), ['consent_required', 'person_subject_reserved'], $route);
        }

        $this->assertDatabaseCount('loop_records', 0);
    }
}
