<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (HC-007, BC-30).
 *
 * health_invoices : factures de soins du tenant (patient, lignes d'actes,
 * remise, total, statut, paiements). Le total est TOUJOURS recalculé côté
 * serveur (Σ lignes − remise) ; les montants sont FIGÉS à la facturation.
 *
 * Cycle de vie : draft → issued → paid | partially_paid | cancelled.
 * Le numéro `HINV-YYYY-NNNN` (séquence PAR TENANT et par année) est posé à
 * l'ÉMISSION — les brouillons n'en consomment pas ; UNIQUE (company_id,
 * number) en filet (les NULL de brouillons ne se heurtent pas en Postgres).
 *
 * Invariants portés par le SCHÉMA :
 *   - CHECK statut borné draft|issued|paid|partially_paid|cancelled ;
 *   - CHECK montants >= 0 et remise <= sous-total ;
 *   - FK COMPOSITE (patient_id, company_id) : facture cross-tenant =
 *     violation FK en base.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_invoices')) {
            Schema::create('health_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('patient_id');
                // Numéro HINV-YYYY-NNNN posé à l'ÉMISSION (null en brouillon).
                $table->string('number', 30)->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                // draft | issued | paid | partially_paid | cancelled.
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_invoices_id_company_unique');
                $table->unique(['company_id', 'number'], 'health_invoices_company_number_unique');
                $table->index(['company_id', 'status'], 'health_invoices_company_status_idx');
                $table->index(['company_id', 'patient_id', 'created_at'], 'health_invoices_company_patient_idx');

                // Cross-tenant impossible (FK composite).
                $table->foreign(['patient_id', 'company_id'], 'health_invoices_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_invoices');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoices_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoices\" ADD CONSTRAINT health_invoices_status_check "
                    ."CHECK (status IN ('draft','issued','paid','partially_paid','cancelled')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoices_amounts_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoices\" ADD CONSTRAINT health_invoices_amounts_check "
                    .'CHECK (subtotal >= 0 AND discount >= 0 AND total >= 0 AND discount <= subtotal); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_invoices');
    }
};
