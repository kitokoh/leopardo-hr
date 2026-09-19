<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (HC-007, BC-30).
 *
 * health_invoice_items : lignes d'actes d'une facture de soins. Le libellé
 * et le prix unitaire sont FIGÉS depuis le catalogue AU MOMENT de la
 * facturation (critère HC-007) : changer le tarif d'un acte ne modifie
 * JAMAIS une facture existante. `line_total` = unit_price × quantity,
 * calculé côté serveur.
 *
 * Invariants portés par le SCHÉMA :
 *   - CHECK quantité > 0 et montants >= 0 ;
 *   - FK COMPOSITES (invoice_id, company_id) et (care_act_id, company_id) :
 *     une ligne cross-tenant est une violation FK en base. La FK vers le
 *     catalogue est RESTRICTIVE (pas de cascade) : un acte facturé ne se
 *     supprime pas, il se désactive.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_invoice_items')) {
            Schema::create('health_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('care_act_id');
                // Libellé et prix FIGÉS depuis le catalogue à la facturation.
                $table->string('label', 150);
                $table->decimal('unit_price', 12, 2);
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->unique(['id', 'company_id'], 'health_invoice_items_id_company_unique');
                $table->index(['company_id', 'invoice_id'], 'health_invoice_items_company_invoice_idx');

                // Cross-tenant impossible (FK composites).
                $table->foreign(['invoice_id', 'company_id'], 'health_invoice_items_invoice_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_invoices')
                    ->cascadeOnDelete();
                $table->foreign(['care_act_id', 'company_id'], 'health_invoice_items_care_act_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_care_acts');
            });

            $schema = resolveTableSchema('health_invoice_items');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoice_items_amounts_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoice_items\" ADD CONSTRAINT health_invoice_items_amounts_check "
                    .'CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_invoice_items');
    }
};
