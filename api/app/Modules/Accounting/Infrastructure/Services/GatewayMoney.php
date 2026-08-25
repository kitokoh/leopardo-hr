<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

/**
 * #5272 — Conversion montant ↔ unité mineure pour les passerelles de paiement.
 *
 * Stripe/Chargily travaillent en unité mineure (centimes) pour les devises à
 * 2 décimales ; certaines devises (XOF, XAF, JPY…) sont à 0 décimale côté
 * Stripe — l'unité mineure EST l'unité monétaire. Référence : documentation
 * Stripe « zero-decimal currencies ».
 */
final class GatewayMoney
{
    /** Devises à 0 décimale (Stripe). */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF',
    ];

    /**
     * Convertit un montant monétaire (ex. 1190.00 DZD) en unité mineure
     * (119000). Arrondi à l'unité mineure la plus proche.
     */
    public static function toMinorUnits(float $amount, string $currency): int
    {
        return self::isZeroDecimal($currency)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * Convertit une unité mineure (ex. 119000) en montant monétaire (1190.00).
     */
    public static function fromMinorUnits(int $minor, string $currency): float
    {
        return self::isZeroDecimal($currency)
            ? (float) $minor
            : round($minor / 100, 2);
    }

    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true);
    }
}
