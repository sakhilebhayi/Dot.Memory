<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governance-tier SLA breach awaiting human acknowledgment (wiki.md
 * §5's "Escalate"/"Integrity incident" breach_action rows only —
 * retr:audit:warm and retr:archive:cold). Raised by
 * App\Console\Commands\ScanSlaBreaches, reviewed via
 * App\Livewire\Memory\SlaDashboard's Acknowledge action. Never
 * auto-executes anything — breach_action is a human instruction, not a
 * system action this app performs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retrieval_sla_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retrieval_class_id')->constrained('retrieval_classes')->cascadeOnDelete();
            $table->foreignId('retrieval_observation_id')->constrained('retrieval_observations')->cascadeOnDelete();
            $table->string('breach_action');
            $table->string('status')->default('open');
            $table->timestamp('detected_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_detail')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['retrieval_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_sla_escalations');
    }
};
