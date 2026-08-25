<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Jobs\DispatchCommunicationJob;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fermeture automatique unique (ADR-0016 Phase 4, #5355) :
 *  1. les pointages sans check-out (politique tenant, logique historique) ;
 *  2. les sessions GPS restées ouvertes trop longtemps (fusion de
 *     l'ancienne commande `smart-attendance:auto-close`, #4797) — itère
 *     TOUS les tenants actifs (chaque tenant dans son schéma via
 *     TenantManager::withinTenant).
 */
class AutoCloseAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-close
                                {--threshold=12 : Fallback hours without check-out before auto-closing}
                                {--hours=14 : Close geo sessions open longer than N hours}
                                {--company= : Company (tenant) to target for geo sessions — otherwise all active tenants}
                                {--dry-run : Preview without writing}
                                {--logs-only : Only close forgotten attendance logs (skip geo sessions)}
                                {--sessions-only : Only close stale geo sessions (skip attendance logs)}';

    protected $description = 'Auto-close forgotten attendance logs and stale GPS sessions (unique auto-close, ADR-0016)';

    public function handle(TenantManager $tenantManager): int
    {
        $logsOnly = (bool) $this->option('logs-only');
        $sessionsOnly = (bool) $this->option('sessions-only');

        if (! $sessionsOnly) {
            $this->closeForgottenAttendanceLogs();
        }

        if (! $logsOnly) {
            $this->closeStaleGeoSessions($tenantManager);
        }

        return self::SUCCESS;
    }

    /**
     * Ferme les pointages sans check-out après le délai de la politique tenant.
     */
    private function closeForgottenAttendanceLogs(): void
    {
        $fallbackThreshold = max(1, (int) $this->option('threshold'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now('UTC')->subHours($fallbackThreshold);

        $this->info("Auto-close: looking for check-ins without check-out before {$cutoff->toDateTimeString()} UTC");

        $candidates = AttendanceLog::query()
            ->withoutGlobalScopes()
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->where('check_in', '<=', $cutoff)
            ->get();

        $this->info("Found {$candidates->count()} unclosed attendance log candidate(s).");

        if ($dryRun) {
            $this->warn('Dry-run mode: no changes written.');

            return;
        }

        $closed = 0;

        $candidates->each(function (AttendanceLog $log) use (&$closed, $fallbackThreshold): void {
            $company = $this->resolveCompany($log);
            $policy = $this->resolvePolicy($company, $fallbackThreshold);

            if (! $policy['enabled']) {
                return;
            }

            // Audit #1702 : le schéma autorise check_in nullable (le query
            // filtre whereNotNull) — garde explicite pour PHPStan niveau 8.
            $checkIn = $log->check_in;

            if ($checkIn === null) {
                return;
            }

            if ($checkIn->greaterThan(Carbon::now('UTC')->subHours($policy['threshold_hours']))) {
                return;
            }

            $autoCheckOut = $checkIn
                ->copy()
                ->addHours($policy['workday_hours'])
                ->addMinutes($policy['overtime_margin_minutes']);

            if ($autoCheckOut->isFuture()) {
                $autoCheckOut = Carbon::now('UTC');
            }

            $hoursWorked = round($checkIn->diffInMinutes($autoCheckOut) / 60, 2);
            $meta = array_merge($log->punch_meta ?? [], [
                'auto_close' => [
                    'closed_at' => Carbon::now('UTC')->toIso8601String(),
                    'policy' => $policy,
                    'correction_window' => true,
                ],
            ]);

            $log->update([
                'check_out' => $autoCheckOut,
                'hours_worked' => $hoursWorked,
                'punch_note' => 'Auto-cloture systeme (aucun checkout detecte)',
                'correction_note' => 'auto_close',
                'status' => $log->status === 'incomplete' ? 'ontime' : $log->status,
                'punch_meta' => $meta,
            ]);

            $closed++;

            Log::info('attendance:auto-close closed forgotten attendance log', [
                'attendance_log_id' => $log->id,
                'employee_id' => $log->employee_id,
                'company_id' => $log->company_id,
                'check_in' => $checkIn->toIso8601String(),
                'auto_check_out' => $autoCheckOut->toIso8601String(),
            ]);

            $this->notifyAutoClose($log, $autoCheckOut);
        });

        $this->info("Auto-closed {$closed} attendance log(s).");

        Log::info('attendance:auto-close run complete', ['closed' => $closed]);
    }

    /**
     * Ferme les sessions GPS restées ouvertes trop longtemps (fusion #5355 —
     * ancienne commande smart-attendance:auto-close, #4797).
     *
     * Multi-tenant : itère TOUTES les companies actives (chaque tenant dans
     * son schéma via TenantManager::withinTenant) — sans itération, seules les
     * sessions du schéma par défaut seraient fermées.
     */
    private function closeStaleGeoSessions(TenantManager $tenantManager): void
    {
        $maxHours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subHours($maxHours);

        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucune société active — rien à fermer.');

            return;
        }

        $totalClosed = 0;
        $totalSkipped = 0;

        foreach ($companies as $company) {
            [$closed, $skipped] = $tenantManager->withinTenant(
                $company,
                fn (): array => $this->closeGeoSessionsForTenant($company, $cutoff, $dryRun),
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
    }

    /**
     * Ferme les sessions GPS expirées dans le schéma du tenant courant.
     *
     * @return array{0: int, 1: int} [fermées, ignorées (dry-run)]
     */
    private function closeGeoSessionsForTenant(Company $company, Carbon $cutoff, bool $dryRun): array
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
            $endedAt = Carbon::now();
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
                    'ended_at' => $endedAt,
                    'duration_seconds' => $durationSeconds,
                    'status' => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
                    'validation_note' => 'Fermée automatiquement (timeout système).',
                ]);
            }

            $handled++;
        }

        return $dryRun ? [0, $handled] : [$handled, 0];
    }

    /**
     * Notify the employee that their forgotten check-out was auto-closed by
     * the system, so they know a correction request is available if the
     * computed hours/overtime are wrong (PA2-ATT-007).
     */
    private function notifyAutoClose(AttendanceLog $log, Carbon $autoCheckOut): void
    {
        if (! $log->employee_id || ! $log->company_id) {
            return;
        }

        DispatchCommunicationJob::dispatch(
            employeeId: $log->employee_id,
            companyId: (string) $log->company_id,
            templateKey: 'attendance_auto_closed',
            context: [
                'attendance_log_id' => $log->id,
                'date' => $log->date?->toDateString(),
                'auto_check_out' => $autoCheckOut->toIso8601String(),
                'hours_worked' => $log->hours_worked,
            ],
        );
    }

    private function resolveCompany(AttendanceLog $log): ?Company
    {
        if (! $log->company_id) {
            return null;
        }

        return Company::query()->find($log->company_id);
    }

    /**
     * @return array{enabled: bool, threshold_hours: int, workday_hours: int, overtime_margin_minutes: int}
     */
    private function resolvePolicy(?Company $company, int $fallbackThreshold): array
    {
        // `??` gère déjà le cas $company null — le nullsafe est redondant (niveau 8).
        $metadata = $company->metadata ?? [];
        $policy = $metadata['attendance_auto_close'] ?? [];
        $policy = is_array($policy) ? $policy : [];

        return [
            'enabled' => (bool) ($policy['enabled'] ?? true),
            'threshold_hours' => max(1, (int) ($policy['threshold_hours'] ?? $fallbackThreshold)),
            'workday_hours' => max(1, (int) ($policy['workday_hours'] ?? 8)),
            'overtime_margin_minutes' => max(0, (int) ($policy['overtime_margin_minutes'] ?? 30)),
        ];
    }
}
