<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-PAY-015 — Employee confirmation or dispute.
 *
 * The double-validation workflow (Plan 60 /
 * 2026_05_31_000001_add_double_validation_to_salary_advances_table.php)
 * only ever let the employee confirm reception of a declared payment
 * (`confirmReceived()` -> `validation_status = employee_confirmed`).
 * There was no way for the employee to flag that they never actually
 * received the money (wrong amount, never handed over, wrong recipient,
 * etc.), even though PA2-PAY-015's acceptance criteria explicitly requires
 * it ("employee confirme reception ou ouvre reclamation").
 *
 * Adds a `disputed` state to `validation_status` plus the tracking columns
 * needed to record and later resolve a dispute, without touching any
 * pre-existing column/value.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('salary_advances');

        Schema::table("{$schema}.salary_advances", function (Blueprint $table): void {
            $table->text('dispute_reason')->nullable()->after('employee_confirmed_at');
            $table->timestamp('disputed_at')->nullable()->after('dispute_reason');
            $table->timestamp('dispute_resolved_at')->nullable()->after('disputed_at');
            $table->unsignedBigInteger('dispute_resolved_by')->nullable()->after('dispute_resolved_at');
            $table->text('dispute_resolution_note')->nullable()->after('dispute_resolved_by');
        });

        Schema::table("{$schema}.salary_advances", function (Blueprint $table): void {
            $table->foreign('dispute_resolved_by')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        // `Schema::enum()` (used by the original migration) is emulated on
        // PostgreSQL as a plain varchar column with a CHECK constraint
        // (see 2026_07_16_000003_add_marketing_to_manager_role_check_constraint.php
        // for the same pattern on `employees.manager_role`), so widening
        // the allowed set requires dropping and recreating the constraint.
        // No-op on MySQL/SQLite: `Schema::enum()` maps to a native enum
        // there and already accepts arbitrary string values in tests
        // (SQLite has no enum type at all).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"salary_advances\" DROP CONSTRAINT IF EXISTS salary_advances_validation_status_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"salary_advances\" ADD CONSTRAINT salary_advances_validation_status_check ".
                "CHECK (validation_status IN ('pending', 'manager_approved', 'payment_declared', 'employee_confirmed', 'disputed', 'rejected'))"
            );
        }
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('salary_advances');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"salary_advances\" DROP CONSTRAINT IF EXISTS salary_advances_validation_status_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"salary_advances\" ADD CONSTRAINT salary_advances_validation_status_check ".
                "CHECK (validation_status IN ('pending', 'manager_approved', 'payment_declared', 'employee_confirmed', 'rejected'))"
            );
        }

        Schema::table("{$schema}.salary_advances", function (Blueprint $table): void {
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropColumn([
                'dispute_reason',
                'disputed_at',
                'dispute_resolved_at',
                'dispute_resolved_by',
                'dispute_resolution_note',
            ]);
        });
    }
};
