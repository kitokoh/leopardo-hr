<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Exceptions\DomainException;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\PayrollCountryChartOfAccounts;
use Illuminate\Support\Facades\Log;

/**
 * Journal des écritures salariales — flux Paie → Comptabilité (issue #5239,
 * Phase C, Partie 1).
 *
 * Persiste EXACTEMENT le résultat de `PayrollAccountingExportService::journalLines()`
 * (#5256, lecture seule du run validé) : équilibre débit = crédit garanti par
 * construction, zéro re-calcul, zéro risque d'écart avec le moteur Payroll.
 *
 * Règles :
 *   - le run doit être `validated` ou `locked` (règle #2223 préservée —
 *     422 `PAYROLL_RUN_NOT_VALIDATED` sinon) ;
 *   - idempotent : contrainte UNIQUE (company_id, payroll_run_id, pay_slip_id,
 *     account_code, debit, credit) — une régénération ne double jamais rien ;
 *   - pays sans plan comptable → `pending` (rien persisté, log) — rattrapable
 *     par la commande `accounting:generate-payroll-entries --run={id}` après
 *     ajout du plan.
 */
final class PayrollJournalEntryService
{
    private const ALLOWED_RUN_STATUSES = ['validated', 'locked'];

    public function __construct(
        private readonly PayrollAccountingExportService $export,
    ) {}

    /**
     * Génère (idempotent) les écritures du journal pour un run de paie.
     *
     * @return array{run_id: int, status: string, generated: int}
     *         status : `generated` (écritures persistées ou run sans bulletin
     *         validé) | `pending` (plan comptable pays indisponible)
     */
    public function generateForRun(PayrollRun $run, ?int $actorId = null): array
    {
        if (! in_array($run->status, self::ALLOWED_RUN_STATUSES, true)) {
            throw new DomainException('PAYROLL_RUN_NOT_VALIDATED', 422, 'PAYROLL_RUN_NOT_VALIDATED');
        }

        if (PayrollCountryChartOfAccounts::forCountry($run->country_code) === null) {
            Log::warning('accounting.journal_entries_pending', [
                'run_id' => $run->id,
                'company_id' => $run->company_id,
                'country_code' => $run->country_code,
                'reason' => 'chart_of_accounts_unavailable',
            ]);

            return [
                'run_id' => (int) $run->id,
                'status' => 'pending',
                'generated' => 0,
            ];
        }

        $lines = $this->export->journalLines($run);

        if ($lines === []) {
            Log::info('accounting.journal_entries_empty', [
                'run_id' => $run->id,
                'company_id' => $run->company_id,
                'reason' => 'no_validated_slips',
            ]);

            return [
                'run_id' => (int) $run->id,
                'status' => 'generated',
                'generated' => 0,
            ];
        }

        $now = now();
        $rows = array_map(
            static fn (array $line): array => [
                'company_id' => (string) ($line['company_id'] ?? $run->company_id),
                'entry_date' => (string) $line['date'],
                'payroll_run_id' => (int) $line['payroll_run_id'],
                'pay_slip_id' => $line['pay_slip_id'] !== null ? (int) $line['pay_slip_id'] : null,
                'employee_id' => $line['employee_id'] !== null ? (int) $line['employee_id'] : null,
                'account_code' => (string) $line['account_code'],
                'account_label' => (string) $line['account_label'],
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'reference' => (string) $line['reference'],
                'source' => 'payroll_run',
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $lines
        );

        // insertOrIgnore : en cas de doublon (régénération, course), la
        // contrainte UNIQUE absorbe l'insertion — jamais de double écriture.
        $inserted = AccountingJournalEntry::query()->insertOrIgnore($rows);

        return [
            'run_id' => (int) $run->id,
            'status' => 'generated',
            'generated' => $inserted,
        ];
    }
}
