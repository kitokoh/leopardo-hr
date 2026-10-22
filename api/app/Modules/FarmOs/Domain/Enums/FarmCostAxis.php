<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T4.1 Axe d'analyse d'une depense ou d'un produit.
 *
 * Registre FERME et VERSIONNE. Sert a la ventilation de la rentabilite :
 * exploitation, parcelle, culture, lot d'animaux, materiel, campagne.
 */
enum FarmCostAxis: string
{
    case Farm = 'farm';
    case Parcel = 'parcel';
    case Crop = 'crop';
    case LivestockBatch = 'livestock_batch';
    case Equipment = 'equipment';
    case Campaign = 'campaign';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.cost_axis.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
