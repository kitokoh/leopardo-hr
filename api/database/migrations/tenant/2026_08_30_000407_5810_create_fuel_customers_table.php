<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5810 (FUEL-016) — clients & fidélité tenant-scoped.
 *
 * `fuel_customers` : comptes professionnels / clients fidélité de la
 * station (le CRM CLIENT du tenant — jamais le CRM commercial Leopardo).
 * `external_id` UNIQUE par tenant → synchronisation idempotente depuis un
 * POS/ERP. `marketing_consent` : opt-in explicite (RGPD), aucune campagne
 * sans consentement. `phone`/`email` chiffrés (cast encrypted), `metadata`
 * chiffré. Points de fidélité en bigint (entiers).
 *
 * Ajout `fuel_sales.customer_id` : lien optionnel vente → client fidélité
 * (FK composite anti cross-tenant), alimente la fidélité sans dupliquer la
 * logique CRM. Migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_customers')) {
            Schema::create('fuel_customers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('external_id', 120);
                $table->string('full_name', 200);
                $table->text('phone')->nullable(); // chiffré (cast encrypted)
                $table->text('email')->nullable(); // chiffré (cast encrypted)
                $table->boolean('marketing_consent')->default(false);
                $table->bigInteger('loyalty_points')->default(0);
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_customers_company_external_unique');
                $table->index(['company_id', 'station_id'], 'fuel_customers_company_station_idx');
                $table->index(['company_id', 'marketing_consent'], 'fuel_customers_consent_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_customers IS 'Clients fidélité FuelStation (CRM client tenant, jamais le CRM commercial Leopardo) — FUEL-016 (#5810).'");
            DB::statement("COMMENT ON COLUMN fuel_customers.marketing_consent IS 'Opt-in marketing explicite (RGPD) — aucune campagne sans consentement.'");
        }

        // Lien vente → client fidélité (additif, idempotent).
        // Prérequis PostgreSQL : UNIQUE(id, company_id) sur fuel_customers
        // pour la FK composite (pattern FUEL-002/003).
        $unique = DB::selectOne(
            "SELECT 1 FROM pg_constraint WHERE conname = 'fuel_customers_id_company_unique'"
        );

        if ($unique === null && schemaTableExists('fuel_customers')) {
            $schema = resolveTableSchema('fuel_customers');

            if ($schema !== null) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_customers ADD CONSTRAINT fuel_customers_id_company_unique UNIQUE (id, company_id)"
                );
            }
        }

        $saleColumn = DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_name = 'fuel_sales' AND column_name = 'customer_id'"
        );

        if ($saleColumn === null && schemaTableExists('fuel_sales')) {
            Schema::table('fuel_sales', function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_id')->nullable()->index()->after('cash_session_id');
            });

            $constraint = DB::selectOne("SELECT 1 FROM pg_constraint WHERE conname = 'fuel_sales_customer_company_fk'");

            if ($constraint === null) {
                $schema = resolveTableSchema('fuel_sales');

                if ($schema !== null) {
                    DB::statement(
                        "ALTER TABLE {$schema}.fuel_sales ADD CONSTRAINT fuel_sales_customer_company_fk ".
                        'FOREIGN KEY (customer_id, company_id) REFERENCES fuel_customers (id, company_id) ON DELETE SET NULL'
                    );
                }
            }

            $index = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE tablename = 'fuel_sales' AND indexname = 'fuel_sales_customer_idx'"
            );

            if ($index === null && $schema = resolveTableSchema('fuel_sales')) {
                DB::statement(
                    "CREATE INDEX fuel_sales_customer_idx ON {$schema}.fuel_sales (company_id, customer_id)"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_customers');
    }
};
