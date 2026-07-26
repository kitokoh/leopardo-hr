<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * PA2-QA-006 — Redis/jobs observability.
 *
 * Persists the last start/finish outcome of every scheduled Artisan command
 * (`Schedule::command(...)` entries in `routes/console.php` / `bootstrap/
 * app.php`) into `scheduled_task_runs`, one row per task, so the platform
 * admin "System" screen can display *when* each background task last ran
 * and whether it succeeded — the "last run + alerts visible" part of the
 * acceptance criteria that pure queue-depth metrics cannot answer.
 *
 * Registered via `Event::listen()` in `AppServiceProvider::boot()` (not
 * `EventServiceProvider::$listen`) because these are framework console
 * events, not domain events, and only fire while `schedule:run` executes
 * (i.e. from the cron entry point), never from a web request.
 *
 * Deliberately best-effort: a failure to *record* an outcome must never
 * abort the actual scheduled task or crash `schedule:run` for the other
 * tasks queued in the same run.
 */
class ScheduledTaskRunRecorder
{
    public function onStarting(ScheduledTaskStarting $event): void
    {
        $name = $this->taskName($event->task);

        $this->upsert($name, [
            'started_at' => now(),
            'finished_at' => null,
            'status' => ScheduledTaskRun::STATUS_UNKNOWN,
        ]);
    }

    public function onFinished(ScheduledTaskFinished $event): void
    {
        $name = $this->taskName($event->task);
        $exitCode = $event->task->exitCode ?? null;
        $success = $exitCode === 0;

        $this->upsert($name, [
            'finished_at' => now(),
            'runtime_ms' => (int) round($event->runtime * 1000),
            'status' => $success ? ScheduledTaskRun::STATUS_SUCCESS : ScheduledTaskRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'output' => $this->truncatedOutput($event->task),
        ]);
    }

    public function onFailed(ScheduledTaskFailed $event): void
    {
        $name = $this->taskName($event->task);

        $this->upsert($name, [
            'finished_at' => now(),
            'status' => ScheduledTaskRun::STATUS_FAILED,
            'exit_code' => $event->task->exitCode ?? null,
            'output' => Str::limit((string) $event->exception->getMessage(), 2000),
        ]);
    }

    private function taskName(object $task): string
    {
        // `getSummaryForDisplay()` returns a human label when `->name(...)`
        // was set, otherwise the raw command line — either way it uniquely
        // identifies the scheduled entry across restarts.
        if (method_exists($task, 'getSummaryForDisplay')) {
            return (string) $task->getSummaryForDisplay();
        }

        return (string) ($task->command ?? $task->description ?? 'unknown');
    }

    /**
     * Reads the scheduled task's redirected output file (`->output` is the
     * filesystem path Laravel writes stdout/stderr to, defaulting to
     * `/dev/null` when no `->sendOutputTo()`/`->appendOutputTo()` was
     * configured for that entry — in which case there is nothing to read).
     */
    private function truncatedOutput(object $task): ?string
    {
        $path = $task->output ?? null;

        if (! is_string($path) || $path === '' || $path === '/dev/null' || ! is_file($path)) {
            return null;
        }

        try {
            $output = (string) file_get_contents($path);
        } catch (Throwable) {
            return null;
        }

        if (trim($output) === '') {
            return null;
        }

        return Str::limit(trim($output), 2000);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $name, array $values): void
    {
        try {
            if (! Schema::hasTable('scheduled_task_runs')) {
                return;
            }

            DB::statement('SET search_path TO public');

            ScheduledTaskRun::query()->updateOrCreate(
                ['name' => $name],
                $values,
            );
        } catch (Throwable $e) {
            Log::warning('ScheduledTaskRunRecorder: failed to persist run', [
                'task' => $name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
