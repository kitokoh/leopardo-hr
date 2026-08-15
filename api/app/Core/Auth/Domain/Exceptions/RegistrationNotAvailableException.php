<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #2617 — l'inscription est réservée aux invitations valides.
 * Sans invitation_token valide (existant, non expiré, non consommé, email
 * correspondant), l'inscription est refusée (422 REGISTRATION_NOT_AVAILABLE) :
 * plus d'inscription anonyme sur la plateforme.
 */
class RegistrationNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Registration is available by invitation only.',
            422,
            'REGISTRATION_NOT_AVAILABLE'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }
}
