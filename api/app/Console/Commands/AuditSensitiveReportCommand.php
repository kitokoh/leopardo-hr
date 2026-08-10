<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * S-2 (#1662) — Rapport périodique des accès aux données sensibles.
 *
 * Agrège `audit_logs` (action `sensitive_data_access`, catégorie
 * `sensitive_data_access`) : volume par ressource et par utilisateur sur la
 * fenêtre demandée. Aide au contrôle d'accès (qui a lu quoi, quand).
 *
 * Usage :
 *   php artisan audit:sensitive-report [--days=30] [--resource=pay_slip.download] [--json]
 */
class AuditSensitiveReportCommand extends Command
{
    protected $signature = 'audit:sensitive-report
        {--days=30 : Fenêtre en jours (défaut 30)}
        {--resource= : Filtre sur une ressource sensible (ex. pay_slip.download)}
        {--json : Sortie JSON (pour automatisation)}';

    protected $description = 'Rapport des accès aux données sensibles (qui/quoi/quand) depuis audit_logs';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);
        $resource = $this->option('resource');

        $query = AuditLog::query()
            ->where('action', 'sensitive_data_access')
            ->where('created_at', '>=', $since);

        if (is_string($resource) && $resource !== '') {
            $query->whereRaw('metadata->>\'resource\' = ?', [$resource]);
        }

        $byResource = (clone $query)
            ->selectRaw("metadata->>'resource' as resource, COUNT(*) as total")
            ->groupBy(DB::raw("metadata->>'resource'"))
            ->orderByDesc('total')
            ->get()
            ->map(fn (AuditLog $row): array => [
                'resource' => (string) $row->getAttribute('resource'),
                'total' => (int) $row->getAttribute('total'),
            ]);

        $byUser = (clone $query)
            ->selectRaw('COALESCE(user_id::text, \'system\') as user, COUNT(*) as total')
            ->groupBy(DB::raw('COALESCE(user_id::text, \'system\')'))
            ->orderByDesc('total')
            ->get()
            ->map(fn (AuditLog $row): array => [
                'user' => (string) $row->getAttribute('user'),
                'total' => (int) $row->getAttribute('total'),
            ]);

        $total = (clone $query)->count();

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'since' => $since->toDateTimeString(),
                'days' => $days,
                'total' => $total,
                'by_resource' => $byResource,
                'by_user' => $byUser,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Accès sensibles depuis %s (%d jour(s)) — total : %d',
            $since->toDateString(),
            $days,
            $total
        ));

        $this->newLine();
        $this->table(['Ressource', 'Accès'], $byResource->map(fn (array $row): array => [$row['resource'], $row['total']])->all());
        $this->newLine();
        $this->table(['Utilisateur', 'Accès'], $byUser->map(fn (array $row): array => [$row['user'], $row['total']])->all());

        return self::SUCCESS;
    }
}
