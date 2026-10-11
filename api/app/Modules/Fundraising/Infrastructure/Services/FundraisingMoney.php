<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

/**
 * Conversion montant ↔ unité mineure pour les passerelles de paiement de la
 * verticale FUNDRAISING.
 *
 * Copie volontairement autonome du utilitaire Accounting `GatewayMoney`
 * (les modules ne s'importent pas entre eux — architecture-check) :
 * Stripe travaille en unité mineure ; les devises à 0 décimale (XOF, XAF,
 * GNF…) ont pour unité mineure l'unité monétaire elle-même.
 */
final class FundraisingMoney
{
    /** Devises à 0 décimale (référence Stripe « zero-decimal currencies »). */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF',
    ];

    /**
     * Montant monétaire (ex. 500.00 XOF) → unité mineure provider (500).
     */
    public static function toMinorUnits(float $amount, string $currency): int
    {
        return self::isZeroDecimal($currency)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true);
    }
}
