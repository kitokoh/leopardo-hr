<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit #1704 — Foreign keys added without indexes (PostgreSQL).
 *
 * PostgreSQL does not auto-index FK columns. The FKs added to
 * `salary_advances` in 2026_05_31_000001 (manager_approved_by,
 * payment_declared_by) and 2026_07_24_000001 (dispute_resolved_by) were
 * created without `->index()`, so lookups/joins on those columns degrade
 * to sequential scans as the table grows.
 *
 * Additive, idempotent: creates the missing indexes if they don't already
 * exist (safe to run on tenants already migrated to the new schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('salary_advances');

        foreach ([
            'salary_advances_manager_approved_by_index',
            'salary_advances_payment_declared_by_index',
            'salary_advances_dispute_resolved_by_index',
        ] as $index) {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS {$index} ON {$schema}.salary_advances ({$this->columnFor($index)})"
            );
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('salary_advances');

        foreach ([
            'salary_advances_manager_approved_by_index',
            'salary_advances_payment_declared_by_index',
            'salary_advances_dispute_resolved_by_index',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$schema}.{$index}");
        }
    }

    private function columnFor(string $index): string
    {
        return match ($index) {
            'salary_advances_manager_approved_by_index' => 'manager_approved_by',
            'salary_advances_payment_declared_by_index' => 'payment_declared_by',
            'salary_advances_dispute_resolved_by_index' => 'dispute_resolved_by',
            default => throw new InvalidArgumentException("Unknown index: {$index}"),
        };
    }
};
