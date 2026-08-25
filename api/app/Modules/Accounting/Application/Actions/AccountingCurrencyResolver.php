<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Domain\Support\AccountingCurrencies;
use App\Support\CountryDefaults;

/**
 * Résolution de la devise par défaut — issue #5270.
 *
 * Chaîne de priorité (COMPTABILITE_CONCEPTION.md §4) :
 *   1. devise explicite du contact (`AccountingContact.currency`) ;
 *   2. devise du paramétrage comptable (`AccountingSettings.currency`) ;
 *   3. devise du pays de l'entreprise (`CountryDefaults`).
 * La devise d'un document hérite de celle de son contact (défaut entreprise)
 * — la création REST des documents reste le périmètre de #5226/#5352, mais
 * la chaîne est consommable dès aujourd'hui par les contacts et les tests.
 */
final class AccountingCurrencyResolver
{
    public function __construct() {}

    /**
     * Devise de référence de l'entreprise (settings → pays).
     */
    public function forCompany(?string $country, ?AccountingSettings $settings = null): string
    {
        $settingsCurrency = $this->supportedOrNull($settings?->currency);

        if ($settingsCurrency !== null) {
            return $settingsCurrency;
        }

        $defaults = CountryDefaults::for($country);

        return (string) $defaults['currency'];
    }

    /**
     * Devise d'un contact (contact → settings → pays). Retourne la devise
     * de l'entreprise quand le contact n'en a pas.
     */
    public function forContact(AccountingContact $contact, ?string $companyCountry, ?AccountingSettings $settings = null): string
    {
        $contactCurrency = $this->supportedOrNull($contact->currency);

        if ($contactCurrency !== null) {
            return $contactCurrency;
        }

        return $this->forCompany($companyCountry, $settings);
    }

    /**
     * Normalise la devise si elle est supportée par le registre.
     */
    private function supportedOrNull(?string $currency): ?string
    {
        $normalized = AccountingCurrencies::normalize($currency);

        if ($normalized !== null && AccountingCurrencies::isSupported($normalized)) {
            return $normalized;
        }

        return null;
    }
}
