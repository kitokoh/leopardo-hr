<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-QA-006 — Redis/jobs observability.
 *
 * Records the last outcome of every scheduled Artisan command (cron entries
 * registered in `routes/console.php` / `bootstrap/app.php`) so the platform
 * admin "System" screen can show *when* each background task last ran and
 * whether it succeeded, instead of only exposing point-in-time queue depths.
 *
 * One row per task `name` (the exact scheduled command line), overwritten on
 * every run (`upsert`) — we only need the *last* run, not a full history, to
 * keep this cheap to query from a dashboard polling every few seconds.
 *
 * Lives under `database/migrations/public` (not `tenant/`): scheduled tasks
 * are a platform-wide operational concern, not scoped to a single tenant
 * company, exactly like `failed_jobs` (PA2-JOB-001) already is in this same
 * directory.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (Schema::hasTable('scheduled_task_runs')) {
            return;
        }

        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 190)->unique();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->integer('runtime_ms')->nullable();
            $table->string('status', 20)->default('unknown');
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        Schema::dropIfExists('scheduled_task_runs');
    }
};
