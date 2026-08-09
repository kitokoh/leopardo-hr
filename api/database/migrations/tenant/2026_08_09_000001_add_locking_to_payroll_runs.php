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
 * Résolution dynamique de l'enum (même pattern que
 * 2026_06_29_000205_extend_attendance_logs_method_for_geo_auto.php) :
 * la colonne peut être un ENUM natif PostgreSQL ou un VARCHAR selon le
 * contexte (schéma de test manuel vs migrations réelles) ; on n'altère le
 * type que s'il s'agit d'un ENUM, et l'ajout de valeur est idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Étendre l'enum natif PG si la colonne en est un.
        $result = DB::selectOne("
            SELECT data_type, udt_name
            FROM information_schema.columns
            WHERE table_name = 'payroll_runs'
              AND column_name = 'status'
              AND table_schema = current_schema()
        ");

        if ($result && $result->data_type === 'USER-DEFINED') {
            $enumName = $result->udt_name;

            $exists = DB::selectOne("
                SELECT 1 FROM pg_enum pe
                JOIN pg_type pt ON pt.oid = pe.enumtypid
                WHERE pt.typname = ?
                  AND pe.enumlabel = 'locked'
            ", [$enumName]);

            if (! $exists) {
                DB::statement("ALTER TYPE {$enumName} ADD VALUE IF NOT EXISTS 'locked'");
            }
        }
        // Colonne VARCHAR → toute valeur acceptée, aucune action.

        // 1b) Mettre à jour le CHECK constraint généré par Laravel
        // (`payroll_runs_status_check`, liste explicite IN (...)) : l'ALTER TYPE
        // ci-dessus ne l'affecte pas — sans cette étape, tout INSERT en statut
        // 'locked' échoue (SQLSTATE 23514, CI rouge 2026-08-09).
        $checks = DB::select("
            SELECT pg_get_constraintdef(oid) AS def
            FROM pg_constraint
            WHERE conrelid = 'payroll_runs'::regclass
              AND contype = 'c'
              AND conname = 'payroll_runs_status_check'
        ");
        foreach ($checks as $row) {
            $def = (string) $row->def;
            if (str_contains($def, "'locked'")) {
                continue; // déjà à jour (idempotent)
            }
            $newDef = preg_replace(
                "/'paid'\\s*,?/",
                "'paid', 'locked'",
                $def,
                1
            );
            if ($newDef === null || $newDef === $def) {
                continue;
            }
            DB::statement("ALTER TABLE payroll_runs DROP CONSTRAINT payroll_runs_status_check");
            DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT payroll_runs_status_check CHECK ({$newDef})");
        }

        // 2) Colonnes de verrouillage.
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
