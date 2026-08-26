<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per stage of an intelligence loop (schemas/intelligence-loop
     * .schema.json in Dot.Brain). Rows sharing a loop_id are one cycle:
     * observation -> decision -> action -> outcome.
     *
     * Envelope columns only -- identifiers, enums, scores, timestamps --
     * so Memory can group, count and grade without reading content. The
     * narrative lives in the encrypted `detail` blob, consistent with the
     * first-party-knowledge carve-out recorded in wiki.md 0.7.0.
     */
    public function up(): void
    {
        Schema::create('loop_records', function (Blueprint $table) {
            $table->id();
            $table->string('loop_id')->index();
            $table->string('event_id')->unique();
            $table->string('stage'); // observation | decision | action | outcome
            $table->string('platform');
            $table->string('source')->nullable();

            // What the loop is about. Context is retrieved by subject, so
            // history accumulates about a thing rather than about a request.
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('subject_label')->nullable();
            $table->string('signature')->nullable();

            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // Decision facets (null unless stage = decision).
            $table->decimal('confidence', 4, 3)->nullable();
            $table->decimal('risk', 4, 3)->nullable();
            $table->string('autonomy_level')->nullable();
            $table->boolean('requires_approval')->nullable();

            // Action facets (null unless stage = action).
            $table->string('action_kind')->nullable();
            $table->string('executor_platform')->nullable();
            $table->string('mechanic_ref')->nullable();
            $table->string('approval_status')->nullable();
            $table->string('execution_status')->nullable();

            // Outcome facets (null unless stage = outcome). The verdict is
            // what grades a decision -- without it nothing can be learned.
            $table->string('verdict')->nullable();
            $table->string('measure')->nullable();

            $table->text('detail')->nullable(); // encrypted:array

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['platform', 'signature']);
            $table->index(['stage', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loop_records');
    }
};
