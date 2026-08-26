<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per ops-recall lookup: proof of how the archive is actually
     * being used by Dot.Brain. Envelope-only (platform, signature key,
     * match count) -- no content, consistent with wiki.md §2.
     */
    public function up(): void
    {
        Schema::create('ops_recall_events', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('signature');
            $table->unsignedInteger('matches')->default(0);
            $table->timestamp('created_at');

            $table->index(['platform', 'signature']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_recall_events');
    }
};
