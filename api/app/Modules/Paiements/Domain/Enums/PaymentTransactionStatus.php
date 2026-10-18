<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

use App\Modules\Paiements\Domain\Exceptions\InvalidTransactionTransitionException;

/**
 * Machine a etats d'une transaction (BC-38, #8680).
 *
 * Les transitions sont declarees ici, pas dispersees dans les services :
 * `succeeded` est terminal (aucun retour a `pending`), `cancelled` ne peut
 * jamais devenir `succeeded`. Une transition non listee leve.
 */
enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Succeeded, self::Failed, self::Cancelled],
            self::Processing => [self::Succeeded, self::Failed, self::Cancelled],
            // Terminaux : aucune sortie.
            self::Succeeded, self::Failed, self::Cancelled => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new InvalidTransactionTransitionException($this, $target);
        }
    }
}
