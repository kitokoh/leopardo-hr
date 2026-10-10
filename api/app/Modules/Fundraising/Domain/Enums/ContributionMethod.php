<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Méthode de paiement d'une contribution (spec §3.2) : traditionnelle
 * (carte, virement, espèces) ou mobile money — la factory résout le driver.
 */
enum ContributionMethod: string
{
    case CARD = 'card';
    case MOBILE_MONEY = 'mobile_money';
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Carte bancaire',
            self::MOBILE_MONEY => 'Mobile money',
            self::BANK_TRANSFER => 'Virement bancaire',
            self::CASH => 'Espèces',
        };
    }
}
