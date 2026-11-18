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
            'BRAND_SLUG_INVALID: « %s » ne produit pas un slug exploitable '
            .'(attendu : 1..160 caractères [a-z0-9-], sans tiret en bord).',
            $raw,
        ));
    }

    public static function reserved(string $slug): self
    {
        return new self(sprintf(
            'BRAND_SLUG_RESERVED: « %s » est réservé par une route de la plateforme.',
            $slug,
        ));
    }
}
