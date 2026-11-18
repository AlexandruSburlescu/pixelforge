<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\Money;
use Illuminate\Auth\Events\Registered;

/** Every new account starts with a small balance so it can try a few generations. */
class GrantWelcomeCredits
{
    public function __construct(private readonly CreditService $credits) {}

    public function handle(Registered $event): void
    {
        $amount = Money::nanoFromUsd((string) config('pixelforge.welcome_credit_usd'));

        if ($event->user instanceof User && $amount > 0) {
            // Fixed reference: registering twice can't grant it twice.
            $this->credits->purchase($event->user, $amount, 'welcome', 'Welcome credit');
        }
    }
}
