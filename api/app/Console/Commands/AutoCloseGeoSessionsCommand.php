<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ferme automatiquement les sessions GPS qui sont restées ouvertes
 * trop longtemps (exemple : app tuée, perte réseau, crash).
 *
 * Multi-tenant : la commande itère TOUTES les companies (chaque tenant dans
 * son schéma via TenantManager::withinTenant) — sans itération, le scheduler
 * ne fermait que les sessions du schéma par défaut (#4797).
 *
 * À ajouter dans le scheduler Laravel :
 *   Schedule::command('smart-attendance:auto-close')->hourly();
 */
class AutoCloseGeoSessionsCommand extends Command
{
    protected $signature = 'smart-attendance:auto-close
                           {--hours=14 : Fermer les sessions ouvertes depuis plus de N heures}
                           {--company= : ID de la société (tenant) cible — sinon tous les tenants actifs}
                           {--dry-run  : Afficher sans fermer}';

    protected $description = 'Ferme automatiquement les sessions GPS restées ouvertes (app tuée, crash réseau) — itère tous les tenants.';

    public function handle(TenantManager $tenantManager): int
    {
        $maxHours = (int) $this->option('hours');
        $dryRun   = (bool) $this->option('dry-run');
        $cutoff   = Carbon::now()->subHours($maxHours);

        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucune société active — rien à fermer.');

            return self::SUCCESS;
        }

        $totalClosed = 0;
        $totalSkipped = 0;

        foreach ($companies as $company) {
            [$closed, $skipped] = $tenantManager->withinTenant(
                $company,
                fn (): array => $this->closeSessionsForTenant($company, $cutoff, $dryRun),
            );

            $totalClosed += $closed;
            $totalSkipped += $skipped;
        }

        if ($totalClosed === 0 && $totalSkipped === 0) {
            $this->info("Aucune session GPS à fermer ({$companies->count()} tenant(s) parcouru(s)).");
        } else {
            $this->info("{$companies->count()} tenant(s) parcouru(s) — {$totalClosed} session(s) fermée(s), {$totalSkipped} en dry-run.");
        }

        if ($dryRun) {
            $this->warn('[dry-run] Aucune modification effectuée.');
        }

        return self::SUCCESS;
    }

    /**
     * Ferme les sessions GPS expirées dans le schéma du tenant courant.
     *
     * @return array{0: int, 1: int} [fermées, ignorées (dry-run)]
     */
    private function closeSessionsForTenant(Company $company, Carbon $cutoff, bool $dryRun): array
    {
        $sessions = GeoAttendanceSession::query()
            ->whereNull('ended_at')
            ->whereIn('status', [
                GeoAttendanceSession::STATUS_DETECTED,
                GeoAttendanceSession::STATUS_PENDING_VALIDATION,
            ])
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($sessions->isEmpty()) {
            return [0, 0];
        }

        $this->line(sprintf(
            '  [%s] Sessions GPS à fermer : %d',
            $company->slug,
            $sessions->count(),
        ));

        $handled = 0;

        foreach ($sessions as $session) {
            $endedAt         = Carbon::now();
            $durationSeconds = (int) $session->started_at->diffInSeconds($endedAt);

            $this->line(sprintf(
                '    → Session #%d / Employee #%d / démarrée à %s (durée: %dh)',
                $session->id,
                $session->employee_id,
                $session->started_at->toDateTimeString(),
                intdiv($durationSeconds, 3600),
            ));

            if (! $dryRun) {
                $session->update([
                    'ended_at'         => $endedAt,
                    'duration_seconds' => $durationSeconds,
                    'status'           => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
                    'validation_note'  => 'Fermée automatiquement (timeout système).',
                ]);
            }

            $handled++;
        }

        return $dryRun ? [0, $handled] : [$handled, 0];
    }
}
