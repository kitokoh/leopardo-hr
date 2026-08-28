<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Infrastructure\Jobs\SendWeeklyManagerDigestJob;
use Illuminate\Console\Command;

/**
 * Dispatch du digest hebdomadaire manager — Issue #5695.
 *
 * Itère les entreprises ACTIVES et dispatch un `SendWeeklyManagerDigestJob`
 * par tenant (le job restaure lui-même le contexte tenant via
 * `EnsureTenantContext`). Programmée chaque lundi à 07:00
 * (routes/console.php).
 */
class SendWeeklyManagerDigestCommand extends Command
{
    protected $signature = 'manager:weekly-digest {--week= : Date de début de semaine (Y-m-d), défaut : lundi courant}';

    protected $description = 'Envoie le digest hebdomadaire email à chaque manager des entreprises actives';

    public function handle(): int
    {
        $weekStart = $this->option('week');

        $companyIds = Company::query()
            ->where('status', 'active')
            ->pluck('id');

        $count = 0;
        foreach ($companyIds as $companyId) {
            SendWeeklyManagerDigestJob::dispatch((string) $companyId, is_string($weekStart) ? $weekStart : null);
            $count++;
        }

        $this->info("Digest hebdomadaire dispatché pour {$count} entreprise(s) active(s).");

        return self::SUCCESS;
    }
}
