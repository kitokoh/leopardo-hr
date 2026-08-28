<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUEL-008 (#5802) — Ventes et transactions par pompe.
 *
 * `fuel_sales` : vente de carburant ou produit annexe — référence station
 * (uuid, FUEL-002), pompe (uuid, FUEL-003), session de caisse (FK interne),
 * vendeur (FK employees), produit, quantité (unités sûres : decimal 14,3),
 * prix unitaire et MONTANT CALCULÉ SERVEUR (quantity × unit_price —
 * jamais fourni par le client), horodatage UTC, source (manual|kiosk|pos)
 * et `external_id` (unicité (company_id, external_id) → rejeu idempotent).
 *
 * La relation pompe/vente est validée au niveau service : si la table
 * `fuel_pumps` (FUEL-003) existe, la pompe doit appartenir au tenant
 * (422 PUMP_OUTSIDE_TENANT) ; tant qu'elle n'existe pas, la colonne reste
 * une référence uuid indexée (résolution au merge de FUEL-003).
 *
 * Migration additive + idempotente (garde #1962/#5431), réf. issue dans le
 * nom. Rollback : suppression de la table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_sales')) {
            Schema::create('fuel_sales', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('station_id')->nullable()->index();
                $table->uuid('pump_id')->nullable()->index();
                $table->uuid('cash_session_id')->nullable()->index();
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
            });
        }

        $this->addForeignKeyIfMissing('fuel_sales', 'fuel_sales_cash_session_fk', 'cash_session_id', 'fuel_cash_sessions', 'id');
        $this->addForeignKeyIfMissing('fuel_sales', 'fuel_sales_employee_fk', 'employee_id', 'employees', 'id');
        $this->addForeignKeyIfMissing('fuel_sales', 'fuel_sales_station_fk', 'station_id', 'fuel_stations', 'id');
        $this->addForeignKeyIfMissing('fuel_sales', 'fuel_sales_pump_fk', 'pump_id', 'fuel_pumps', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_sales');
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $references,
        string $referencedColumn,
    ): void {
        if (! schemaTableExists($references)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_schema = ANY (current_schemas(false))',
            [$constraint]
        );

        if ($exists === null) {
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
                 FOREIGN KEY ({$column}) REFERENCES {$references} ({$referencedColumn}) ON DELETE CASCADE"
            );
        }
    }
};
