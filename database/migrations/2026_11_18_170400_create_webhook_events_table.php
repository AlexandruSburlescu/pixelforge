<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound Pixelforge webhooks. UNIQUE(event_id) + insertOrIgnore is the dedupe:
 * a second delivery of the same event is stored zero extra times and never
 * processed twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 40)->unique();
            $table->string('type', 40);
            $table->string('provider_generation_id', 40)->nullable()->index();
            $table->foreignUlid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('delivery_attempt')->default(1);
            $table->unsignedInteger('duplicates')->default(0);
            $table->json('payload');
            $table->timestamp('event_created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['processed_at', 'created_at']);
            $table->index(['generation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
