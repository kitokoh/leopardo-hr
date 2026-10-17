<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

use App\Modules\Marketing\Domain\Models\MarketingCampaign;

/**
 * Marketing OS — cycle de vie d'une campagne (brief §I).
 *
 * @see MarketingCampaign
 */
enum MarketingCampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Active',
            self::Paused => 'En pause',
            self::Finished => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }
}
