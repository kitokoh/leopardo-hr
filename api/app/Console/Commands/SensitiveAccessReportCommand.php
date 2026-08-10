<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Spec S-2 (#1662) — Journalisation des accès aux données sensibles.
 *
 * Rapport périodique des lectures sensibles tracées par DataAccessAuditLogger
 * (catégorie `sensitive_data_access`) : comptage par action et par tenant sur
 * une fenêtre donnée, plus un échantillon des dernières traces.
 *
 * Usage :
 *   php artisan audit:sensitive-report [--days=7] [--company=<uuid>]
 */
class SensitiveAccessReportCommand extends Command
{
    protected $signature = 'audit:sensitive-report
        {--days=7 : Fenetre de rapport en jours (defaut 7)}
        {--company= : UUID de la societe (tenant) cible — sinon toutes}';

    protected $description = 'Rapport des accès aux données sensibles (bulletins, exports, journal, certificat, fin de contrat)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $companyId = (string) ($this->option('company') ?? '');
        $since = now()->subDays($days);

        $query = AuditLog::query()
            ->where('created_at', '>=', $since)
            ->where('metadata->category', 'sensitive_data_access');

        if ($companyId !== '') {
            $query->where('company_id', $companyId);
        }

        /** @var Collection<int, array{action: string, company_id: string, total: int}> $rows */
        $rows = $query
            ->selectRaw('action, company_id, COUNT(*) as total')
            ->groupBy('action', 'company_id')
            ->orderByDesc('total')
            ->get()
            ->map(function (AuditLog $row): array {
                return [
                    'action' => $row->action,
                    'company_id' => $row->company_id,
                    'total' => (int) $row->getAttribute('total'),
                ];
            });

        $total = (int) $rows->sum('total');

        $this->info(sprintf(
            'Accès sensibles tracés depuis %s (%d jours) : %d%s',
            $since->toDateString(),
            $days,
            $total,
            $companyId !== '' ? " (société {$companyId})" : ' (toutes sociétés)'
        ));

        if ($rows->isEmpty()) {
            $this->warn('Aucune trace de lecture sensible sur la période.');

            return self::SUCCESS;
        }

        $this->table(
            ['Action', 'Société', 'Accès'],
            $rows->map(fn (array $row): array => [$row['action'], $row['company_id'], $row['total']])->all()
        );

        // Échantillon des dernières traces (preuves consultables sans écrire en base).
        $recent = AuditLog::query()
            ->where('created_at', '>=', $since)
            ->where('metadata->category', 'sensitive_data_access')
            ->when($companyId !== '', fn (Builder $q): Builder => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->limit(10)
            ->get(['company_id', 'user_id', 'action', 'created_at']);

        if ($recent->isNotEmpty()) {
            $this->line('Dernières traces :');
            $this->table(
                ['Date', 'Société', 'Utilisateur', 'Action'],
                $recent->map(fn (AuditLog $log): array => [
                    $log->created_at?->toDateTimeString() ?? '—',
                    $log->company_id,
                    (string) ($log->user_id ?? '—'),
                    $log->action,
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
