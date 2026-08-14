<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DZ-DEPTH (issue #1818) — bulletins rétroactifs et régularisations :
 * correction d'un run clôturé sans modifier le run original.
 *
 * Colonnes additives sur `payroll_runs` :
 *   - `type`            : 'standard' (défaut) | 'regularization' ;
 *   - `original_run_id` : run clôturé corrigé par cette régularisation ;
 *   - `reason`          : motif de la régularisation (obligatoire, audité).
 *
 * Migration additive et idempotente (pattern schema-aware du module Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('payroll_runs');

        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.payroll_runs", function (Blueprint $blueprint) use ($schema): void {
            if (! schemaHasColumn('payroll_runs', 'type')) {
                $blueprint->string('type', 20)->default('standard')->index();
            }
            if (! schemaHasColumn('payroll_runs', 'original_run_id')) {
                $blueprint->unsignedBigInteger('original_run_id')->nullable()->after('type');

                $blueprint->foreign('original_run_id')
                    ->references('id')
                    ->on("{$schema}.payroll_runs")
                    ->nullOnDelete();
            }
            if (! schemaHasColumn('payroll_runs', 'reason')) {
                $blueprint->text('reason')->nullable()->after('original_run_id');
            }
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('payroll_runs');

        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.payroll_runs", function (Blueprint $blueprint): void {
            if (schemaHasColumn('payroll_runs', 'original_run_id')) {
                $blueprint->dropForeign(['original_run_id']);
            }
            foreach (['original_run_id', 'type', 'reason'] as $column) {
                if (schemaHasColumn('payroll_runs', $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
