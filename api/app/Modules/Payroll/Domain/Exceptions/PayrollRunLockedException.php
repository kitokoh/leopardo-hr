<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use RuntimeException;

/**
 * Programme FOCUS (F-11) — tentative de modification d'un run de paie verrouillé
 * (clôture comptable). Aucune modification silencieuse après verrouillage.
 */
class PayrollRunLockedException extends RuntimeException
{
}
