<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — Transition d'état d'opération refusée par la machine à états.
 *
 * Le message est traduit en 422 par le contrôleur (jamais un 500 : c'est une
 * erreur d'usage, pas une panne).
 */
class FarmOperationTransitionException extends RuntimeException
{
    public static function notAllowed(string $from, string $to): self
    {
        return new self(sprintf('Farm operation transition from [%s] to [%s] is not allowed.', $from, $to));
    }

    public static function reasonRequired(string $to): self
    {
        return new self(sprintf('A reason is required to move a started farm operation to [%s].', $to));
    }
}
