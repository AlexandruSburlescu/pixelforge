<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CreditLedger\Pages;

use App\Models\CreditLedgerEntry;
use App\MoonShine\Resources\CreditLedger\CreditLedgerResource;
use App\Support\Money;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<CreditLedgerResource>
 */
final class CreditLedgerIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('User', 'user_id', fn (CreditLedgerEntry $e) => $e->user?->email ?? '#'.$e->user_id),
            Text::make('Type', 'type', fn (CreditLedgerEntry $e) => $e->type->value)
                ->badge(fn (mixed $t) => match ($t instanceof \BackedEnum ? $t->value : (string) $t) {
                    'purchase' => Color::GREEN,
                    'hold' => Color::YELLOW,
                    'refund' => Color::BLUE,
                    default => Color::GRAY,
                }),
            Text::make('Amount', 'amount_nano', fn (CreditLedgerEntry $e) => Money::toUsdString($e->amount_nano)),
            Text::make('Balance after', 'balance_after_nano', fn (CreditLedgerEntry $e) => Money::format($e->balance_after_nano, 6)),
            Text::make('Generation', 'generation_id', fn (CreditLedgerEntry $e) => $e->generation_id ? substr($e->generation_id, -8) : '—'),
            Text::make('Note', 'note'),
            Date::make('At', 'created_at')->format('Y-m-d H:i:s')->sortable(),
        ];
    }
}
