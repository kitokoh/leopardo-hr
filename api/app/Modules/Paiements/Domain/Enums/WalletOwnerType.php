<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Proprietaire logique d'un wallet (BC-38, #8680).
 */
enum WalletOwnerType: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
    case Client = 'client';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
