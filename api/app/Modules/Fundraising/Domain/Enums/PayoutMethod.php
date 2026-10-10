<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Canal de reversement au bénéficiaire (spec §3.3) : mobile money ou
 * virement bancaire.
 */
enum PayoutMethod: string
{
    case MOBILE_MONEY = 'mobile_money';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::MOBILE_MONEY => 'Mobile money',
            self::BANK_TRANSFER => 'Virement bancaire',
        };
    }
}
