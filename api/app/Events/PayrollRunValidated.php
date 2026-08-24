<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Run de paie validé (étape RH franchie) — issue #5239, Phase C.
 *
 * Événement ADDITIF dispatché dans PayrollRunController::validateRun APRÈS la
 * validation effective (aucune modification du moteur de calcul Payroll —
 * FOCUS intact). Consommé côté Accounting par
 * App\Modules\Accounting\Application\Listeners\GeneratePayrollJournalEntries
 * (persistance idempotente des écritures salariales dans le journal).
 */
class PayrollRunValidated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PayrollRun $payrollRun,
        public readonly ?int $actorId = null,
    ) {}
}
