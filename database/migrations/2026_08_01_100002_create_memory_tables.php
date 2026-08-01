<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dot.Memory domain schema — storage/retrieval telemetry only.
 *
 * Trust boundary ("store without reading", wiki.md §2): none of these
 * tables may ever gain a column capable of holding arbitrary tenant
 * content (free-text bodies, blobs, serialized payloads, query text,
 * result sets). Every column here is a number, timestamp, enum/string
 * label, or foreign key. This file is the schema-level enforcement of
 * that principle — reviewers should reject any future migration that
 * adds a content-shaped column to these tables.
 *
 * Tenancy: these tables are ecosystem/infrastructure-level, not
 * per-team. Tenant key = owning platform (wiki.md §7) — the platform
 * that published the stored content, not a Dot.Memory team. Jetstream
 * Teams here only gate human access to this monitoring dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // hot | warm | cold
            $table->string('name');
            $table->unsignedInteger('latency_target_ms')->nullable(); // null = async/no fixed target (e.g. cold's "5 min async")
            $table->string('backing'); // "Graph store + cache projections", "Warm store", "Immutable cold archive"
            $table->timestamps();
        });

        Schema::create('indexes', function (Blueprint $table) {
            $table->id();
            $table->string('index_key'); // logical index id, e.g. "graph-provenance"
            $table->unsignedInteger('version')->default(1);
            $table->string('type'); // graph | vector | audit-log
            $table->foreignId('storage_tier_id')->constrained('storage_tiers')->cascadeOnDelete();
            $table->string('status')->default('active'); // active | rebuilding | deprecated | retired
            $table->unsignedBigInteger('entry_count')->default(0);
            $table->timestamp('last_rebuilt_at')->nullable();
            $table->timestamps();

            $table->unique(['index_key', 'version']);
        });

        Schema::create('retrieval_classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_key')->unique(); // retr:agent-context:hot, retr:surface:hot, retr:audit:warm, retr:archive:cold
            $table->string('name');
            $table->string('serves'); // short label of the consumer, e.g. "Colony agents assembling working context"
            $table->foreignId('storage_tier_id')->constrained('storage_tiers');
            $table->unsignedInteger('p95_target_ms');
            $table->unsignedInteger('p99_target_ms')->nullable();
            $table->boolean('completeness_required')->default(false); // retr:audit:warm
            $table->boolean('zero_loss_required')->default(false); // retr:archive:cold
            $table->string('breach_action'); // short label, e.g. "degraded-mode flag"
            $table->timestamps();
        });

        Schema::create('retrieval_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retrieval_class_id')->constrained('retrieval_classes')->cascadeOnDelete();
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->unsignedInteger('p50_latency_ms')->nullable();
            $table->unsignedInteger('p95_latency_ms');
            $table->unsignedInteger('p99_latency_ms')->nullable();
            $table->boolean('sla_met');
            $table->boolean('degraded_mode_triggered')->default(false);
            $table->timestamps();

            // Aggregates only — latency/failure counts per class x window.
            // No query text, no result payloads, no tenant identifiers:
            // this table is the concrete instance of the "telemetry
            // plane, never the data plane" separation from wiki.md §2.
            $table->index(['retrieval_class_id', 'window_start']);
        });

        Schema::create('durability_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_tier_id')->constrained('storage_tiers');
            $table->string('check_type'); // restore_test | integrity_check
            $table->timestamp('audit_period_start');
            $table->timestamp('audit_period_end');
            $table->unsignedBigInteger('items_checked')->default(0);
            $table->unsignedBigInteger('items_passed')->default(0);
            $table->unsignedBigInteger('items_failed')->default(0);
            $table->decimal('integrity_score', 5, 4)->nullable(); // items_passed / items_checked, precomputed
            $table->string('result'); // pass | fail | degraded
            $table->timestamp('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('durability_outcomes');
        Schema::dropIfExists('retrieval_observations');
        Schema::dropIfExists('retrieval_classes');
        Schema::dropIfExists('indexes');
        Schema::dropIfExists('storage_tiers');
    }
};
