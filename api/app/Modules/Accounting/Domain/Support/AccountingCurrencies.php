<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Support;

use App\Support\CountryDefaults;

/**
 * Registre des devises supportées par le module Comptabilité — issue #5270.
 *
 * Source de vérité unique : l'union des devises du registre pays existant
 * (`CountryDefaults` : DZ, MA, TN, SN, CI, ML, BF, BJ, TG, NE, CM, GA, CG,
 * TD, CF, GQ, FR, TR, GB, US, CA). La validation de la devise des contacts,
 * des settings et de l'endpoint de conversion passe TOUTE par ce registre —
 * jamais de liste codée en dur ailleurs (règle #5270, anti-divergence).
 */
final class AccountingCurrencies
{
    /** @var array<int, string>|null */
    private static ?array $supported = null;

    /**
     * Codes ISO 4217 (3 lettres) des devises supportées, triés.
     *
     * @return array<int, string>
     */
    public static function supported(): array
    {
        if (self::$supported !== null) {
            return self::$supported;
        }

        $currencies = [];

        foreach (CountryDefaults::all() as $defaults) {
            $currencies[strtoupper((string) $defaults['currency'])] = true;
        }

        $list = array_keys($currencies);
        sort($list);

        self::$supported = $list;

        return $list;
    }

    /**
     * Normalise un code devise (majuscules, sans espaces) ou null si le
     * format n'est pas un code ISO 4217 valide (3 lettres).
     */
    public static function normalize(?string $currency): ?string
    {
        $value = strtoupper(trim((string) $currency));

        if ($value === '' || preg_match('/^[A-Z]{3}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    public static function isSupported(?string $currency): bool
    {
        $normalized = self::normalize($currency);

        return $normalized !== null && in_array($normalized, self::supported(), true);
    }
}
