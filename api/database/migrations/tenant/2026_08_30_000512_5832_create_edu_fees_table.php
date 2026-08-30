<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5832 (EDU-016) — frais scolaires et contrat Accounting.
 *
 * `edu_fees` : frais facturés à une famille (inscription, scolarité…),
 * rattachés à un élève (et optionnellement à un dossier d'admission).
 * Statut borné (pending|paid|waived|cancelled) ; `external_reference`
 * unique PAR TENANT → rejeu idempotent (import Accounting côté consommateur).
 *
 * CONTRAT ACCOUNTING (sans reproduire la comptabilité) : EduManager ne crée
 * JAMAIS d'écriture comptable — les frais sont exposés via le modèle
 * `EduFee` (read model tenant-scopé) et consommés par Accounting via son
 * propre flux (contrat documenté EDU-016). `paid_at` + `payment_reference`
 * tracent le règlement sans duplication.
 *
 * company_id uuid NON nullable, FK composites anti cross-tenant,
 * CHECK gardés pg_constraint, index tenant-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_fees')) {
            Schema::create('edu_fees', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('admission_id')->nullable()->index();
                $table->string('label', 191);
                $table->decimal('amount', 12, 2);
                $table->date('due_date');
                // pending | paid | waived | cancelled — CHECK edu_fees_status_check
                $table->string('status', 20)->default('pending');
                $table->string('external_reference', 100)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_reference'], 'edu_fees_company_external_unique');
                $table->index(['company_id', 'student_id'], 'edu_fees_company_student_idx');
                $table->index(['company_id', 'status'], 'edu_fees_company_status_idx');
                $table->index(['company_id', 'due_date'], 'edu_fees_company_due_idx');

                $table->foreign(['student_id', 'company_id'], 'edu_fees_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['admission_id', 'company_id'], 'edu_fees_admission_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_admissions')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('edu_fees');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fees_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fees\" ADD CONSTRAINT edu_fees_status_check "
                    ."CHECK (status IN ('pending','paid','waived','cancelled')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fees_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fees\" ADD CONSTRAINT edu_fees_amount_check "
                    .'CHECK (amount > 0); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_fees');
    }
};
