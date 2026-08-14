<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1818 — bulletins rétroactifs et régularisations : un run verrouillé
 * peut être corrigé via un run de régularisation (sans modifier l'original).
 *
 * Additive et idempotente (pattern 00015) :
 *   - `payroll_runs.type` ENUM('standard','regularization') DEFAULT 'standard'
 *   - `payroll_runs.original_run_id` BIGINT NULLABLE (référence le run corrigé)
 *   - `payroll_runs.reason` TEXT NULLABLE (motif de régularisation)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_runs', 'type')) {
            Schema::table('payroll_runs', static function (Blueprint $table): void {
                $table->string('type', 20)->default('standard')->after('status');
            });
        }

        if (! Schema::hasColumn('payroll_runs', 'original_run_id')) {
            Schema::table('payroll_runs', static function (Blueprint $table): void {
                $table->unsignedBigInteger('original_run_id')->nullable()->after('type');
                $table->index('original_run_id');
            });
        }

        if (! Schema::hasColumn('payroll_runs', 'reason')) {
            Schema::table('payroll_runs', static function (Blueprint $table): void {
                $table->text('reason')->nullable()->after('original_run_id');
            });
        }

        $this->ensureTypeConstraint();
    }

    public function down(): void
    {
        foreach (['reason', 'original_run_id', 'type'] as $column) {
            if (Schema::hasColumn('payroll_runs', $column)) {
                Schema::table('payroll_runs', static function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Contrainte CHECK sur payroll_runs.type (idempotente, même pattern que
     * payroll_runs_status_check — voir 2026_08_09_000001).
     */
    private function ensureTypeConstraint(): void
    {
        if (! Schema::hasColumn('payroll_runs', 'type')) {
            return;
        }

        $schema = resolveTableSchema('payroll_runs');

        $exists = DB::selectOne(
            "SELECT 1 FROM pg_constraint WHERE conname = 'payroll_runs_type_check' AND conrelid = ?::regclass",
            ["{$schema}.payroll_runs"]
        );

        if ($exists !== null) {
            return;
        }

        DB::statement(
            "ALTER TABLE \"{$schema}\".\"payroll_runs\" ADD CONSTRAINT payroll_runs_type_check "
            ."CHECK (type IN ('standard', 'regularization'))"
        );
    }
};
