<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

/**
 * Normalisation des adresses de destination des canaux CRM (issue #5727).
 *
 * Téléphones : E.164-ish (chiffres, + autorisé en tête, 8-15 chiffres).
 * Emails : trim + lowercase.
 */
final class CrmPhoneNormalizer
{
    public function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $value);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            $body = substr($digits, 1);
            if (strlen($body) < 8 || strlen($body) > 15 || ! ctype_digit($body)) {
                return null;
            }

            return '+'.$body;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15 || ! ctype_digit($digits)) {
            return null;
        }

        return $digits;
    }

    public function normalizeEmail(string $value): ?string
    {
        $email = strtolower(trim($value));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
