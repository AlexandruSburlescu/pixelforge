<?php

namespace App\Enums;

/**
 * Lifecycle: pending (stored, waiting for a provider slot) → queued → processing
 * → succeeded | failed. rank() makes transitions monotonic: an update only applies
 * when the new rank is higher, so late or duplicated webhooks are harmless.
 */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }

    public function isFinal(): bool
    {
        return $this->rank() === 3;
    }

    public function canMoveTo(self $next): bool
    {
        return $next->rank() > $this->rank();
    }

    /** @return list<string> */
    public static function inFlight(): array
    {
        return [self::Pending->value, self::Queued->value, self::Processing->value];
    }

    /** @return list<string> values whose rank is strictly below $status */
    public static function below(self $status): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->rank() < $status->rank()),
        ));
    }
}
