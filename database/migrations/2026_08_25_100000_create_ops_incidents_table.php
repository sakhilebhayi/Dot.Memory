<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational-incident archive for the Dot.Brain guardian (data plane,
     * not telemetry plane): the envelope columns exist only so the
     * publisher can retrieve and aggregate its own records (by platform,
     * signature, status); the narrative detail lives in the opaque,
     * encrypted `record` blob, which Dot.Memory stores without reading --
     * wiki.md §2.
     */
    public function up(): void
    {
        Schema::create('ops_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_uid')->unique();
            $table->string('platform');
            $table->string('environment');
            $table->string('detection_source');
            $table->string('signature');
            $table->string('component');
            $table->string('severity'); // sev1..sev4 (Dot.Brain incident.schema.json)
            $table->string('status'); // open|remediating|resolved|escalated|rolled_back
            $table->string('tests_result')->nullable();
            $table->string('deploy_result')->nullable();
            $table->string('validation_result')->nullable();
            $table->boolean('rollback_occurred')->default(false);
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('record')->nullable(); // encrypted:json -- opaque to Dot.Memory
            $table->timestamps();

            $table->index(['platform', 'signature']);
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_incidents');
    }
};
