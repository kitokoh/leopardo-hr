<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Issue #1942 — une régularisation active existe déjà pour ce run original
 * (anti-double-application, index unique partiel
 * `payroll_runs_original_active_unique`).
 */
class PayrollRegularizationAlreadyExistsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Une régularisation active existe déjà pour ce run. Consultez la liste des régularisations avant d\'en créer une nouvelle.');
    }
}
