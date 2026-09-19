<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Support;

/**
 * Politique prix/devises du module Retail (BC-17 RETAIL, #7672).
 *
 * Devises ISO 4217 acceptées pour les prix produits : whitelist stricte
 * pour la validation (jamais de valeur libre), calquée sur
 * `CatalogPricePolicy` (BC-28 C-CURRENCY #6886). Les montants sont
 * toujours stockés en minor units (entier) — jamais de flottants.
 */
final class RetailPricePolicy
{
    /** Devises ISO 4217 par défaut (zones produits Leopardo). */
    public const CURRENCIES = [
        'XOF',
        'XAF',
        'DZD',
        'MAD',
        'EUR',
        'USD',
    ];

    /**
     * @return list<string>
     */
    public static function allowedCurrencies(): array
    {
        return self::CURRENCIES;
    }
}
