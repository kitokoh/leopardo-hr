<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Plan 64 — Clôture automatique des pointages oubliés.
 *
 * Finds all attendance logs with check_in but no check_out older than 12 hours,
 * creates an automatic check_out with source='auto_close', and logs the operation.
 *
 * Schedule: hourly via Kernel.php
 */
class AutoCloseAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-close
                                {--threshold=12 : Hours without check-out before auto-closing}
                                {--dry-run : Preview without writing}';

    protected $description = 'Auto-close attendance logs that have no check-out after N hours (Plan 64)';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subHours($threshold);

        $this->info("Auto-close: looking for check-ins without check-out before {$cutoff->toDateTimeString()}");

        $query = AttendanceLog::query()
            ->withoutGlobalScopes()
            ->whereNotNull('check_in')
            ->where(function ($q) {
                $q->whereNull('check_out')
                  ->orWhere('check_out', '');
            })
            ->where('check_in', '<=', $cutoff);

        $count = $query->count();
        $this->info("Found {$count} unclosed attendance log(s).");

        if ($dryRun) {
            $this->warn('Dry-run mode: no changes written.');

            return self::SUCCESS;
        }

        $closed = 0;

        $query->each(function (AttendanceLog $log) use (&$closed): void {
            // Auto check-out: set to check_in + 8h (reasonable workday cap) or now, whichever is earlier
            $autoCheckOut = $log->check_in->copy()->addHours(8);
            if ($autoCheckOut->isFuture()) {
                $autoCheckOut = Carbon::now();
            }

            // Calculate hours worked
            $hoursWorked = round($log->check_in->diffInMinutes($autoCheckOut) / 60, 2);

            $log->update([
                'check_out' => $autoCheckOut,
                'hours_worked' => $hoursWorked,
                'punch_note' => 'Auto-clôture système (aucun checkout détecté)',
                'correction_note' => 'auto_close',
                'status' => 'auto_closed',
            ]);

            $closed++;

            Log::info("attendance:auto-close — closed log #{$log->id} for employee #{$log->employee_id} "
                . "(check_in: {$log->check_in}, auto check_out: {$autoCheckOut})");
        });

        $this->info("Auto-closed {$closed} attendance log(s).");

        Log::info("attendance:auto-close — run complete: {$closed} logs auto-closed.");

        return self::SUCCESS;
    }
}
