<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (HC-007, BC-30).
 *
 * health_care_acts : catalogue tarifaire des actes médicaux du tenant
 * (consultations, examens, actes techniques, journées d'hospitalisation).
 * Le prix du catalogue est le prix COURANT : à la facturation il est FIGÉ
 * dans `health_invoice_items.unit_price` (critère HC-007) — modifier le
 * catalogue ne change jamais une facture existante.
 *
 * Invariants portés par le SCHÉMA :
 *   - UNIQUE (company_id, code) : le code d'acte est unique par tenant ;
 *   - CHECK catégorie bornée consultation|examination|procedure|
 *     hospitalization|other ;
 *   - CHECK prix >= 0.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_care_acts')) {
            Schema::create('health_care_acts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 30);
                $table->string('name', 150);
                // consultation | examination | procedure | hospitalization |
                // other — CHECK ci-dessous.
                $table->string('category', 30)->default('other');
                $table->decimal('price', 12, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_care_acts_id_company_unique');
                $table->unique(['company_id', 'code'], 'health_care_acts_company_code_unique');
                $table->index(['company_id', 'category', 'is_active'], 'health_care_acts_company_category_idx');
            });

            $schema = resolveTableSchema('health_care_acts');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_care_acts_category_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_care_acts\" ADD CONSTRAINT health_care_acts_category_check "
                    ."CHECK (category IN ('consultation','examination','procedure','hospitalization','other')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_care_acts_price_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_care_acts\" ADD CONSTRAINT health_care_acts_price_check "
                    .'CHECK (price >= 0); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_care_acts');
    }
};
