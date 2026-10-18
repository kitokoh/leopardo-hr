<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Nature d'une transaction (BC-38, #8680).
 */
enum PaymentTransactionType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
    case InternalTransfer = 'internal_transfer';
    case Refund = 'refund';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * Un transfert strictement interne n'a aucun rail externe : il ne doit
     * jamais porter de `provider_instance_id`.
     */
    public function requiresProvider(): bool
    {
        return $this !== self::InternalTransfer;
    }
}
