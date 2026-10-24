<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registre des devises supportees par la plateforme (noyau partage).
 *
 * Pourquoi ici et pas dans un module : la garde d'isolation des modules
 * (`ARCHITECTURE.md` §2, issue #5584) interdit a un module d'importer les
 * classes d'un autre — `Modules/Paiements` ne peut donc pas consommer
 * `Modules/Accounting\...\AccountingCurrencies`. `App\Support` est le noyau
 * partage, precisement destine a ce que plusieurs BC doivent connaitre.
 *
 * Source de verite des codes : le registre pays `CountryDefaults`. Toute
 * devise ouverte a la plateforme est donc une devise adossee a un pays connu —
 * aucune liste codee en dur, et les BC ne peuvent pas diverger entre eux.
 *
 * `AccountingCurrencies` (#5270) reste l'API historique de son BC et delegue
 * ici : son contrat public ne change pas, la source devient simplement unique.
 */
final class SupportedCurrencies
{
    /** @var list<string>|null */
    private static ?array $supported = null;

    /**
     * Codes ISO 4217 des devises supportees, tries, indexes de facon sequentielle.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        if (self::$supported !== null) {
            return self::$supported;
        }

        $currencies = [];

        foreach (CountryDefaults::all() as $defaults) {
            $currencies[strtoupper((string) $defaults['currency'])] = true;
        }

        $list = array_values(array_keys($currencies));
        sort($list);

        /** @var list<string> $list */
        self::$supported = $list;

        return $list;
    }

    /**
     * Normalise un code devise (majuscules, sans espaces), ou null si le
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

        return $normalized !== null && in_array($normalized, self::all(), true);
    }

    /**
     * Devise par defaut d'un pays explicitement couvert, ou null.
     *
     * On n'utilise PAS `CountryDefaults::for()` ici : cette methode replie un
     * pays inconnu sur `DZ` (donc DZD), et un repli silencieux est exactement
     * le defaut que #8743 corrige. Un pays non couvert rend `null` et laisse
     * l'appelant decider, visiblement.
     */
    public static function countryDefault(?string $country): ?string
    {
        $code = strtoupper(trim((string) $country));

        if ($code === '') {
            return null;
        }

        foreach (CountryDefaults::all() as $defaults) {
            if (strtoupper((string) $defaults['country']) !== $code) {
                continue;
            }

            $currency = self::normalize((string) $defaults['currency']);

            return $currency !== null && self::isSupported($currency) ? $currency : null;
        }

        return null;
    }
}
