<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AutoCloseAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-close
                                {--threshold=12 : Fallback hours without check-out before auto-closing}
                                {--dry-run : Preview without writing}';

    protected $description = 'Auto-close attendance logs that have no check-out after the tenant policy delay';

    public function handle(): int
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

            return self::SUCCESS;
        }

        $closed = 0;

        $candidates->each(function (AttendanceLog $log) use (&$closed, $fallbackThreshold): void {
            $company = $this->resolveCompany($log);
            $policy = $this->resolvePolicy($company, $fallbackThreshold);

            if (! $policy['enabled']) {
                return;
            }

            if ($log->check_in->greaterThan(Carbon::now('UTC')->subHours($policy['threshold_hours']))) {
                return;
            }

            $autoCheckOut = $log->check_in
                ->copy()
                ->addHours($policy['workday_hours'])
                ->addMinutes($policy['overtime_margin_minutes']);

            if ($autoCheckOut->isFuture()) {
                $autoCheckOut = Carbon::now('UTC');
            }

            $hoursWorked = round($log->check_in->diffInMinutes($autoCheckOut) / 60, 2);
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
                'check_in' => $log->check_in?->toIso8601String(),
                'auto_check_out' => $autoCheckOut->toIso8601String(),
            ]);
        });

        $this->info("Auto-closed {$closed} attendance log(s).");

        Log::info('attendance:auto-close run complete', ['closed' => $closed]);

        return self::SUCCESS;
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
        $metadata = $company?->metadata ?? [];
        $policy = is_array($metadata) ? ($metadata['attendance_auto_close'] ?? []) : [];
        $policy = is_array($policy) ? $policy : [];

        return [
            'enabled' => (bool) ($policy['enabled'] ?? true),
            'threshold_hours' => max(1, (int) ($policy['threshold_hours'] ?? $fallbackThreshold)),
            'workday_hours' => max(1, (int) ($policy['workday_hours'] ?? 8)),
            'overtime_margin_minutes' => max(0, (int) ($policy['overtime_margin_minutes'] ?? 30)),
        ];
    }
}
