<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only money ledger. users.balance_nano is the running sum.
 *
 *   purchase  +amount               (Buy credits)
 *   hold      -max possible charge  (on submit)
 *   settle    +(hold - real charge) (on success; the charge itself is hold - settle)
 *   refund    +hold                 (on failure: failed generations are free)
 *
 * UNIQUE(generation_id, type) is the hard guarantee that a duplicated or replayed
 * webhook can never settle or refund the same generation twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->bigInteger('amount_nano');
            $table->bigInteger('balance_after_nano');
            // Idempotency for purchases (no generation); NULLs don't collide.
            $table->string('reference', 64)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at');

            $table->unique(['generation_id', 'type']);
            $table->unique(['user_id', 'reference']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};
