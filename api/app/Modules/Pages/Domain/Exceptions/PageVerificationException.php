<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * #8725 — refus métier du dossier de vérification.
 *
 * Une exception, des fabriques nommées : le code d'erreur rendu au client est
 * explicite et stable (`PAGE_VERIFICATION_*`), ce qui permet à l'interface de
 * dire *pourquoi* une demande est refusée au lieu d'un 422 générique. La leçon
 * #8247 rappelle qu'un `abort()` ne transmet jamais son code — d'où une
 * exception de domaine avec un code en propriété.
 */
final class PageVerificationException extends DomainException
{
    public static function methodDisabled(string $method): self
    {
        return new self(
            sprintf('La méthode de vérification « %s » n\'est pas activée sur cette plateforme.', $method),
            422,
            'PAGE_VERIFICATION_METHOD_DISABLED',
        );
    }

    public static function requestAlreadyOpen(): self
    {
        return new self(
            'Un dossier de vérification est déjà ouvert pour cette Page.',
            409,
            'PAGE_VERIFICATION_ALREADY_OPEN',
        );
    }

    public static function requestClosed(): self
    {
        return new self(
            'Ce dossier de vérification est clos : ouvrez-en un nouveau pour redemander une vérification.',
            409,
            'PAGE_VERIFICATION_CLOSED',
        );
    }

    public static function suspendedPage(): self
    {
        return new self(
            'Une Page suspendue ne peut pas faire l\'objet d\'une demande de vérification.',
            422,
            'PAGE_VERIFICATION_SUSPENDED',
        );
    }

    public static function wrongMethod(string $expected, string $actual): self
    {
        return new self(
            sprintf('Cette opération exige la méthode « %s » (dossier en « %s »).', $expected, $actual),
            422,
            'PAGE_VERIFICATION_WRONG_METHOD',
        );
    }

    public static function cooldown(int $seconds): self
    {
        return new self(
            sprintf('Un code vient d\'être envoyé : réessayez dans %d seconde(s).', max(1, $seconds)),
            429,
            'PAGE_VERIFICATION_COOLDOWN',
        );
    }

    public static function codeNotFound(): self
    {
        return new self(
            'Aucun code n\'a été émis pour ce numéro sur ce dossier.',
            422,
            'PAGE_VERIFICATION_CODE_NOT_FOUND',
        );
    }

    /**
     * @param  string  $state  `expired` | `consumed` | `locked`
     */
    public static function codeUnusable(string $state): self
    {
        $message = match ($state) {
            'expired' => 'Ce code a expiré : demandez-en un nouveau.',
            'consumed' => 'Ce code a déjà été utilisé.',
            default => 'Trop de tentatives : demandez un nouveau code.',
        };

        return new self($message, 422, 'PAGE_VERIFICATION_CODE_'.strtoupper($state));
    }

    public static function codeMismatch(int $remaining): self
    {
        return new self(
            sprintf('Code incorrect (%d tentative(s) restante(s)).', max(0, $remaining)),
            422,
            'PAGE_VERIFICATION_CODE_MISMATCH',
        );
    }

    public static function noEvidence(): self
    {
        return new self(
            'Un dossier documentaire ne peut pas être approuvé sans aucune pièce justificative.',
            422,
            'PAGE_VERIFICATION_NO_EVIDENCE',
        );
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalidEvidence(array $errors): self
    {
        return new self(
            'Pièce justificative refusée : '.implode(' ', $errors),
            422,
            'PAGE_VERIFICATION_EVIDENCE_INVALID',
        );
    }

    public static function publicPath(): self
    {
        return new self(
            'Le chemin de stockage d\'une pièce ne peut pas être une URL publique ou un chemin absolu.',
            422,
            'PAGE_VERIFICATION_EVIDENCE_PATH_PUBLIC',
        );
    }

    public static function invalidReason(string $reason): self
    {
        return new self(
            sprintf('Motif de décision inconnu : « %s ».', $reason),
            422,
            'PAGE_VERIFICATION_REASON_INVALID',
        );
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            sprintf('Transition de vérification impossible : %s -> %s.', $from, $to),
            409,
            'PAGE_VERIFICATION_TRANSITION_INVALID',
        );
    }
}
