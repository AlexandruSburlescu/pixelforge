<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of GET /v1/usage per key per UTC day: Pixelforge's own numbers,
 * used by the admin report to prove our records match theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('generations');
            $table->unsignedBigInteger('input_text_tokens');
            $table->unsignedBigInteger('input_image_tokens');
            $table->unsignedBigInteger('output_tokens');
            $table->unsignedBigInteger('reasoning_tokens');
            $table->bigInteger('cost_nano');
            $table->timestamp('synced_at');

            $table->unique(['api_key_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_usage_daily');
    }
};
