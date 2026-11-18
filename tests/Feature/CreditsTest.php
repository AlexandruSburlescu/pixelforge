<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buying_credits_adds_them_once_even_on_double_click(): void
    {
        $user = User::factory()->create();
        $click = ['amount' => 10, 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($user)->post('/credits', $click)->assertRedirect();
        $this->actingAs($user)->post('/credits', $click)->assertRedirect();

        $this->assertSame(10 * Money::NANO_PER_USD, $user->fresh()->balance_nano);
        $this->assertSame(1, CreditLedgerEntry::where('type', LedgerType::Purchase)->count());
    }

    public function test_only_offered_packs_can_be_bought(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/credits', ['amount' => 1_000_000, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->fresh()->balance_nano);
    }

    public function test_new_accounts_get_the_welcome_credit_once(): void
    {
        $user = User::factory()->create();

        event(new Registered($user));
        event(new Registered($user));

        $this->assertSame(Money::nanoFromUsd((string) config('pixelforge.welcome_credit_usd')), $user->fresh()->balance_nano);
    }

    public function test_ledger_balance_always_matches_the_cached_balance(): void
    {
        $user = User::factory()->create();
        event(new Registered($user));
        $this->actingAs($user)->post('/credits', ['amount' => 5, 'idempotency_key' => (string) Str::uuid()]);

        $this->assertSame((int) CreditLedgerEntry::where('user_id', $user->id)->sum('amount_nano'), $user->fresh()->balance_nano);
        $this->assertSame($user->fresh()->balance_nano, CreditLedgerEntry::where('user_id', $user->id)->latest('id')->value('balance_after_nano'));
    }
}
