<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitor slow queries from PostgreSQL pg_stat_statements.
 *
 * This command queries the pg_stat_statements extension (if available)
 * and logs any query whose mean_exec_time exceeds the configured threshold.
 *
 * Schedule: every 15 minutes in production.
 */
class MonitorSlowQueries extends Command
{
    protected $signature = 'monitor:slow-queries
        {--threshold=500 : Seuil en millisecondes (par defaut 500ms)}
        {--limit=20 : Nombre max de queries a afficher}';

    protected $description = 'Liste les requetes SQL lentes depuis pg_stat_statements';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $limit = (int) $this->option('limit');

        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Cette commande ne fonctionne qu\'avec PostgreSQL.');

            return self::SUCCESS;
        }

        try {
            $slowQueries = DB::select(
                "SELECT query, calls, mean_exec_time, total_exec_time, rows
                 FROM pg_stat_statements
                 WHERE mean_exec_time > ?
                 ORDER BY mean_exec_time DESC
                 LIMIT ?",
                [$threshold, $limit]
            );
        } catch (\Throwable $e) {
            $this->warn('pg_stat_statements non disponible : '.$e->getMessage());
            Log::channel('structured')->warning('monitor:slow-queries pg_stat_statements unavailable', [
                'error' => $e->getMessage(),
            ]);

            return self::SUCCESS;
        }

        if (empty($slowQueries)) {
            $this->info("Aucune requete lente (seuil: {$threshold}ms).");

            return self::SUCCESS;
        }

        $this->info(count($slowQueries)." requete(s) lente(s) detectee(s) (seuil: {$threshold}ms):");

        $rows = [];
        foreach ($slowQueries as $query) {
            $truncatedQuery = mb_substr($query->query, 0, 80);
            $rows[] = [
                $truncatedQuery,
                $query->calls,
                round($query->mean_exec_time, 2).'ms',
                round($query->total_exec_time / 1000, 2).'s',
                $query->rows,
            ];

            Log::channel('structured')->warning('Slow query detected', [
                'query' => mb_substr($query->query, 0, 200),
                'calls' => $query->calls,
                'mean_exec_time_ms' => round($query->mean_exec_time, 2),
                'total_exec_time_s' => round($query->total_exec_time / 1000, 2),
                'rows' => $query->rows,
            ]);
        }

        $this->table(
            ['Query (tronquee)', 'Appels', 'Moy', 'Total', 'Lignes'],
            $rows
        );

        return self::SUCCESS;
    }
}
