<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Console\Commands;

use App\Modules\Delivery\Application\Jobs\ExportDeliveryReportJob;
use App\Modules\Delivery\Domain\Models\Delivery;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Export asynchrone des rapports livraison (BC-26-D07, issue #6295).
 *
 *   php artisan delivery:export-report {company} {--from=} {--to=}
 * Snapshot JSON déterministe du read model, écrit sur le disque local
 * (`storage/app/delivery_reports/...`) par le job — retry borné, DLQ.
 * Le même run est rejouable sans doublon (même runKey → même fichier).
 */
final class ExportDeliveryReportCommand extends Command
{
    protected $signature = 'delivery:export-report {company : company_id (uuid)} {--from= : date début Y-m-d} {--to= : date fin Y-m-d}';

    protected $description = 'Planifie l\'export JSON du rapport livraison (job asynchrone)';

    public function handle(): int
    {
        $companyId = (string) $this->argument('company');

        $exists = Delivery::query()
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            $this->error("Aucune livraison pour ce tenant ({$companyId}).");

            return self::INVALID;
        }

        $from = (string) ($this->option('from') ?? now()->subDays(30)->format('Y-m-d'));
        $to = (string) ($this->option('to') ?? now()->format('Y-m-d'));

        ExportDeliveryReportJob::dispatch($companyId, $from, $to, (string) Str::uuid());

        $this->info("Export rapport planifié ({$from} → {$to}).");

        return self::SUCCESS;
    }
}
