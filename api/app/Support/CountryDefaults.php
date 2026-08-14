<?php

namespace App\Support;

final class CountryDefaults
{
    /**
     * @var array<string, array{label: string, language: string, currency: string, timezone: string}>
     */
    private const DEFAULTS = [
        'DZ' => ['label' => 'Algerie', 'language' => 'fr', 'currency' => 'DZD', 'timezone' => 'Africa/Algiers'],
        'MA' => ['label' => 'Maroc', 'language' => 'fr', 'currency' => 'MAD', 'timezone' => 'Africa/Casablanca'],
        'TN' => ['label' => 'Tunisie', 'language' => 'fr', 'currency' => 'TND', 'timezone' => 'Africa/Tunis'],
        'SN' => ['label' => 'Senegal', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Dakar'],
        'CI' => ['label' => 'Cote d Ivoire', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Abidjan'],
        'ML' => ['label' => 'Mali', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Bamako'],
        'BF' => ['label' => 'Burkina Faso', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Ouagadougou'],
        'BJ' => ['label' => 'Benin', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Porto-Novo'],
        'TG' => ['label' => 'Togo', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Lome'],
        'NE' => ['label' => 'Niger', 'language' => 'fr', 'currency' => 'XOF', 'timezone' => 'Africa/Niamey'],
        'CM' => ['label' => 'Cameroun', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Douala'],
        'GA' => ['label' => 'Gabon', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Libreville'],
        'CG' => ['label' => 'Congo', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Brazzaville'],
        'TD' => ['label' => 'Tchad', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Ndjamena'],
        'CF' => ['label' => 'Republique Centrafricaine', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Bangui'],
        'GQ' => ['label' => 'Guinee Equatoriale', 'language' => 'fr', 'currency' => 'XAF', 'timezone' => 'Africa/Malabo'],
        'FR' => ['label' => 'France', 'language' => 'fr', 'currency' => 'EUR', 'timezone' => 'Europe/Paris'],
        'TR' => ['label' => 'Turquie', 'language' => 'tr', 'currency' => 'TRY', 'timezone' => 'Europe/Istanbul'],
        'GB' => ['label' => 'Royaume-Uni', 'language' => 'en', 'currency' => 'GBP', 'timezone' => 'Europe/London'],
        'US' => ['label' => 'Etats-Unis', 'language' => 'en', 'currency' => 'USD', 'timezone' => 'America/New_York'],
        // PA2-COUNTRY-001: Canada was listed in the acceptance criteria
        // ("DZ, MA, TN, FR, TR, CEMAC, CEDEAO, CA exposes via CountryDefaults")
        // but was missing from this catalogue. Default timezone/language
        // reflect the most populous province (Ontario); CA companies can
        // still configure a province-specific timezone at company level.
        'CA' => ['label' => 'Canada', 'language' => 'en', 'currency' => 'CAD', 'timezone' => 'America/Toronto'],
    ];

    /**
     * @return array{country: string, label: string, language: string, currency: string, timezone: string}
     */
    public static function for(?string $country): array
    {
        $code = strtoupper(trim((string) $country));
        if ($code === '' || ! isset(self::DEFAULTS[$code])) {
            $code = 'DZ';
        }

        return [
            'country' => $code,
            ...self::DEFAULTS[$code],
        ];
    }

    /**
     * @return array<int, array{country: string, label: string, language: string, currency: string, timezone: string}>
     */
    public static function all(): array
    {
        return array_map(
            fn (string $code, array $defaults): array => ['country' => $code, ...$defaults],
            array_keys(self::DEFAULTS),
            self::DEFAULTS,
        );
    }

    /**
     * MULTI-PAYS (#1867) — résolution STRICTE, sans fallback silencieux vers
     * DZ. Retourne null pour un code absent, inconnu ou non supporté.
     *
     * @return array{country: string, label: string, language: string, currency: string, timezone: string}|null
     */
    public static function find(?string $country): ?array
    {
        $code = strtoupper(trim((string) $country));

        if ($code === '' || ! isset(self::DEFAULTS[$code])) {
            return null;
        }

        $defaults = self::DEFAULTS[$code];

        return [
            'country' => $code,
            'label' => $defaults['label'],
            'language' => $defaults['language'],
            'currency' => $defaults['currency'],
            'timezone' => $defaults['timezone'],
        ];
    }

    /**
     * MULTI-PAYS (#1867) — le code est-il un pays supporté du registre ?
     */
    public static function isSupported(?string $country): bool
    {
        return self::find($country) !== null;
    }
}
