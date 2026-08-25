<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Exports\DsnExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Export DSN (Déclaration Sociale Nominative) d'un run de paie validé —
 * #5438 (Pack FR lot 1, gap E1).
 *
 * Usage :
 *   php artisan payroll:export-dsn {run} [--disk=local]
 *
 * Écrit `dsn/{run}_{période}.xml` sur le disque choisi et affiche le chemin.
 */
class ExportDsnCommand extends Command
{
    protected $signature = 'payroll:export-dsn {run : ID du run de paie} {--disk=local : Disque de destination}';

    protected $description = 'Exporte la DSN (S21.G00) d un run de paie valide';

    public function handle(DsnExportService $dsn): int
    {
        $run = PayrollRun::query()->find((int) $this->argument('run'));

        if ($run === null) {
            $this->error(sprintf('Run de paie introuvable : %s', (string) $this->argument('run')));

            return self::FAILURE;
        }

        if ($run->status !== 'validated') {
            $this->error(sprintf('Le run #%d n est pas validé (statut : %s) — la DSN exige un run validé.', $run->id, $run->status));

            return self::FAILURE;
        }

        $xml = $dsn->build($run);
        $period = $run->period_start->format('Y-m');
        $path = "dsn/run_{$run->id}_{$period}.xml";

        Storage::disk((string) $this->option('disk'))->put($path, $xml);

        $this->info(sprintf('DSN exportée : %s (%d octets, %d bulletin(s)).', $path, strlen($xml), $run->employee_count));

        return self::SUCCESS;
    }
}
