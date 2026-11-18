<?php

namespace App\Services\Credits;

use App\Enums\LedgerType;
use App\Models\CreditLedgerEntry;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Every balance change writes one ledger row and updates users.balance_nano in
 * the same transaction. The ledger's unique keys make settle/refund idempotent:
 * a second attempt hits the constraint and changes nothing.
 *
 * Callers must already be inside a DB transaction for hold/settle/refund, so the
 * money moves atomically with the generation's status change.
 */
class CreditService
{
    /** Reserve the maximum possible charge before submitting. */
    public function hold(User $user, Generation $generation, int $amountNano): void
    {
        $this->assertInTransaction();
        $balance = $this->lockedBalance($user);

        if ($balance < $amountNano) {
            throw new InsufficientCredits($amountNano, $balance);
        }

        $this->write($user->id, $generation->id, LedgerType::Hold, -$amountNano, $balance);
    }

    /**
     * Turn the hold into the real charge: return (hold − charge). Returns false if
     * this generation was already settled or refunded (duplicate event).
     */
    public function settle(Generation $generation, int $chargeNano): bool
    {
        $this->assertInTransaction();

        if ($this->alreadyClosed($generation)) {
            return false;
        }

        return $this->tryWrite($generation, LedgerType::Settle, $generation->hold_nano - $chargeNano);
    }

    /** Failed generations are free: return the whole hold. */
    public function refund(Generation $generation): bool
    {
        $this->assertInTransaction();

        if ($this->alreadyClosed($generation)) {
            return false;
        }

        return $this->tryWrite($generation, LedgerType::Refund, $generation->hold_nano);
    }

    /** Fake purchase. $reference makes a retried "Buy" click count once. */
    public function purchase(User $user, int $amountNano, string $reference, ?string $note = null): CreditLedgerEntry
    {
        return DB::transaction(function () use ($user, $amountNano, $reference, $note) {
            $existing = CreditLedgerEntry::where('user_id', $user->id)->where('reference', $reference)->first();

            if ($existing) {
                return $existing;
            }

            return $this->write($user->id, null, LedgerType::Purchase, $amountNano, $this->lockedBalance($user), $reference, $note);
        });
    }

    private function alreadyClosed(Generation $generation): bool
    {
        return CreditLedgerEntry::where('generation_id', $generation->id)
            ->whereIn('type', [LedgerType::Settle->value, LedgerType::Refund->value])
            ->exists();
    }

    private function tryWrite(Generation $generation, LedgerType $type, int $amountNano): bool
    {
        try {
            // Savepoint: a constraint hit must not abort the caller's transaction.
            DB::transaction(fn () => $this->write(
                $generation->user_id, $generation->id, $type, $amountNano,
                $this->lockedBalance($generation->user_id),
            ));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function write(
        int $userId,
        ?string $generationId,
        LedgerType $type,
        int $amountNano,
        int $balanceBefore,
        ?string $reference = null,
        ?string $note = null,
    ): CreditLedgerEntry {
        $entry = CreditLedgerEntry::create([
            'user_id' => $userId,
            'generation_id' => $generationId,
            'type' => $type,
            'amount_nano' => $amountNano,
            'balance_after_nano' => $balanceBefore + $amountNano,
            'reference' => $reference,
            'note' => $note,
        ]);

        // Relative update: correct even if another writer changed the row.
        User::whereKey($userId)->increment('balance_nano', $amountNano);

        return $entry;
    }

    private function lockedBalance(User|int $user): int
    {
        return (int) User::whereKey($user instanceof User ? $user->id : $user)
            ->lockForUpdate()
            ->value('balance_nano');
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Credit changes must run inside the caller\'s transaction.');
        }
    }
}
