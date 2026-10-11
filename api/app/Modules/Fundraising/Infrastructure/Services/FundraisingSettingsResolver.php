<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Domain\Models\FundraisingSettings;

/**
 * Résolution du paramétrage Fundraising du tenant courant.
 *
 * Une ligne par entreprise (pattern `AccountingSettings` #5232) : si le
 * tenant n'a jamais paramétré la verticale, les défauts
 * (`FundraisingSettings::defaults()` + config `fundraising.limits`) sont
 * servis SANS créer de ligne — l'upsert n'arrive qu'au premier PUT
 * explicite du responsable.
 *
 * Mémoïsée par instance (une requête = un contexte tenant) : les actions
 * publiques (initiation de contribution, surface publique) ne paient
 * qu'une seule lecture.
 */
final class FundraisingSettingsResolver
{
    private ?FundraisingSettings $memoized = null;

    /**
     * Settings du tenant courant (persistés ou défauts non sauvegardés).
     * Doit être appelé DANS un contexte tenant (middleware `tenant` ou
     * `TenantManager::withinTenant`) : le scope BelongsToCompany fait le
     * reste — hors contexte, la garde #3727 s'applique.
     */
    public function forCurrentCompany(): FundraisingSettings
    {
        if ($this->memoized instanceof FundraisingSettings) {
            return $this->memoized;
        }

        /** @var FundraisingSettings|null $settings */
        $settings = FundraisingSettings::query()->first();

        if (! $settings instanceof FundraisingSettings) {
            $settings = new FundraisingSettings;
            $settings->forceFill(FundraisingSettings::defaults());
        }

        return $this->memoized = $settings;
    }

    public function isMethodEnabled(ContributionMethod $method): bool
    {
        return $this->forCurrentCompany()->isMethodEnabled($method);
    }

    /**
     * Chaîne de fallback des garde-fous montant : cagnotte → settings
     * tenant → config (`fundraising.limits`) → constante de repli.
     */
    public function minAmount(?float $fundraiserMin): float
    {
        if ($fundraiserMin !== null) {
            return $fundraiserMin;
        }

        $settings = $this->forCurrentCompany();

        if ($settings->default_min_amount !== null) {
            return (float) $settings->default_min_amount;
        }

        /** @var array<string, mixed> $limits */
        $limits = config('fundraising.limits', []);

        return (float) ($limits['default_min_amount'] ?? 100);
    }

    public function maxAmount(?float $fundraiserMax): float
    {
        if ($fundraiserMax !== null) {
            return $fundraiserMax;
        }

        $settings = $this->forCurrentCompany();

        if ($settings->default_max_amount !== null) {
            return (float) $settings->default_max_amount;
        }

        /** @var array<string, mixed> $limits */
        $limits = config('fundraising.limits', []);

        return (float) ($limits['default_max_amount'] ?? 10000000);
    }

    /**
     * Montants suggérés par défaut à la création d'une cagnotte (le
     * payload explicite prime toujours).
     *
     * @return array<int, float|int>|null
     */
    public function defaultSuggestedAmounts(): ?array
    {
        return $this->forCurrentCompany()->default_suggested_amounts;
    }
}
