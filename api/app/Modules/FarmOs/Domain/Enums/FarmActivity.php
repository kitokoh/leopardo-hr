<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS (BC-37 AGRICULTURE) — Activité agricole déclarée par l'exploitation.
 *
 * Registre FERMÉ et VERSIONNÉ : extension par nouvelle case uniquement, jamais
 * de renommage ni de retrait (même règle que `SolutionIndustry`, BOS-013). Des
 * activités peuvent apparaître ou disparaître dans le temps : la fin d'une
 * activité se déclare par une date (`farm_activities.ended_at`), jamais par la
 * suppression de la ligne — aucune donnée historique n'est perdue.
 *
 * Cette enum est la SEULE liste autorisée à l'étape 1 de l'onboarding agricole
 * (fail-closed : toute valeur absente est rejetée à la validation). Elle ne
 * contient AUCUN libellé utilisateur (garde i18n) : la traduction se fait
 * côté front via {@see translationKey()}.
 *
 * @see \App\Modules\FarmOs\Domain\Services\FarmCapabilityRecommender
 */
enum FarmActivity: string
{
    // ── Agriculture végétale ─────────────────────────────────────────────
    case CerealCrops = 'cereal_crops';
    case MarketGardening = 'market_gardening';
    case Orchards = 'orchards';
    case Viticulture = 'viticulture';
    case Greenhouse = 'greenhouse';

    // ── Élevage ──────────────────────────────────────────────────────────
    case Cattle = 'cattle';
    case SheepGoat = 'sheep_goat';
    case Poultry = 'poultry';
    // Élevage porcin : proposé uniquement lorsque légalement applicable dans le
    // pays de l'exploitation (le filtrage par pays est de la responsabilité de
    // l'appelant — Farm OS n'émet aucun avis juridique).
    case PigFarming = 'pig_farming';
    case Dairy = 'dairy';

    // ── Autres productions ───────────────────────────────────────────────
    case Beekeeping = 'beekeeping';
    case Aquaculture = 'aquaculture';

    // ── Formes d'exploitation ────────────────────────────────────────────
    case MixedFarming = 'mixed_farming';
    case Cooperative = 'cooperative';
    case AgriServices = 'agri_services';
    case AgriFood = 'agri_food';

    /**
     * Clé i18n (front) — jamais de libellé en dur côté backend.
     */
    public function translationKey(): string
    {
        return 'farm.activity.'.$this->value;
    }

    /**
     * L'activité relève-t-elle de l'élevage (terre + animaux) ?
     */
    public function isLivestock(): bool
    {
        return in_array($this, [
            self::Cattle,
            self::SheepGoat,
            self::Poultry,
            self::PigFarming,
            self::Dairy,
        ], true);
    }

    /**
     * L'activité est-elle purement végétale ?
     */
    public function isCropProduction(): bool
    {
        return in_array($this, [
            self::CerealCrops,
            self::MarketGardening,
            self::Orchards,
            self::Viticulture,
            self::Greenhouse,
        ], true);
    }

    /**
     * Exploitation mixte : au moins un atelier végétal ET un atelier animal —
     * la sélection « exploitation mixte » est traitée comme un choix explicite
     * de l'utilisateur, pas comme une déduction automatique.
     */
    public function requiresCombinedSetup(): bool
    {
        return in_array($this, [self::MixedFarming, self::Cooperative], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function cropActivities(): array
    {
        return array_values(array_map(
            static fn (self $c): string => $c->value,
            array_filter(self::cases(), static fn (self $c): bool => $c->isCropProduction())
        ));
    }

    /**
     * @return list<string>
     */
    public static function livestockActivities(): array
    {
        return array_values(array_map(
            static fn (self $c): string => $c->value,
            array_filter(self::cases(), static fn (self $c): bool => $c->isLivestock())
        ));
    }
}
