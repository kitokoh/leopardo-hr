<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — statut de validation d'une ressource (brief §C, §J).
 *
 * La validation est une étape HUMAINE distincte de la production : une
 * ressource `pending` n'est pas publiable, une ressource `rejected` non
 * plus. Le schéma porte donc ce statut à part de la version du fichier.
 */
enum MarketingAssetValidationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Pending => 'En attente de validation',
            self::Approved => 'Validée',
            self::Rejected => 'Refusée',
        };
    }
}
