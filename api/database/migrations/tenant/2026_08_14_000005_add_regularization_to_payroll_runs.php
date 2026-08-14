<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DZ-DEPTH (#1818) — bulletins rétroactifs et régularisations.
 *
 * Migration additive sur `payroll_runs` :
 *   - `type` ('standard' | 'regularization') : un run de régularisation
 *     corrige un run déjà verrouillé sans modifier l'original ;
 *   - `original_run_id` : lien vers le run clôturé corrigé ;
 *   - `reason` : motif obligatoire de la régularisation (audit).
 *
 * Les runs existants restent `standard` (rétrocompatibilité totale).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        if (! Schema::hasColumn('payroll_runs', 'type')) {
            Schema::table('payroll_runs', function (Blueprint $table): void {
                $table->string('type', 30)->default('standard'); // standard | regularization
                $table->unsignedBigInteger('original_run_id')->nullable();
                $table->text('reason')->nullable();

                $table->foreign('original_run_id')
                    ->references('id')
                    ->on('payroll_runs')
                    ->nullOnDelete();

                $table->index(['original_run_id']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_runs') || ! Schema::hasColumn('payroll_runs', 'type')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropForeign(['original_run_id']);
            $table->dropColumn(['type', 'original_run_id', 'reason']);
        });
    }
};
