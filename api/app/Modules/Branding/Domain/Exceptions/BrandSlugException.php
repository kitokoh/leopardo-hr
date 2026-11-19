<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un slug de Brand est invalide ou réservé (BC-41, #9578).
 *
 * Le slug est une URL publique : il doit être normalisable en ASCII, non vide,
 * et ne pas entrer en collision avec une route existante de la superapp.
 *
 * ## Pourquoi `App\Exceptions\DomainException` et pas `\DomainException`
 *
 * Le renderer de `bootstrap/app.php` n'écoute QUE
 * `App\Exceptions\DomainException` (#8247) : une sous-classe du
 * `\DomainException` global remontait donc en **500** alors que le message
 * était déjà traduit dans les 4 catalogues `lang/<locale>/errors.php`. Le client
 * recevait « Server Error » pour une faute de saisie, et le code
 * `BRAND_SLUG_INVALID` n'existait que dans les tests. Toute erreur de contrat
 * exposée à l'appelant hérite de cette classe : 422 + `{error, message,
 * localized_message}`.
 */
final class BrandSlugException extends DomainException
{
    public static function invalid(string $raw): self
    {
        return new self(
            sprintf(
                'BRAND_SLUG_INVALID: "%s" does not produce a usable slug '
                .'(expected: 1..160 characters [a-z0-9-], no leading or trailing hyphen).',
                $raw,
            ),
            422,
            'BRAND_SLUG_INVALID',
        );
    }

    public static function reserved(string $slug): self
    {
        return new self(
            sprintf('BRAND_SLUG_RESERVED: "%s" is reserved by a platform route.', $slug),
            422,
            'BRAND_SLUG_RESERVED',
        );
    }
}
