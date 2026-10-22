<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.1 Unité de gestion d'un intrant.
 *
 * Registre FERMÉ et VERSIONNÉ. Farm OS ne CONVERTIT jamais automatiquement
 * entre unités (un sac n'est pas un poids connu) : l'unité est une étiquette
 * de saisie, la conversion reste un geste explicite de l'exploitant.
 */
enum FarmInputUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Litre = 'l';
    case Millilitre = 'ml';
    case Unit = 'unit';
    case Bag = 'bag';
    case Dose = 'dose';
    case Tonne = 't';

    /**
     * Clé i18n (front) — jamais de libellé en dur côté backend.
     */
    public function translationKey(): string
    {
        return 'farm.input_unit.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
