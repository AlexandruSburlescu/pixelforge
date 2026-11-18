<?php

namespace App\Services\Credits;

use App\Support\Money;
use RuntimeException;

class InsufficientCredits extends RuntimeException
{
    public function __construct(public readonly int $neededNano, public readonly int $balanceNano)
    {
        parent::__construct(sprintf(
            'This generation can cost up to %s and your balance is %s. Buy credits to continue.',
            Money::format($neededNano), Money::format($balanceNano),
        ));
    }
}
