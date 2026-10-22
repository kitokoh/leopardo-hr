<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.1 Catégorie d'intrant agricole.
 *
 * Registre FERMÉ et VERSIONNÉ : extension par nouvelle case uniquement, jamais
 * de renommage ni de retrait (même règle que `FarmActivity` / `SolutionIndustry`).
 *
 * Aucun libellé utilisateur ici (garde i18n PA2-I18N-007) : la traduction se
 * fait côté front via {@see translationKey()}.
 */
enum FarmInputCategory: string
{
    case Seeds = 'seeds';
    case Fertilizers = 'fertilizers';
    case PlantProtection = 'plant_protection';
    case AnimalFeed = 'animal_feed';
    case Veterinary = 'veterinary';
    case SpareParts = 'spare_parts';
    case Fuel = 'fuel';
    case Other = 'other';

    /**
     * Clé i18n (front) — jamais de libellé en dur côté backend.
     */
    public function translationKey(): string
    {
        return 'farm.input_category.'.$this->value;
    }

    /**
     * Catégories dont la manipulation est réglementée : Farm OS ne fournit
     * AUCUN conseil de dosage ni de manipulation, et l'affichage des
     * précautions est de la responsabilité du front.
     */
    public function isRegulatedByDefault(): bool
    {
        return in_array($this, [self::PlantProtection, self::Veterinary], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
