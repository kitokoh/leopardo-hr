<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Statut d'une instance de fournisseur (BC-38, #8685).
 *
 * Seules `Active` et `Sandbox` sont resolvables par le ProviderResolver —
 * `Inactive` est exclue par construction.
 */
enum ProviderInstanceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Sandbox = 'sandbox';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function isResolvable(): bool
    {
        return $this !== self::Inactive;
    }
}
