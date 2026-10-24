<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Exceptions\UnsupportedCurrencyException;
use App\Support\SupportedCurrencies;

/**
 * BC-38 PAIEMENTS (#8743) — résolution de la devise d'une opération.
 *
 * Allowlist : `App\Support\SupportedCurrencies`, registre partagé du noyau,
 * dérivé du registre pays `CountryDefaults`. Deux raisons de ne PAS appeler
 * `Modules/Accounting\...\AccountingCurrencies` directement :
 *   1. la garde d'isolation des modules (`ARCHITECTURE.md` §2, #5584) refuse
 *      tout import inter-module, et la CI le vérifie (« Nouveaux imports
 *      croisés détectés ») ;
 *   2. une seule source partagée rend la divergence structurellement
 *      impossible, au lieu de compter sur la discipline.
 *
 * Différence de fond avec l'ancien comportement : la chaîne `?currency` →
 * `company.currency` → `'DZD'` **inventait** une devise. Une devise absente de
 * l'allowlist est maintenant refusée explicitement (422 côté HTTP), et le
 * dernier recours est la devise du **pays** de l'entreprise — jamais une
 * constante. Une configuration incomplète doit se voir, pas produire un solde
 * en dirhams algériens chez un tenant sénégalais.
 */
final class PaymentCurrencyResolver
{
    /**
     * Devise demandée par le client, validée contre l'allowlist.
     *
     * @throws UnsupportedCurrencyException
     */
    public function fromRequest(?string $requested): string
    {
        $normalized = SupportedCurrencies::normalize($requested);

        if ($normalized === null) {
            throw UnsupportedCurrencyException::malformed((string) $requested);
        }

        if (! SupportedCurrencies::isSupported($normalized)) {
            throw UnsupportedCurrencyException::notInAllowlist($normalized);
        }

        return $normalized;
    }

    /**
     * Devise de l'opération quand le client n'en fournit pas.
     *
     * Ordre : devise de l'entreprise si elle est supportée, sinon devise du
     * pays de l'entreprise. Un pays non couvert par le registre lève
     * explicitement — on ne retombe pas sur `DZ` par accident.
     *
     * @throws UnsupportedCurrencyException
     */
    public function forCompany(?string $companyCurrency, ?string $companyCountry): string
    {
        $normalized = SupportedCurrencies::normalize($companyCurrency);

        if ($normalized !== null && SupportedCurrencies::isSupported($normalized)) {
            return $normalized;
        }

        $fallback = SupportedCurrencies::countryDefault($companyCountry);

        if ($fallback === null) {
            throw UnsupportedCurrencyException::notConfigured($companyCountry);
        }

        return $fallback;
    }

    /**
     * Devise effective : explicite si fournie, sinon celle de l'entreprise.
     *
     * @throws UnsupportedCurrencyException
     */
    public function resolve(?string $requested, ?string $companyCurrency, ?string $companyCountry): string
    {
        if ($requested !== null && trim($requested) !== '') {
            return $this->fromRequest($requested);
        }

        return $this->forCompany($companyCurrency, $companyCountry);
    }

    /**
     * Origine de la devise retenue — exposée aux clients pour rendre la
     * résolution observable au lieu de la laisser deviner.
     */
    public function sourceFor(?string $requested, ?string $companyCurrency): string
    {
        if ($requested !== null && trim($requested) !== '') {
            return 'request';
        }

        return SupportedCurrencies::isSupported($companyCurrency) ? 'company' : 'country_default';
    }

    /**
     * Allowlist courante, pour la documentation et les tests de contrat.
     *
     * @return list<string>
     */
    public function allowlist(): array
    {
        return SupportedCurrencies::all();
    }
}
