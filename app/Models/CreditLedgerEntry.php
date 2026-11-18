<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only: rows are never updated or deleted.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $generation_id
 * @property LedgerType $type
 * @property int $amount_nano
 * @property int $balance_after_nano
 * @property string|null $reference
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'generation_id', 'type', 'amount_nano', 'balance_after_nano', 'reference', 'note'])]
class CreditLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount_nano' => 'integer',
            'balance_after_nano' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
