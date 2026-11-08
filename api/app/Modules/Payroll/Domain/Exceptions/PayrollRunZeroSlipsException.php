<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Calcul abouti mais 0 bulletin généré (ex. aucune structure salariale active
 * pour le pays — #1767). L'Action a restaure `draft` ; l'interface répond
 * 422 `payroll.zero_slips_generated` (jamais de validation à vide).
 */
class PayrollRunZeroSlipsException extends \RuntimeException {}
