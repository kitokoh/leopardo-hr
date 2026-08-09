<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-11 : clôture 2 étapes + verrouillage.
 *
 * Ajoute le statut `locked` et les colonnes de verrouillage
 * (locked_by / locked_at) à payroll_runs. Le verrouillage est l'étape finale
 * du cycle de clôture (préparation → validation RH → validation comptable →
 * verrouillage) : après verrouillage, toute modification exige un
 * déverrouillage motivé et tracé (PayrollClosingService).
 *
 * NB : le type enum natif PostgreSQL ne supporte pas l'ajout de valeur en
 * plein milieu ; `ADD VALUE` est idempotent via IF NOT EXISTS. Les statuts
 * existants (draft/calculating/calculated/validated/paid/cancelled) ne sont
 * pas modifiés.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Étendre l'enum natif PG (créé par la migration
        // 2026_05_10_100001_create_payroll_engine_tables.php).
        DB::statement("ALTER TYPE payroll_runs_status ADD VALUE IF NOT EXISTS 'locked'");

        if (! Schema::hasColumn('payroll_runs', 'locked_by')) {
            Schema::table('payroll_runs', function (Blueprint $table): void {
                $table->unsignedInteger('locked_by')->nullable()->after('paid_at');
                $table->timestampTz('locked_at')->nullable()->after('locked_by');

                $table->foreign('locked_by')->references('id')->on('employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_runs', 'locked_at')) {
            Schema::table('payroll_runs', function (Blueprint $table): void {
                $table->dropForeign(['locked_by']);
                $table->dropColumn(['locked_by', 'locked_at']);
            });
        }

        // Le retrait d'une valeur d'un enum PG nécessite un type de
        // remplacement ; conservé volontairement (down partiel, non destructif).
    }
};
