<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per user request. The ULID primary key doubles as the Idempotency-Key
 * sent to Pixelforge and as metadata.generation_id, so a webhook can find its row
 * even if it arrives before our POST returned.
 *
 * All money columns are integer nano-USD (1 USD = 1_000_000_000).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();

            // Client-sent key: a double click or a retried form post maps to the same row.
            $table->string('idempotency_key', 64);
            $table->string('provider_id', 40)->nullable()->unique();
            $table->string('status', 16);

            $table->text('prompt');
            $table->string('size', 9);
            $table->string('quality', 6);
            $table->json('reference_images');
            $table->json('rates');            // rates snapshot used for the estimate, replaced by the provider's

            $table->bigInteger('estimate_min_nano');
            $table->bigInteger('estimate_nano');
            $table->bigInteger('estimate_max_nano');
            $table->bigInteger('hold_nano')->default(0);

            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->bigInteger('provider_cost_nano')->nullable();
            $table->bigInteger('charge_nano')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('image_path')->nullable();

            $table->string('error_code', 48)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedSmallInteger('submit_attempts')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);      // history page
            $table->index(['status', 'updated_at']);       // reconciler: stuck in-flight rows
            $table->index(['completed_at']);               // daily report (billed on completion day)
            $table->index(['api_key_id', 'completed_at']); // per-key reconciliation vs GET /v1/usage
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
