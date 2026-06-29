<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ferme automatiquement les sessions GPS qui sont restées ouvertes
 * trop longtemps (exemple : app tuée, perte réseau, crash).
 *
 * À ajouter dans le scheduler Laravel :
 *   Schedule::command('smart-attendance:auto-close')->hourly();
 */
class AutoCloseGeoSessionsCommand extends Command
{
    protected $signature   = 'smart-attendance:auto-close
                              {--hours=14 : Fermer les sessions ouvertes depuis plus de N heures}
                              {--dry-run  : Afficher sans fermer}';

    protected $description = 'Ferme automatiquement les sessions GPS restées ouvertes (app tuée, crash réseau).';

    public function handle(): int
    {
        $maxHours = (int) $this->option('hours');
        $dryRun   = (bool) $this->option('dry-run');
        $cutoff   = Carbon::now()->subHours($maxHours);

        $sessions = GeoAttendanceSession::query()
            ->whereNull('ended_at')
            ->whereIn('status', [
                GeoAttendanceSession::STATUS_DETECTED,
                GeoAttendanceSession::STATUS_PENDING_VALIDATION,
            ])
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('Aucune session GPS à fermer.');
            return self::SUCCESS;
        }

        $this->info("Sessions GPS à fermer : {$sessions->count()}");

        foreach ($sessions as $session) {
            $endedAt         = Carbon::now();
            $durationSeconds = (int) $session->started_at->diffInSeconds($endedAt);

            $this->line(sprintf(
                '  → Session #%d / Employee #%d / démarrée à %s (durée: %dh)',
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
        }

        if ($dryRun) {
            $this->warn('[dry-run] Aucune modification effectuée.');
        } else {
            $this->info("{$sessions->count()} session(s) fermée(s).");
        }

        return self::SUCCESS;
    }
}
