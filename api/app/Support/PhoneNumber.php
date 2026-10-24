<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Noyau partage — normalisation d'un numero de telephone en E.164 (BC-03, #8736).
 *
 * **Pourquoi ici et pas dans un module.** Le telephone doit devenir un
 * identifiant de premier rang (OTP/SMS, dedoublonnage d'un client final
 * multi-app). Or il est manipule par plusieurs BC : `Core/Auth` (compte
 * utilisateur), `Modules/HR` (employe), `Modules/CRM` (contact). La garde
 * d'isolation (#5584) interdit a un module d'importer un autre module : une
 * normalisation unique ne peut donc pas vivre dans l'un d'eux. `App\Support`
 * est le noyau partage — meme raison que `SupportedCurrencies` (#8743) et
 * `Money` (#8714).
 *
 * **Ce que la normalisation garantit.** Deux ecritures du meme numero
 * (`+213 555 12 34 56`, `00213555123456`, `0555 12 34 56` avec pays DZ) doivent
 * produire **la meme chaine**, sinon l'unicite en base ne sert a rien : deux
 * comptes porteraient le meme numero sous deux formes et l'OTP resterait
 * ambigu. C'est la condition technique de l'ecart P-5 de l'audit readiness.
 *
 * **Ce que la normalisation ne fait PAS.** Elle ne devine pas le pays d'un
 * numero qui n'a ni prefixe international ni indicatif fourni : elle rend
 * `null` plutot qu'un numero faux. Un numero mal forme est refuse, jamais
 * « repare » au mieux.
 */
final class PhoneNumber
{
    /**
     * Indicatif international (sans `+`) par pays du registre partage.
     *
     * Le registre pays `CountryDefaults` porte la devise, la langue et le
     * fuseau, mais pas l'indicatif telephonique : cette table est donc locale
     * au noyau, et volontairement limitee aux pays couverts par la plateforme.
     * Un pays absent rend `null` — on ne devine pas.
     *
     * @var array<string, string>
     */
    private const DIAL_CODES = [
        'DZ' => '213',
        'MA' => '212',
        'TN' => '216',
        'SN' => '221',
        'CI' => '225',
        'ML' => '223',
        'BF' => '226',
        'BJ' => '229',
        'TG' => '228',
        'NE' => '227',
        'CM' => '237',
        'GA' => '241',
        'CG' => '242',
        'TD' => '235',
        'CF' => '236',
        'GQ' => '240',
        'FR' => '33',
        'TR' => '90',
        'GB' => '44',
        'US' => '1',
        'CA' => '1',
    ];

    /**
     * Normalise un numero en E.164 (`+` suivi de 8 a 15 chiffres), ou `null`.
     *
     * @param  string|null  $country  Code pays ISO 3166-1 alpha-2, utilise
     *                                uniquement quand le numero n'est pas deja
     *                                en forme internationale.
     */
    public static function toE164(?string $raw, ?string $country = null): ?string
    {
        $digits = self::digitsOnly($raw);

        if ($digits === null) {
            return null;
        }

        // Forme internationale explicite : `+213...` ou `00213...`.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $country = strtoupper(trim((string) $country));
        $dialCode = self::DIAL_CODES[$country] ?? null;

        // Un numero national commence par `0` : on retire ce zero et on prefixe
        // l'indicatif du pays. Sans pays connu, on ne peut pas trancher — un
        // `0555123456` pourrait etre algerien, francais ou autre.
        if (str_starts_with($digits, '0')) {
            if ($dialCode === null) {
                return null;
            }

            $digits = $dialCode.ltrim($digits, '0');
        } elseif ($dialCode !== null && ! self::startsWithAnyDialCode($digits)) {
            // Numero sans `0` initial et sans indicatif connu : on suppose le
            // pays fourni (cas d'un import de contacts locaux).
            $digits = $dialCode.$digits;
        }

        return self::isValidE164($digits) ? '+'.$digits : null;
    }

    /**
     * Vrai si la chaine est deja un E.164 valide.
     */
    public static function isValidE164(?string $value): bool
    {
        $digits = self::digitsOnly($value);

        if ($digits === null) {
            return false;
        }

        // E.164 : 15 chiffres au maximum, et un numero reel en compte au moins
        // 8 (indicatif pays inclus). Le `+` est exige dans la forme canonique.
        return str_starts_with(trim((string) $value), '+')
            && strlen($digits) >= 8
            && strlen($digits) <= 15;
    }

    /**
     * Indicatif international d'un pays couvert, ou `null`.
     */
    public static function dialCodeFor(?string $country): ?string
    {
        return self::DIAL_CODES[strtoupper(trim((string) $country))] ?? null;
    }

    /**
     * Chiffres seuls d'un numero, ou `null` si la chaine ne contient pas que
     * des chiffres, espaces, tirets, points, parentheses et un `+` initial.
     */
    private static function digitsOnly(?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        // On refuse tout caractere qui ne fait pas partie d'une ecriture
        // telephonique usuelle : un `#` ou une lettre signale une saisie
        // erronee, pas un numero a « nettoyer ».
        if (preg_match('/^\+?[0-9 ()\-.]+$/', $value) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === null || $digits === '' ? null : $digits;
    }

    /**
     * Vrai si le numero commence par un indicatif connu — evite de prefixer un
     * numero qui porte deja son indicatif sans `+`.
     */
    private static function startsWithAnyDialCode(string $digits): bool
    {
        foreach (self::DIAL_CODES as $dialCode) {
            if (str_starts_with($digits, $dialCode)) {
                return true;
            }
        }

        return false;
    }
}
