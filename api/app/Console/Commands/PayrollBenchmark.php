<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Database\Seeders\PayrollBenchmarkSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * payroll:benchmark — protocole de mesure F-12 (#1542/#1594).
 *
 * Seed un jeu DZ réaliste (PayrollBenchmarkSeeder) puis exécute les étapes
 * de clôture mensuelle en mesurant durée, temps/employé et pic mémoire.
 *
 * Usage :
 *   php artisan payroll:benchmark --employees=10000 --step=all
 *   php artisan payroll:benchmark --employees=1000 --step=calculate
 *
 * Étapes : calculate → validate-rh → lock (le run devient verrouillé).
 */
class PayrollBenchmark extends Command
{
    protected $signature = 'payroll:benchmark
        {--employees=10000 : nombre d\'employés à seeder (défaut 10000, max 50000)}
        {--step=all : calculate | validate-rh | lock | all}';

    protected $description = 'Benchmark clôture mensuelle (F-12) : seed DZ + calculate + validate-rh + lock, avec métriques.';

    public function handle(PayrollCalculator $calculator, PayrollClosingService $closing): int
    {
        $employees = (int) max(1, min((int) $this->option('employees'), 50000));
        $step = (string) $this->option('step');

        // ── Seed ───────────────────────────────────────────────────────────
        $this->info("Étape seed : {$employees} employés…");
        $seedStart = microtime(true);
        (new PayrollBenchmarkSeeder)->run($employees);
        $this->line(sprintf('  seed: %.2fs', microtime(true) - $seedStart));

        /** @var Company $company */
        $company = Company::query()->where('slug', 'benchmark-dz-spa')->firstOrFail();
        $run = PayrollRun::query()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->latest('period_start')
            ->firstOrFail();

        $totalStart = microtime(true);

        // ── calculate ──────────────────────────────────────────────────────
        if (in_array($step, ['calculate', 'all'], true)) {
            // Barrière N+1 (#1594) : compteur de requêtes SQL pendant le calcul.
            // Une requête par employé par entité chargée = signature N+1 ;
            // l'ordre de grandeur attendu est < 20 requêtes/employé.
            $queryCount = 0;
            $sqlListener = static function () use (&$queryCount): void {
                $queryCount++;
            };
            DB::listen($sqlListener);

            $start = microtime(true);
            $peakBefore = memory_get_peak_usage(true);
            DB::statement('SET search_path TO public,shared_tenants');
            $run = $calculator->calculateRun($run);
            $duration = microtime(true) - $start;

            // Le listener ne doit être actif que pendant calculate : le retirer
            // dès que calculateRun a fini pour que le compteur s'arrête (avant
            // l'impression des métriques et les étapes validate-rh / lock).
            // Laravel's Dispatcher has no removeListener() — forget() removes
            // every listener for the event (fine here: benchmark-only command).
            DB::connection()->getEventDispatcher()?->forget('illuminate.query');

            $this->table(['métrique', 'valeur'], [
                ['employés', $run->employee_count],
                ['durée calculate', sprintf('%.2fs', $duration)],
                ['temps/employé', sprintf('%.1fms', $duration * 1000 / max(1, $run->employee_count))],
                ['requêtes SQL', (string) $queryCount],
                ['requêtes/employé', sprintf('%.1f', $queryCount / max(1, $run->employee_count))],
                ['pic mémoire', sprintf('%.1f Mo', (memory_get_peak_usage(true) - $peakBefore) / 1048576)],
                ['total_gross', number_format((float) $run->total_gross, 2)],
                ['total_net', number_format((float) $run->total_net, 2)],
            ]);
        }

        // ── validate-rh ────────────────────────────────────────────────────
        if (in_array($step, ['validate-rh', 'all'], true)) {
            $validator = Employee::query()
                ->where('company_id', $company->id)
                ->where('role', '!=', 'employee')
                ->first()
                ?? Employee::query()->where('company_id', $company->id)->firstOrFail();

            $start = microtime(true);
            $run = $closing->validateRh($run, $validator);
            $this->line(sprintf('  validate-rh: %.2fs → statut %s', microtime(true) - $start, $run->status));
        }

        // ── lock ───────────────────────────────────────────────────────────
        if (in_array($step, ['lock', 'all'], true)) {
            $validator = Employee::query()
                ->where('company_id', $company->id)
                ->where('role', '!=', 'employee')
                ->first()
                ?? Employee::query()->where('company_id', $company->id)->firstOrFail();

            $start = microtime(true);
            $run = $closing->lock($run, $validator);
            $this->line(sprintf('  lock: %.2fs → statut %s', microtime(true) - $start, $run->status));
        }

        $this->newLine();
        $this->info(sprintf('Total clôture (%s): %.2fs', $step, microtime(true) - $totalStart));
        $this->warn('F-12 objectif : 10 000 employés < 30 min (1800s) — reporter le run dans docs/payroll/BENCHMARK.md');

        return self::SUCCESS;
    }
}
