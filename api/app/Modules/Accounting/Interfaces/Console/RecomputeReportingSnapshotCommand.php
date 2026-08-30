<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Console;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\RecomputeAccountingReportingSnapshotJob;
use App\Modules\Accounting\Application\Actions\AccountingReportingSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * BC-22-D10 (issue #6243) — recompute manuel d'un snapshot de read model.
 *
 * Déclencheur opérationnel documenté dans `ANALYTICS_SNAPSHOTS.md` : à utiliser
 * quand un endpoint de reporting dépasse son budget p95
 * (`dev-hub/tools/performance-budgets.json`) — jamais préventivement sur des
 * volumes sains (le read model à la volée reste la stratégie par défaut).
 *
 * Usage :
 *   php artisan accounting:reporting-snapshot <company>                 (dashboard, période courante)
 *   php artisan accounting:reporting-snapshot <company> --from=2026-08-01 --to=2026-08-31
 *   php artisan accounting:reporting-snapshot <company> --sync          (recompute inline au lieu du job)
 */
final class RecomputeReportingSnapshotCommand extends Command
{
    protected $signature = 'accounting:reporting-snapshot
                            {company : ID (uuid) ou slug de l\'entreprise cible}
                            {--from= : Début de période (YYYY-MM-DD, défaut début de mois)}
                            {--to= : Fin de période (YYYY-MM-DD, défaut aujourd\'hui)}
                            {--report=accounting_dashboard : Read model à matérialiser}
                            {--sync : Exécuter le recompute inline (défaut : job async tenant-scoped)}';

    protected $description = 'Recompute idempotent du snapshot d\'un read model de reporting (BC-22-D10, issue #6243)';

    public function handle(AccountingReportingSnapshotService $snapshots): int
    {
        $input = $this->argument('company');

        if (trim((string) $input) === '') {
            $this->error("Argument obligatoire : ID (uuid) ou slug de l'entreprise.");

            return self::FAILURE;
        }

        $company = $this->resolveCompany(trim($input));

        if ($company === null) {
            $this->error("Entreprise introuvable : {$input}");

            return self::FAILURE;
        }

        $report = (string) $this->option('report');
        $from = $this->option('from') !== null && $this->option('from') !== ''
            ? (string) $this->option('from')
            : null;
        $to = $this->option('to') !== null && $this->option('to') !== ''
            ? (string) $this->option('to')
            : null;

        if ((bool) $this->option('sync')) {
            $snapshot = $snapshots->recompute((string) $company->id, $report, $from, $to);
            $this->info(sprintf(
                'Snapshot %s v%d (période %s → %s) — refreshed_at %s.',
                $snapshot->report,
                $snapshot->version,
                $snapshot->period_from->toDateString(),
                $snapshot->period_to->toDateString(),
                $snapshot->refreshed_at->toIso8601String(),
            ));

            return self::SUCCESS;
        }

        RecomputeAccountingReportingSnapshotJob::dispatch((string) $company->id, $report, $from, $to);
        $this->info("Recompute du snapshot {$report} mis en file (job tenant-scoped).");

        return self::SUCCESS;
    }

    private function resolveCompany(string $input): ?Company
    {
        if (Str::isUuid($input)) {
            /** @var Company|null $byId */
            $byId = Company::query()->where('id', $input)->first();

            if ($byId instanceof Company) {
                return $byId;
            }
        }

        /** @var Company|null $bySlug */
        $bySlug = Company::query()->where('slug', $input)->first();

        return $bySlug;
    }
}
