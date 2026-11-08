<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Issue #5239 — levée quand le journal d'un run n'est pas équilibré
 * (débit ≠ crédit) : refuse la persistance d'écritures comptables fausses.
 */
class UnbalancedPayrollEntriesException extends \RuntimeException {}
