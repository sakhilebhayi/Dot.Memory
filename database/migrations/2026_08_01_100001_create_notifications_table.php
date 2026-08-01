<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard `database` notification channel table (matches the
 * output of `php artisan notifications:table`), added for the in-app
 * notification bell so operational events (SLA breaches, durability
 * check failures, etc.) have somewhere to land. No event/listener
 * wiring emits notifications automatically yet — see wiki.md §9
 * roadmap. This is Laravel/Jetstream's generic notification
 * infrastructure, not a domain telemetry table — it is not in scope of
 * the "store without reading" schema invariant enforced on the models
 * in the 2026_08_01_100002 migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
