<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\EmployeeDeparture;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #5324 — départ employé enregistré (workflow d'offboarding).
 *
 * Événement de sortie HR → consommateurs : audit (`hr.departure`),
 * webhook `employee.departed` (interface spec hr-lifecycle §6.2 — le
 * module Payroll doit l'écouter pour exclure l'employé des runs, gap G6).
 */
class EmployeeDeparted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly EmployeeDeparture $departure,
    ) {}
}
