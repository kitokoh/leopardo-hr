<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-JOB-001 — Redis/queues readiness.
 *
 * `config/queue.php` ('failed' => ['driver' => 'database-uuids', 'table' =>
 * 'failed_jobs']) and `App\Console\Commands\QueueHealthCheck` both assume a
 * `failed_jobs` table exists, and Laravel's built-in `queue:failed`,
 * `queue:retry`, `queue:forget` and `queue:flush` commands write/read it
 * unconditionally regardless of the active queue driver (redis, database,
 * sync, ...) — this table was never created by a migration in this repo,
 * so every one of those commands (and `QueueHealthCheck::failedJobsCount()`)
 * would throw `QueryException: relation "failed_jobs" does not exist` the
 * first time a queued job actually failed in a real (non-sqlite) environment.
 *
 * Lives under `database/migrations/public` (not `tenant/`): failed jobs are
 * an operational/infrastructure concern shared across the whole platform,
 * not scoped to a single tenant company, exactly like `seed_locks` and
 * `personal_access_tokens` already are in this same directory.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Issue #1490 : ne JAMAIS laisser fuir `SET search_path TO public` dans
        // la session — les migrations tenant exécutées ensuite dans la même
        // session créeraient leurs tables dans `public` au lieu du schéma
        // tenant (shared_tenants), d'où les `relation "companies" does not
        // exist` intermittents sur la gate de coverage (runs 2026-07-25).
        $previousPath = DB::selectOne('SHOW search_path')->search_path;
        DB::statement('SET search_path TO public');

        try {
            if (Schema::hasTable('failed_jobs')) {
                return;
            }

            try {
                Schema::create('failed_jobs', function (Blueprint $table) {
                    $table->id();
                    $table->string('uuid')->unique();
                    $table->text('connection');
                    $table->text('queue');
                    $table->longText('payload');
                    $table->longText('exception');
                    $table->timestampTz('failed_at')->useCurrent();
                });
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '42P07') {
                    throw $exception;
                }
            }
        } finally {
            DB::statement('SET search_path TO '.$previousPath);
        }
    }

    public function down(): void
    {
        $previousPath = DB::selectOne('SHOW search_path')->search_path;
        DB::statement('SET search_path TO public');
        try {
            Schema::dropIfExists('failed_jobs');
        } finally {
            DB::statement('SET search_path TO '.$previousPath);
        }
    }
};
