<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Spec S-2 (#1662) — Rapport périodique des accès aux données sensibles.
 *
 * Agrège les `audit_logs` de catégorie `hr_data_access` (lectures de
 * bulletins, exports, journal de paie, certificat, fin de contrat, exports
 * bancaires — cf. DataAccessAuditLogger) par action : nombre d'accès,
 * acteurs distincts, dernière occurrence. Sortie table, JSON ou CSV.
 *
 * Usage :
 *   php artisan audit:sensitive-report [--days=30] [--since=YYYY-MM-DD]
 *       [--company=<uuid>] [--json] [--csv=<path>]
 */
class SensitiveAccessReportCommand extends Command
{
    protected $signature = 'audit:sensitive-report
        {--days=30 : Fenetre en jours (defaut 30)}
        {--since= : Date de debut Y-m-d (prioritaire sur --days)}
        {--company= : UUID de la societe (tenant) — sinon toutes}
        {--json : Sortie JSON brute}
        {--csv= : Chemin du fichier CSV a ecrire (optionnel)}';

    protected $description = 'Rapport des acces aux donnees sensibles (paie, exports, bulletins) depuis la fenetre demandee';

    public function handle(): int
    {
        $cutoff = $this->resolveCutoff();
        $companyId = (string) ($this->option('company') ?? '');

        $logs = AuditLog::query()
            ->where('created_at', '>=', $cutoff)
            ->where('metadata->category', 'hr_data_access')
            ->when($companyId !== '', fn ($query) => $query->where('company_id', $companyId))
            ->get(['action', 'company_id', 'user_id', 'created_at']);

        /** @var array<string, array{action: string, company_id: string, accesses: int, actors: array<int, mixed>, last_seen: \Illuminate\Support\Carbon}> $buckets */
        $buckets = [];

        foreach ($logs as $log) {
            $key = $log->action.'|'.$log->company_id;
            $createdAt = $log->created_at ?? now();
            $buckets[$key] ??= [
                'action' => $log->action,
                'company_id' => (string) $log->company_id,
                'accesses' => 0,
                'actors' => [],
                'last_seen' => $createdAt,
            ];
            $buckets[$key]['accesses']++;
            $buckets[$key]['actors'][(string) $log->user_id] = true;
            if ($createdAt->gt($buckets[$key]['last_seen'])) {
                $buckets[$key]['last_seen'] = $createdAt;
            }
        }

        $rows = collect($buckets)
            ->map(fn (array $bucket): array => [
                'action' => $bucket['action'],
                'company_id' => $bucket['company_id'],
                'accesses' => $bucket['accesses'],
                'actors' => count($bucket['actors']),
                'last_seen' => $bucket['last_seen']->toDateTimeString(),
            ])
            ->sortByDesc('accesses')
            ->values()
            ->all();

        $totalAccesses = array_sum(array_map(fn (array $row): int => $row['accesses'], $rows));
        $totalActors = array_sum(array_map(fn (array $row): int => $row['actors'], $rows));

        $this->info(sprintf(
            'Acces aux donnees sensibles depuis %s%s : %d acces (%d acteurs distincts cumules, %d actions).',
            $cutoff->toDateString(),
            $companyId !== '' ? " (societe {$companyId})" : ' (toutes societes)',
            $totalAccesses,
            $totalActors,
            count($rows)
        ));

        if ($rows === []) {
            $this->warn('Aucun acces sensible journalise sur la periode.');

            return self::SUCCESS;
        }

        $csvPath = $this->option('csv');

        if ($this->option('json')) {
            $json = json_encode([
                'since' => $cutoff->toDateString(),
                'company_id' => $companyId !== '' ? $companyId : null,
                'total_accesses' => $totalAccesses,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->line($json === false ? '{}' : $json);

            return self::SUCCESS;
        }

        $this->table(['Action', 'Societe', 'Acces', 'Acteurs', 'Dernier acces'], $rows);

        if (is_string($csvPath) && $csvPath !== '') {
            $this->writeCsv($csvPath, $rows);
            $this->info("Rapport CSV ecrit : {$csvPath}.");
        }

        return self::SUCCESS;
    }

    private function resolveCutoff(): \Illuminate\Support\Carbon
    {
        $since = (string) ($this->option('since') ?? '');

        if ($since !== '') {
            return \Illuminate\Support\Carbon::parse($since)->startOfDay();
        }

        return now()->subDays(max(1, (int) $this->option('days')));
    }

    /**
     * @param  array<int, array{action: string, company_id: string, accesses: int, actors: int, last_seen: string}>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Impossible d'ouvrir {$path} en ecriture.");

            return;
        }

        fputcsv($handle, ['action', 'company_id', 'accesses', 'actors', 'last_seen']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
