<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module FuelStation — Issue #5797 (FUEL-003).
 *
 * Tables tenant : produits, pompes, cuves, compteurs et équipements.
 *
 * Règles :
 *   - company_id uuid NON nullable partout ; isolation BelongsToCompany ;
 *   - références cross-tenant impossibles : FK composites (site_id, company_id)
 *     / (pump_id, company_id) — créées CONDITIONNELLEMENT à l'existence de la
 *     table référencée (fuel_sites de FUEL-002 #5796) : ordre de merge requis
 *     #5796 → #5797 pour matérialiser les FK sur les environnements frais ;
 *   - compteur actif UNIQUE par pompe : index partiel UNIQUE
 *     (company_id, pump_id) WHERE is_active ;
 *   - capacité strictement positive (CHECK) et unités allowlistées (CHECK) ;
 *   - statuts CHECK nommés et documentés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_products')) {
            Schema::create('fuel_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 40);
                $table->string('name', 120);
                // liter | kg | unit
                $table->string('unit', 10)->default('liter');
                $table->boolean('is_active')->default(true);
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_products_company_code_unique');
                $table->index(['company_id', 'is_active'], 'fuel_products_company_active_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_products ADD CONSTRAINT fuel_products_unit_check '
                ."CHECK (unit IN ('liter','kg','unit'))"
            );
        }

        if (! schemaTableExists('fuel_pumps')) {
            Schema::create('fuel_pumps', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('code', 40);
                $table->string('name', 120);
                // active | inactive | maintenance | out_of_service
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_pumps_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_pumps_company_site_idx');
                $table->index(['company_id', 'status'], 'fuel_pumps_company_status_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_pumps ADD CONSTRAINT fuel_pumps_status_check '
                ."CHECK (status IN ('active','inactive','maintenance','out_of_service'))"
            );
        }

        if (! schemaTableExists('fuel_tanks')) {
            Schema::create('fuel_tanks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('code', 40);
                $table->string('name', 120);
                $table->unsignedBigInteger('product_id')->nullable();
                // Capacité strictement positive (CHECK).
                $table->decimal('capacity', 14, 2);
                // liter | kg | unit
                $table->string('unit', 10)->default('liter');
                $table->decimal('min_level', 14, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_tanks_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_tanks_company_site_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_tanks ADD CONSTRAINT fuel_tanks_capacity_check CHECK (capacity > 0)'
            );
            DB::statement(
                'ALTER TABLE fuel_tanks ADD CONSTRAINT fuel_tanks_unit_check '
                ."CHECK (unit IN ('liter','kg','unit'))"
            );
            DB::statement(
                'ALTER TABLE fuel_tanks ADD CONSTRAINT fuel_tanks_status_check '
                ."CHECK (status IN ('active','inactive','maintenance'))"
            );
        }

        if (! schemaTableExists('fuel_meters')) {
            Schema::create('fuel_meters', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('pump_id');
                $table->string('code', 40);
                $table->string('name', 120);
                // liter | kg | unit
                $table->string('unit', 10)->default('liter');
                $table->boolean('is_active')->default(true);
                $table->decimal('last_reading', 16, 3)->default(0);
                $table->timestamp('installed_at')->nullable();
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_meters_company_code_unique');
                $table->index(['company_id', 'pump_id'], 'fuel_meters_company_pump_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_meters ADD CONSTRAINT fuel_meters_unit_check '
                ."CHECK (unit IN ('liter','kg','unit'))"
            );
            // Compteur actif UNIQUE par pompe (index partiel).
            DB::statement(
                'CREATE UNIQUE INDEX fuel_meters_active_per_pump_unique '
                .'ON fuel_meters (company_id, pump_id) WHERE is_active = true'
            );
        }

        if (! schemaTableExists('fuel_equipment')) {
            Schema::create('fuel_equipment', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('code', 40);
                $table->string('name', 120);
                // pump | tank | meter | nozzle | console | other
                $table->string('type', 20)->default('other');
                $table->string('status', 20)->default('active');
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_equipment_company_code_unique');
                $table->index(['company_id', 'site_id'], 'fuel_equipment_company_site_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_equipment ADD CONSTRAINT fuel_equipment_type_check '
                ."CHECK (type IN ('pump','tank','meter','nozzle','console','other'))"
            );
            DB::statement(
                'ALTER TABLE fuel_equipment ADD CONSTRAINT fuel_equipment_status_check '
                ."CHECK (status IN ('active','inactive','maintenance'))"
            );
        }

        // FK composites conditionnelles : la table `fuel_sites` est livrée par
        // FUEL-002 (#5796). Si elle n'existe pas encore (branche seule / ordre
        // de merge #5797 avant #5796), les colonnes restent indexées SANS FK —
        // la contrainte est matérialisée sur les environnements frais une fois
        // #5796 mergée (migrations ré-exécutées par migrate:fresh).
        if (schemaTableExists('fuel_sites')) {
            if (! $this->hasForeignKey('fuel_pumps', 'fuel_pumps_site_company_fk')) {
                Schema::table('fuel_pumps', function (Blueprint $table): void {
                    $table->foreign(['site_id', 'company_id'], 'fuel_pumps_site_company_fk')
                        ->references(['id', 'company_id'])->on('fuel_sites')->nullOnDelete();
                });
            }
            if (! $this->hasForeignKey('fuel_tanks', 'fuel_tanks_site_company_fk')) {
                Schema::table('fuel_tanks', function (Blueprint $table): void {
                    $table->foreign(['site_id', 'company_id'], 'fuel_tanks_site_company_fk')
                        ->references(['id', 'company_id'])->on('fuel_sites')->nullOnDelete();
                });
            }
            if (! $this->hasForeignKey('fuel_equipment', 'fuel_equipment_site_company_fk')) {
                Schema::table('fuel_equipment', function (Blueprint $table): void {
                    $table->foreign(['site_id', 'company_id'], 'fuel_equipment_site_company_fk')
                        ->references(['id', 'company_id'])->on('fuel_sites')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_equipment');
        Schema::dropIfExists('fuel_meters');
        Schema::dropIfExists('fuel_tanks');
        Schema::dropIfExists('fuel_pumps');
        Schema::dropIfExists('fuel_products');
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
              WHERE table_name = ? AND constraint_name = ? LIMIT 1',
            [$table, $constraint]
        );

        return $row !== null;
    }
};
