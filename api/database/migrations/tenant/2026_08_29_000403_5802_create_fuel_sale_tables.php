<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5802 (FUEL-008) — ventes et transactions par pompe.
 *
 * `fuel_sales` : vente de carburant ou produit annexe — référence station
 * (FK COMPOSITE (station_id, company_id) → fuel_stations(id, company_id)),
 * pompe (FK COMPOSITE (pump_id, company_id) → fuel_pumps(id, company_id)),
 * session de caisse (FK interne), vendeur (FK employees), produit, quantité
 * en unités sûres (decimal 14,3), prix unitaire et MONTANT CALCULÉ SERVEUR
 * (quantity × unit_price — jamais fourni par le client), horodatage UTC,
 * source (manual|kiosk|pos) et `external_id` (unicité (company_id,
 * external_id) → rejeu idempotent).
 *
 * Prérequis FK composites : UNIQUE(id, company_id) sur `fuel_pumps` et
 * `fuel_meter_registers` posées de façon gardée (pattern FUEL-003 pour
 * fuel_stations) avant les FKs qui les référencent.
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * clés primaires bigint ($table->id()), company_id uuid indexé, CHECKs
 * gardés pg_constraint. Rollback : suppression de la table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prérequis FK composites (pattern FUEL-003) : UNIQUE(id, company_id)
        // sur les tables référencées par les FKs composites de fuel_sales.
        foreach (['fuel_pumps', 'fuel_meter_registers'] as $table) {
            $this->addIdCompanyUnique($table);
        }

        if (! schemaTableExists('fuel_sales')) {
            Schema::create('fuel_sales', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedBigInteger('pump_id')->nullable()->index();
                $table->unsignedBigInteger('cash_session_id')->nullable()->index();
                $table->unsignedInteger('employee_id')->index();
                $table->string('product', 80);
                $table->decimal('quantity', 14, 3);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('amount', 14, 2);
                $table->timestampTz('sale_time')->useCurrent();
                $table->string('source', 20)->default('manual'); // manual|kiosk|pos
                $table->string('external_id', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_sales_external_unique');
                $table->index(['company_id', 'sale_time'], 'fuel_sales_company_time_idx');

                // FKs composites anti cross-tenant (pattern FUEL-002/003).
                $table->foreign(['station_id', 'company_id'], 'fuel_sales_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['pump_id', 'company_id'], 'fuel_sales_pump_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_pumps')
                    ->cascadeOnDelete();
                $table->foreign('cash_session_id', 'fuel_sales_cash_session_fk')
                    ->references('id')
                    ->on('fuel_cash_sessions')
                    ->cascadeOnDelete();
                $table->foreign('employee_id', 'fuel_sales_employee_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_sales');
    }

    private function addIdCompanyUnique(string $table): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $constraint = $table.'_id_company_unique';

        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$constraint]);

        if ($row !== null) {
            return;
        }

        $schema = resolveTableSchema($table);

        if ($schema !== null) {
            DB::statement(
                "ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$constraint} UNIQUE (id, company_id)"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_sales');

        if ($schema === null || $this->constraintExists('fuel_sales_source_check')) {
            return;
        }

        DB::statement(
            "ALTER TABLE {$schema}.fuel_sales ADD CONSTRAINT fuel_sales_source_check CHECK (source IN ('manual', 'kiosk', 'pos'))"
        );
    }
};
