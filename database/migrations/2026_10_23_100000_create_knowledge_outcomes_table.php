<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moderator outcomes for knowledge recommendations (R-9). Append-only: a changed mind is a NEW row,
     * never an update. Holds opaque identifiers only (a decision event id, a contribution ref and the
     * moderator's opaque uuid) -- no name, e-mail or content. Additive and guarded so a re-run is safe.
     */
    public function up(): void
    {
        if (Schema::hasTable('knowledge_outcomes')) {
            return;
        }

        Schema::create('knowledge_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('decision_event_id')->index();
            $table->string('contribution_ref');
            $table->string('outcome', 16); // adopted | declined
            $table->string('actor_ref', 64);
            $table->timestamp('decided_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_outcomes');
    }
};
