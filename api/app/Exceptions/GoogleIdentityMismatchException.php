<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * #6531 — audit sécurité : un compte Google (sub) tente de s'authentifier sur
 * un employé déjà lié à un autre sub Google. Refus 401 pour empêcher la
 * réattribution silencieuse d'un email Workspace après départ d'un employé.
 */
class GoogleIdentityMismatchException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Cette adresse Google est déjà liée à un autre compte. Contactez le support.',
            401,
            'GOOGLE_IDENTITY_MISMATCH'
        );
    }
}
