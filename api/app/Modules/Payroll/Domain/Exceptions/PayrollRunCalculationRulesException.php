<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Résolution des règles pays impossible au calcul (ex. pays sans règles
 * enregistrées, #2555). L'Action a restaure le statut `draft` (recalculable) ;
 * l'interface répond 422 `payroll.calculation_failed`.
 */
class PayrollRunCalculationRulesException extends \RuntimeException {}
