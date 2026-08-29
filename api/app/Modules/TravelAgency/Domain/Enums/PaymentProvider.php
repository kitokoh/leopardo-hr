<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Fournisseur de paiement d'une transaction (TRAVEL-210, issue #6023).
 */
enum PaymentProvider: string
{
    case CASH = 'cash';
    case PVIT = 'pvit';
    case MOMO = 'momo';
    case CARD = 'card';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Espèces',
            self::PVIT => 'PVit',
            self::MOMO => 'Mobile Money',
            self::CARD => 'Carte bancaire',
        };
    }
}
