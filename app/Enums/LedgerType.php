<?php

namespace App\Enums;

enum LedgerType: string
{
    case Purchase = 'purchase';
    case Hold = 'hold';
    case Settle = 'settle';
    case Refund = 'refund';
}
