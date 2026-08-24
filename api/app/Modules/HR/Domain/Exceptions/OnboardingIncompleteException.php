<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Exceptions;

use DomainException;

/**
 * Issue #5261 — l'embauche d'un candidat est bloquée tant que les steps
 * d'onboarding obligatoires de l'entreprise ne sont pas complétés
 * (garde « onboarding avant activation paie »).
 */
class OnboardingIncompleteException extends DomainException
{
    public static function forCompany(string $companyId): self
    {
        return new self(
            sprintf('Onboarding steps obligatoires incomplets pour la company %s.', $companyId)
        );
    }
}
