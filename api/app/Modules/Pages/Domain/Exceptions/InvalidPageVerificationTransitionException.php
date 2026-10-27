<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * #8725 — transition de vérification refusée.
 *
 * Étend `App\Shared\Exceptions\DomainException` (et non `RuntimeException`)
 * pour bénéficier du renderer `{error, message, localized_message}` : la leçon
 * #8247 (AGENTS.md) rappelle qu'un `abort(404, 'CODE')` ne transmet JAMAIS son
 * code au client, le renderer `HttpExceptionInterface` réécrivant tout 404 en
 * `RESOURCE_NOT_FOUND` générique. Un code d'erreur stable et documentable
 * exige donc une exception de domaine dédiée, avec un statut 422 (la requête
 * est comprise, la transition est refusée par l'état courant).
 */
final class InvalidPageVerificationTransitionException extends DomainException
{
    public const CODE = 'PAGE_VERIFICATION_TRANSITION_INVALID';

    public static function between(string $from, string $to): self
    {
        return new self(
            __('pages.verification_transition_invalid', ['from' => $from, 'to' => $to]),
            422,
            self::CODE,
        );
    }
}
