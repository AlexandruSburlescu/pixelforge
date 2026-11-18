<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pixelforge API keys, one per environment (local, production...). Each key has
 * its own webhook URL, signing secret and usage report, so costs reconcile per key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('environment', 32);
            $table->text('secret');           // encrypted cast; never logged
            $table->text('webhook_secret');   // encrypted cast (whsec_...)
            $table->string('hint', 16);       // last 4 chars, safe to display
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['environment', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
