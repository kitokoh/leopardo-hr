<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Exceptions;

use DomainException;

/**
 * Issue #5261 — le candidat a déjà été embauché (statut `hired` ou un
 * Employee avec le même candidate_id existe déjà) : pas de doublon de
 * fiche employé (anti-doublon, constitution §II).
 */
class CandidateAlreadyHiredException extends DomainException
{
    public static function forCandidate(string $applicantId): self
    {
        return new self(sprintf('Candidat %s déjà embauché.', $applicantId));
    }
}
