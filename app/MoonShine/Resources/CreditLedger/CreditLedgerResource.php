<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CreditLedger;

use App\Models\CreditLedgerEntry;
use App\MoonShine\Resources\CreditLedger\Pages\CreditLedgerIndexPage;
use App\MoonShine\Resources\ReadOnlyResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ReadOnlyResource<CreditLedgerEntry>
 */
#[Icon('banknotes')]
#[Group('Money', 'banknotes')]
#[Order(2)]
class CreditLedgerResource extends ReadOnlyResource
{
    protected string $model = CreditLedgerEntry::class;

    protected array $with = ['user'];

    protected string $sortColumn = 'id';

    public function getTitle(): string
    {
        return 'Credit ledger';
    }

    protected function pages(): array
    {
        return [CreditLedgerIndexPage::class];
    }

    protected function search(): array
    {
        return ['generation_id', 'reference'];
    }
}
