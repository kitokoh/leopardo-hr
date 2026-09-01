<?php

declare(strict_types=1);

namespace App\Core\Solutions\Survey\Exceptions;

use RuntimeException;

/**
 * Questionnaire de solution inconnu (fail-closed).
 *
 * @see \App\Core\Solutions\Survey\SolutionSurveyRegistry::resolve()
 */
final class SolutionSurveyNotFoundException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Solution survey [%s] not found.', $code));
    }
}
