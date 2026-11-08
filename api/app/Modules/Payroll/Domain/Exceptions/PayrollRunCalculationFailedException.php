<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Échec d'execution du calcul d'un run (après passage en `calculating`).
 * L'Action a deja restaure le statut `draft` (recalculable - #2221/#6529) ;
 * l'interface répond 422 `payroll.calculation_failed`.
 */
class PayrollRunCalculationFailedException extends \RuntimeException {}
