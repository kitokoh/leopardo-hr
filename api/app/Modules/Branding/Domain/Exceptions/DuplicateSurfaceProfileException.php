<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use DomainException;

/**
 * Deux providers de surface déclarent la même verticale (BC-41, #9579).
 *
 * Une collision est une erreur de configuration, pas un cas d'exécution : elle
 * doit être vue au boot. Le message nomme la verticale pour rendre le
 * diagnostic direct — sans quoi on cherche pendant une heure quel module a
 * écrasé `restaurant`.
 */
final class DuplicateSurfaceProfileException extends DomainException
{
    public static function forVertical(string $vertical): self
    {
        return new self(sprintf(
            'SURFACE_PROFILE_DUPLICATE: la verticale « %s » est déjà fournie par un autre provider. '
            .'Deux profils pour une même verticale rendraient le rendu non déterministe.',
            $vertical,
        ));
    }
}
