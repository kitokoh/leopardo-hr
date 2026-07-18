<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Levee quand une publication est demandee/planifiee alors que le
 * compte social du tenant n'est pas au statut 'active' (revoked, error).
 */
class SocialAccountInactiveException extends DomainException
{
    public static function withStatus(string $status): self
    {
        return new self(
            "Le compte social n'est pas actif (statut actuel: {$status}). Reconnectez le profil Ayrshare avant de publier.",
            422
        );
    }
}
