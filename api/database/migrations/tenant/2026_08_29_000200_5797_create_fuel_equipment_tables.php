<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5797 (FUEL-003) — équipements FuelStation : pompes, cuves,
 * compteurs (registers).
 *
 * Contraintes d'intégrité :
 *  - FK COMPOSITES (station_id, company_id) → fuel_stations(id, company_id)
 *    : impossible de rattacher une pompe/cuve à la station d'un AUTRE
 *    tenant (exigence spec §13.2) ;
 *  - UNIQUE(company_id, pump_id, meter_code) WHERE status='active' :
 *    un seul compteur ACTIF par (pompe, code) — le retrait passe par un
 *    second register (historique conservé) ;
 *  - capacités/volumes en unités mineures entières (bigint) — jamais de
 *    flottants binaires pour le calcul métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prérequis FK composite : UNIQUE(id, company_id) sur fuel_stations.
        if (schemaTableExists('fuel_stations') && ! $this->uniqueExists('fuel_stations_id_company_unique')) {
            $schema = resolveTableSchema('fuel_stations');
            DB::statement(
                "ALTER TABLE {$schema}.fuel_stations ADD CONSTRAINT fuel_stations_id_company_unique UNIQUE (id, company_id)"
            );
        }

        // FUEL-003 — catalogue des produits vendus par la station (tenant-scoped).
        // Les colonnes product_types/product_code/product_type des équipements
        // référencent ces codes au niveau application (pas de FK : les codes
        // produits sont stables par tenant).
        if (! schemaTableExists('fuel_products')) {
            Schema::create('fuel_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->string('unit_code', 20)->default('l');

                // active | inactive
                $table->string('status', 20)->default('active');

                // Métadonnées opérationnelles chiffrées (RGPD).
                $table->text('metadata')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_products_company_code_unique');
                $table->index(['company_id', 'status'], 'fuel_products_company_status_idx');
            });

            $productSchema = resolveTableSchema('fuel_products');

            if ($productSchema !== null && ! $this->constraintExists('fuel_products_unit_check')) {
                DB::statement(
                    "ALTER TABLE {$productSchema}.fuel_products ADD CONSTRAINT fuel_products_unit_check CHECK (unit_code IN ('l', 'gal'))"
                );
            }

            if ($productSchema !== null && ! $this->constraintExists('fuel_products_status_check')) {
                DB::statement(
                    "ALTER TABLE {$productSchema}.fuel_products ADD CONSTRAINT fuel_products_status_check CHECK (status IN ('active', 'inactive'))"
                );
            }
        }

        if (! schemaTableExists('fuel_pumps')) {
            Schema::create('fuel_pumps', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');

                $table->string('code', 40);
                // Liste de codes produit servis par la pompe (ex. ["essence","gazoil"]).
                $table->json('product_types')->nullable();

                // active | inactive | retired
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['id', 'company_id'], 'fuel_pumps_id_company_unique');
                $table->unique(['company_id', 'station_id', 'code'], 'fuel_pumps_company_station_code_unique');
                $table->index(['company_id', 'station_id'], 'fuel_pumps_company_station_idx');
                $table->index(['company_id', 'status'], 'fuel_pumps_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_pumps_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_tanks')) {
            Schema::create('fuel_tanks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');

                $table->string('code', 40);
                $table->string('product_type', 40);
                // Capacité et niveau en unités mineures entières (ex. centilitres).
                // Capacité obligatoire et strictement positive (CHECK fuel_tanks_capacity_check).
                $table->unsignedBigInteger('capacity_minor');
                $table->unsignedBigInteger('current_level_minor')->default(0);

                // active | inactive | retired
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'station_id', 'code'], 'fuel_tanks_company_station_code_unique');
                $table->index(['company_id', 'station_id'], 'fuel_tanks_company_station_idx');
                $table->index(['company_id', 'status'], 'fuel_tanks_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_tanks_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_meter_registers')) {
            Schema::create('fuel_meter_registers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');
                $table->unsignedBigInteger('pump_id');

                $table->string('meter_code', 40);

                // mechanical | electronic | main_totalizer | secondary_totalizer | test
                $table->string('meter_type', 30)->default('electronic');

                $table->string('product_code', 40)->nullable();
                $table->string('unit_code', 20)->default('l');

                // Précision native du compteur (décimales), ex. 2 pour 0,01 l.
                $table->unsignedSmallInteger('precision_scale')->default(0);

                // Valeur de rollover documentée (remise à zéro) — NULL si jamais.
                $table->unsignedBigInteger('rollover_limit')->nullable();

                $table->timestamp('installed_at')->nullable();
                $table->timestamp('retired_at')->nullable();

                // active | retired
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'pump_id', 'meter_code'], 'fuel_meters_company_pump_code_unique');
                $table->index(['company_id', 'pump_id'], 'fuel_meters_company_pump_idx');
                $table->index(['company_id', 'station_id'], 'fuel_meters_company_station_idx');

                $table->foreign(['pump_id', 'company_id'], 'fuel_meters_pump_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_pumps')
                    ->cascadeOnDelete();
            });
        }

        // FUEL-003 — critères d'acceptation : compteur ACTIF unique par pompe
        // (index partiel), capacité strictement positive, unités allowlistées.
        $this->addEquipmentGuards();

        $this->addChecks();
    }

    /**
     * Contraintes supplémentaires exigées par FUEL-003 (spec §13.2) :
     *  - un seul compteur actif par pompe (index partiel WHERE status='active') ;
     *  - capacité de cuve strictement positive ;
     *  - unités de comptage allowlistées.
     */
    private function addEquipmentGuards(): void
    {
        $meterSchema = resolveTableSchema('fuel_meter_registers');

        // FUEL-003 — un seul compteur ACTIF par pompe (décision porteur d'idée) :
        // index partiel PG (company_id, pump_id) WHERE status = 'active'.
        if ($meterSchema !== null && ! $this->indexExists('fuel_meters_active_per_pump_unique')) {
            DB::statement(
                "CREATE UNIQUE INDEX fuel_meters_active_per_pump_unique ON {$meterSchema}.fuel_meter_registers (company_id, pump_id) WHERE status = 'active'"
            );
        }

        $tankSchema = resolveTableSchema('fuel_tanks');

        if ($tankSchema !== null && ! $this->constraintExists('fuel_tanks_capacity_check')) {
            DB::statement(
                "ALTER TABLE {$tankSchema}.fuel_tanks ADD CONSTRAINT fuel_tanks_capacity_check CHECK (capacity_minor > 0)"
            );
        }

        if ($meterSchema !== null && ! $this->constraintExists('fuel_meters_unit_check')) {
            DB::statement(
                "ALTER TABLE {$meterSchema}.fuel_meter_registers ADD CONSTRAINT fuel_meters_unit_check CHECK (unit_code IN ('l', 'gal'))"
            );
        }
    }


    public function down(): void
    {
        Schema::dropIfExists('fuel_meter_registers');
        Schema::dropIfExists('fuel_tanks');
        Schema::dropIfExists('fuel_pumps');
        Schema::dropIfExists('fuel_products');
    }

    private function uniqueExists(string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?',
            [$constraint]
        );

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach (['fuel_pumps' => 'fuel_pumps_status_check', 'fuel_tanks' => 'fuel_tanks_status_check'] as $table => $constraint) {
            $schema = resolveTableSchema($table);

            if ($schema === null || $this->constraintExists($constraint)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$constraint} CHECK (status IN ('active', 'inactive', 'retired'))"
            );
        }

        $schema = resolveTableSchema('fuel_meter_registers');

        if ($schema !== null && ! $this->constraintExists('fuel_meters_status_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_meter_registers ADD CONSTRAINT fuel_meters_status_check CHECK (status IN ('active', 'retired'))"
            );
        }

        if ($schema !== null && ! $this->constraintExists('fuel_meters_type_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_meter_registers ADD CONSTRAINT fuel_meters_type_check CHECK (meter_type IN ('mechanical', 'electronic', 'main_totalizer', 'secondary_totalizer', 'test'))"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
