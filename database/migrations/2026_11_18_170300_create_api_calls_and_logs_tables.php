<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api_calls: one small row per outbound HTTP call to Pixelforge (kept forever,
 * cheap to index). api_logs: the bodies, split off so the hot table stays narrow;
 * pruned after a retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 6);
            $table->string('endpoint', 191);
            $table->string('idempotency_key', 64)->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedSmallInteger('status_code')->nullable(); // null = network error
            $table->string('error_code', 48)->nullable();
            $table->unsignedSmallInteger('retry_after')->nullable();
            $table->unsignedSmallInteger('rate_limit_remaining')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at');

            $table->index(['generation_id']);
            $table->index(['created_at']);
            $table->index(['status_code', 'created_at']);
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_call_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('request_body')->nullable();   // Authorization is never stored
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_calls');
    }
};
