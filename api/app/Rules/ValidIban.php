<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates an IBAN (International Bank Account Number) using the ISO 13616
 * checksum algorithm (mod 97) plus a per-country expected length check.
 *
 * Country length table covers, at minimum, the countries currently supported
 * by the multi-country payroll engine (see AbstractCountryRules): DZ, MA, FR,
 * TR. A handful of other common countries are included for completeness.
 *
 * @see https://en.wikipedia.org/wiki/International_Bank_Account_Number#Structure
 */
class ValidIban implements ValidationRule
{
    /**
     * Registered IBAN length per ISO 3166-1 alpha-2 country code.
     *
     * DZ (Algeria) and MA (Morocco) are not part of the official IBAN
     * registry but are commonly issued locally with the lengths below and
     * are required here for the payroll bank-export countries.
     *
     * @var array<string, int>
     */
    private const COUNTRY_LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AT' => 20, 'AZ' => 28,
        'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22,
        'BR' => 29, 'CH' => 21, 'CR' => 22, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28,
        'DZ' => 24, 'EE' => 20, 'EG' => 29, 'ES' => 24,
        'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22,
        'GE' => 22, 'GI' => 23, 'GL' => 18, 'GR' => 27,
        'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22,
        'IL' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30,
        'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LC' => 32,
        'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21,
        'MA' => 28, 'MC' => 27, 'MD' => 24, 'ME' => 22,
        'MK' => 19, 'MR' => 27, 'MT' => 31, 'MU' => 30,
        'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28,
        'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24,
        'RS' => 22, 'SA' => 24, 'SC' => 31, 'SE' => 24,
        'SI' => 19, 'SK' => 24, 'SM' => 27, 'ST' => 25,
        'SV' => 28, 'TL' => 23, 'TN' => 24, 'TR' => 26,
        'UA' => 29, 'VA' => 22, 'VG' => 24, 'XK' => 20,
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $normalized = self::normalize($value);

        if (! self::isValid($normalized)) {
            $fail("Le champ :attribute n'est pas un IBAN valide.");
        }
    }

    /**
     * Strip spaces/dashes and uppercase, matching how IBANs are usually
     * pasted from bank documents.
     */
    public static function normalize(string $iban): string
    {
        return strtoupper(str_replace([' ', '-'], '', $iban));
    }

    /**
     * Structural + checksum validation of an already-normalized IBAN.
     */
    public static function isValid(string $iban): bool
    {
        if (! preg_match('/^([A-Z]{2})(\d{2})([A-Z0-9]+)$/', $iban, $matches)) {
            return false;
        }

        [, $countryCode, , $bban] = $matches;

        $expectedLength = self::COUNTRY_LENGTHS[$countryCode] ?? null;

        if ($expectedLength === null || strlen($iban) !== $expectedLength) {
            return false;
        }

        // BBAN must be alphanumeric only past the 4-char country+checksum prefix.
        if (! ctype_alnum($bban)) {
            return false;
        }

        return self::mod97Checksum($iban) === 1;
    }

    /**
     * ISO 7064 mod-97-10 checksum: rearrange (move first 4 chars to the end),
     * convert letters to numbers (A=10..Z=35), then reduce mod 97 in chunks
     * to avoid PHP integer overflow on very large IBANs.
     */
    private static function mod97Checksum(string $iban): int
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ((string) ($remainder.$chunk)) % 97;
        }

        return $remainder;
    }
}
