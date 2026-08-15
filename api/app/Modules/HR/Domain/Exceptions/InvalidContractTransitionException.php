<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Exceptions;

use RuntimeException;

/**
 * #3891 — transition d'état illégale du cycle de vie d'un contrat
 * (ex. suspendre un contrat draft, activer un contrat terminé).
 *
 * Traduite en 422 par le controller, jamais en 500.
 */
final class InvalidContractTransitionException extends RuntimeException
{
}
