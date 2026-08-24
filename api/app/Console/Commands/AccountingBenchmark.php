<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Database\Seeders\AccountingBenchmarkSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * accounting:benchmark — protocole de mesure de charge du module Comptabilité
 * (issue #5275). Calqué sur payroll:benchmark (F-12).
 *
 * Seed une entreprise dédiée avec N documents (lignes + paiements) puis mesure :
 *   - liste par statut (avec eager loading — barrière N+1 : nombre de requêtes
 *     indépendant du nombre de documents) ;
 *   - requête « relances » (statuts émis + due_date ≤ seuil) ;
 *   - agrégation par mois d'émission (type journal).
 *
 * Usage :
 *   php artisan accounting:benchmark --documents=10000
 *   php artisan accounting:benchmark --documents=1000
 */
class AccountingBenchmark extends Command
{
    protected $signature = 'accounting:benchmark
        {--documents=10000 : nombre de documents à seeder (défaut 10000, max 50000)}';

    protected $description = 'Benchmark charge Comptabilité (F-12, issue #5275) : seed + listes + agrégations avec métriques.';

    public function handle(): int
    {
        $documents = (int) max(100, min((int) $this->option('documents'), 50000));

        // ── Seed ───────────────────────────────────────────────────────────
        $this->info("Étape seed : {$documents} documents…");
        $seedStart = microtime(true);
        $slug = (new AccountingBenchmarkSeeder)->run($documents);
        $seedSeconds = microtime(true) - $seedStart;
        $this->line(sprintf('  seed: %.2fs', $seedSeconds));

        /** @var Company $company */
        $company = Company::query()->where('slug', $slug)->firstOrFail();

        // ── Mesures ────────────────────────────────────────────────────────
        $results = [];

        // 1) Liste par statut, eager loading (contact + lignes + paiements) —
        //    barrière N+1 : le compteur de requêtes ne doit pas dépendre de N.
        $statusStart = microtime(true);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $list = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->where('status', 'sent')
            ->with(['contact', 'lines', 'payments'])
            ->paginate(50);
        $statusSeconds = microtime(true) - $statusStart;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $results['liste par statut (50/page, eager)'] = sprintf(
            '%.2fs — %d requêtes (N+1 barrière: ≤ 5) — %d pages',
            $statusSeconds,
            $queryCount,
            $list->lastPage(),
        );

        // 2) Requête « relances » : émis non soldés, échéance passée de 7 j.
        $reminderStart = microtime(true);
        $reminderCount = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->subDays(7)->toDateString())
            ->whereColumn('paid_amount', '<', 'total_ttc')
            ->count();
        $results['requête relances (J+7)'] = sprintf(
            '%.2fs — %d documents éligibles',
            microtime(true) - $reminderStart,
            $reminderCount,
        );

        // 3) Agrégation par mois d'émission (type journal/rapport).
        $aggregateStart = microtime(true);
        $aggregates = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])
            ->selectRaw("to_char(issue_date, 'YYYY-MM') as month, COUNT(*) as n, SUM(total_ttc) as ttc")
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $results['agrégation par mois (journal)'] = sprintf(
            '%.2fs — %d mois couverts',
            microtime(true) - $aggregateStart,
            $aggregates->count(),
        );

        // ── Rapport ────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('Métriques (protocole F-12, issue #5275) — '.$documents.' documents :');
        $this->table(['Mesure', 'Résultat'], collect($results)->map(
            static fn (string $value, string $key): array => [$key, $value]
        )->all());

        $this->line('Cible : recherche < 200 ms sur 10 000 documents (DoD #5275).');
        $this->line('Rapport complet : docs/accounting/BENCHMARK.md');

        return self::SUCCESS;
    }
}
