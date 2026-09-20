<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (facturation, BC-30).
 *
 * health_care_acts : catalogue des actes de soins facturables (tenant) —
 * code unique PAR TENANT, catégorie bornée, prix courant (le prix est
 * FIGÉ à la ligne de facture au moment de la facturation, spec §4).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites (health_invoice_items) ;
 *   - UNIQUE(company_id, code) : code d'acte unique PAR TENANT ;
 *   - CHECK `category` (consultation|exam|surgery|hospitalization|other).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_care_acts')) {
            Schema::create('health_care_acts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 50);
                $table->string('label', 191);
                // consultation | exam | surgery | hospitalization | other
                $table->string('category', 30)->default('consultation');
                $table->decimal('price', 12, 2);
                $table->string('currency', 3);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_care_acts_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_care_acts_id_company_unique');
                $table->index(['company_id', 'category'], 'health_care_acts_company_category_idx');
                $table->index(['company_id', 'active'], 'health_care_acts_company_active_idx');
            });

            $schema = resolveTableSchema('health_care_acts');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_care_acts_category_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_care_acts\" ADD CONSTRAINT health_care_acts_category_check "
                    ."CHECK (category IN ('consultation','exam','surgery','hospitalization','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        // Table dépendante (FK composite care_act_id) : part AVANT (2BP01).
        Schema::dropIfExists('health_invoice_items');

        Schema::dropIfExists('health_care_acts');
    }
};
