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
        'FR' => ['label' => 'France', 'language' => 'fr', 'currency' => 'EUR', 'timezone' => 'Europe/Paris'],
        'TR' => ['label' => 'Turquie', 'language' => 'tr', 'currency' => 'TRY', 'timezone' => 'Europe/Istanbul'],
        'GB' => ['label' => 'Royaume-Uni', 'language' => 'en', 'currency' => 'GBP', 'timezone' => 'Europe/London'],
        'US' => ['label' => 'Etats-Unis', 'language' => 'en', 'currency' => 'USD', 'timezone' => 'America/New_York'],
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
}
