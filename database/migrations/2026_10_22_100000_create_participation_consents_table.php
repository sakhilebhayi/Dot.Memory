<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in consent for person-level participation records (R-9). One row
     * per InfoDot user UUID; the absence of a row means "no consent". The
     * row holds the decision and its dates only -- no name, no e-mail.
     */
    public function up(): void
    {
        Schema::create('participation_consents', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid', 36)->unique();
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('source')->default('infodot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_consents');
    }
};
