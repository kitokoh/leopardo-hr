<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Accounting\Infrastructure\Services\AccountingCurrencies;
use App\Modules\Paiements\Domain\Exceptions\UnsupportedCurrencyException;
use App\Support\CountryDefaults;

/**
 * BC-38 PAIEMENTS (#8743) — resolution de la devise d'une operation.
 *
 * Source de verite de l'allowlist : `AccountingCurrencies` (#5270), qui derive
 * elle-meme du registre pays `CountryDefaults`. Aucune liste n'est codee en dur
 * ici : ajouter un pays au registre suffit a ouvrir sa devise, et les deux BC
 * ne peuvent pas diverger.
 *
 * Difference de fond avec l'ancien comportement : la chaine `?currency` ->
 * `company.currency` -> `'DZD'` **inventait** une devise. Une devise absente de
 * l'allowlist est maintenant refusee explicitement (422 cote HTTP), et le
 * dernier recours est la devise du **pays** de l'entreprise — jamais une
 * constante. Une configuration incomplete doit se voir, pas produire un solde
 * en dirhams algeriens chez un tenant senegalais.
 */
final class PaymentCurrencyResolver
{
    /**
     * Devise demandee par le client, validee contre l'allowlist.
     *
     * @throws UnsupportedCurrencyException
     */
    public function fromRequest(?string $requested): string
    {
        $normalized = AccountingCurrencies::normalize($requested);

        if ($normalized === null) {
            throw UnsupportedCurrencyException::malformed((string) $requested);
        }

        if (! AccountingCurrencies::isSupported($normalized)) {
            throw UnsupportedCurrencyException::notInAllowlist($normalized);
        }

        return $normalized;
    }

    /**
     * Devise de l'operation quand le client n'en fournit pas.
     *
     * Ordre : devise de l'entreprise si elle est supportee, sinon devise du
     * pays de l'entreprise. Un pays inconnu retombe sur le defaut du registre
     * pays (DZ) — decision du registre, pas de ce resolver, et desormais
     * tracee comme telle dans la reponse (`currency_source`).
     *
     * @throws UnsupportedCurrencyException
     */
    public function forCompany(?string $companyCurrency, ?string $companyCountry): string
    {
        $normalized = AccountingCurrencies::normalize($companyCurrency);

        if ($normalized !== null && AccountingCurrencies::isSupported($normalized)) {
            return $normalized;
        }

        $fallback = AccountingCurrencies::normalize(
            (string) CountryDefaults::for($companyCountry)['currency'],
        );

        if ($fallback === null || ! AccountingCurrencies::isSupported($fallback)) {
            throw UnsupportedCurrencyException::notConfigured();
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
     * Origine de la devise retenue — exposee aux clients pour rendre la
     * resolution observable au lieu de la laisser deviner.
     */
    public function sourceFor(?string $requested, ?string $companyCurrency): string
    {
        if ($requested !== null && trim($requested) !== '') {
            return 'request';
        }

        $normalized = AccountingCurrencies::normalize($companyCurrency);

        return ($normalized !== null && AccountingCurrencies::isSupported($normalized))
            ? 'company'
            : 'country_default';
    }

    /**
     * Allowlist courante, pour la documentation et les tests de contrat.
     *
     * @return list<string>
     */
    public function allowlist(): array
    {
        return AccountingCurrencies::supported();
    }
}
