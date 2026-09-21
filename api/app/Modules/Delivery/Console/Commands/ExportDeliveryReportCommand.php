<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Console\Commands;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Infrastructure\Jobs\ExportDeliveryReportJob;
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
    protected $signature = 'delivery:export-report {company : company_id (uuid)} {--from= : date debut Y-m-d} {--to= : date fin Y-m-d}';

    protected $description = 'Planifie l\'export JSON du rapport livraison (job asynchrone)';

    public function handle(): int
    {
        $companyRaw = $this->argument('company');
        // #8004 — narrowing `is_string()` redundant (PHPStan level 8).
        $companyId = (string) $companyRaw;

        $exists = Delivery::query()
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            $this->error(__('delivery.commands.no_deliveries_for_tenant', ['company' => $companyId]));

            return self::INVALID;
        }

        $fromRaw = $this->option('from');
        $toRaw = $this->option('to');
        // #8004 — narrowing `is_string()` redundant (PHPStan level 8) : le
        // cast explicite conserve exactement la meme semantique (defaut si vide).
        $fromValue = (string) $fromRaw;
        $toValue = (string) $toRaw;
        $from = $fromValue !== '' ? $fromValue : now()->subDays(30)->format('Y-m-d');
        $to = $toValue !== '' ? $toValue : now()->format('Y-m-d');

        ExportDeliveryReportJob::dispatch($companyId, $from, $to, (string) Str::uuid());

        $this->info(__('delivery.commands.export_report_planned', ['from' => $from, 'to' => $to]));

        return self::SUCCESS;
    }
}
