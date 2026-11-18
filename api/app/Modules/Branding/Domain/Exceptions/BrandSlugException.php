<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use DomainException;

/**
 * Un slug de Brand est invalide ou réservé (BC-41, #9578).
 *
 * Le slug est une URL publique : il doit être normalisable en ASCII, non vide,
 * et ne pas entrer en collision avec une route existante de la superapp.
 */
final class BrandSlugException extends DomainException
{
    public static function invalid(string $raw): self
    {
        return new self(sprintf(
            'BRAND_SLUG_INVALID: "%s" does not produce a usable slug '
            .'(expected: 1..160 characters [a-z0-9-], no leading or trailing hyphen).',
            $raw,
        ));
    }

    public static function reserved(string $slug): self
    {
        return new self(sprintf(
            'BRAND_SLUG_RESERVED: "%s" is reserved by a platform route.',
            $slug,
        ));
    }
}
