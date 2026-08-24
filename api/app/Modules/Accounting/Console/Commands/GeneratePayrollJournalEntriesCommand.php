<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Console\Commands;

use App\Exceptions\DomainException;
use App\Modules\Accounting\Infrastructure\Services\PayrollJournalEntryService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Console\Command;

/**
 * Rattrapage des écritures salariales du journal comptable (issue #5239,
 * Phase C, Partie 1).
 *
 * Idempotent : une exécution multiple ne double jamais les écritures
 * (contrainte UNIQUE du journal). À utiliser après ajout du plan comptable
 * d'un pays dont les runs étaient restés `pending`.
 */
class GeneratePayrollJournalEntriesCommand extends Command
{
    protected $signature = 'accounting:generate-payroll-entries {--run= : Identifiant du run de paie (obligatoire)}';

    protected $description = 'Génère les écritures salariales du journal comptable pour un run validé/locké (idempotent)';

    public function handle(PayrollJournalEntryService $entries): int
    {
        $runId = (int) $this->option('run');
        if ($runId <= 0) {
            $this->error('L\'option --run est requise (identifiant du run de paie).');

            return self::INVALID;
        }

        /** @var PayrollRun|null $run */
        $run = PayrollRun::query()->find($runId);
        if ($run === null) {
            $this->error("Run de paie {$runId} introuvable.");

            return self::FAILURE;
        }

        try {
            $result = $entries->generateForRun($run);
        } catch (DomainException $exception) {
            $this->error($exception->errorCode());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Run %d : statut %s, %d écriture(s) insérée(s).',
            $result['run_id'],
            $result['status'],
            $result['generated'],
        ));

        return self::SUCCESS;
    }
}
