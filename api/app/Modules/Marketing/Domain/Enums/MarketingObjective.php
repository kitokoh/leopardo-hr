<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — objectif poursuivi par une campagne, une idée ou une
 * ressource (brief §B). Vocabulaire volontairement transversal : aucun
 * terme métier vertical.
 */
enum MarketingObjective: string
{
    case Awareness = 'notoriete';
    case Acquisition = 'acquisition';
    case Conversion = 'conversion';
    case Retention = 'fidelisation';
    case Launch = 'lancement';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Awareness => 'Notoriété',
            self::Acquisition => 'Acquisition',
            self::Conversion => 'Conversion',
            self::Retention => 'Fidélisation',
            self::Launch => 'Lancement',
        };
    }
}
