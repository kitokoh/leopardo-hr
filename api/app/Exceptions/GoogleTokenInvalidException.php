<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Issue #3941 — identité Google non vérifiable : l'ID token fourni à
 * /api/v1/user/google-signin est absent, invalide, expiré, mal signé ou
 * son email n'est pas vérifié. Fail-closed : aucun token n'est émis.
 */
class GoogleTokenInvalidException extends DomainException
{
    public function __construct(string $reason = 'Le jeton Google est invalide ou expiré.')
    {
        parent::__construct($reason, 401, 'GOOGLE_TOKEN_INVALID');
    }
}
