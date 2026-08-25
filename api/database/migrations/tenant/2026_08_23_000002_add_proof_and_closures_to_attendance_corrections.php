<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5267 — corrections de pointage : justificatif + verrouillage de période.
 *
 *  1. `attendance_correction_requests.proof_path` (nullable) — pièce jointe
 *     optionnelle (mêmes règles que les absences : jpg/jpeg/png/pdf/heic ≤ 5 Mo).
 *  2. Table `attendance_period_closures` — clôture tracée d'une période de
 *     pointage par tenant ; toute demande/décision de correction portant sur
 *     une date close est refusée (422 ATTENDANCE_PERIOD_CLOSED).
 *
 * F-17 (#1595/#1933) : accès qualifiés par schéma résolu via
 * `resolveTableSchema` (convention #1613) — idempotent, rejouable sur Render.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('attendance_correction_requests');

        if ($schema !== null) {
            $hasProof = (bool) DB::selectOne(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = 'attendance_correction_requests' AND column_name = 'proof_path'",
                [$schema]
            );

            if (! $hasProof) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ADD COLUMN proof_path VARCHAR(255) NULL',
                    $schema.'.attendance_correction_requests'
                ));
            }
        }

        if (! schemaTableExists('attendance_period_closures')) {
            Schema::create('attendance_period_closures', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('closed_by')->nullable();
                $table->foreign('closed_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('closed_at')->useCurrent();

                $table->unique(['company_id', 'period_start', 'period_end'], 'attendance_period_closures_unique');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_correction_requests');

        if ($schema !== null) {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP COLUMN IF EXISTS proof_path',
                $schema.'.attendance_correction_requests'
            ));
        }

        Schema::dropIfExists('attendance_period_closures');
    }
};
