<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6176 (RESTO-211) - RestaurantManager : zones de livraison, livreurs, livraisons.
 *
 * - `restaurant_delivery_zones` : zones de livraison par branche (frais en minor
 *   units, commande minimum) ;
 * - `restaurant_delivery_riders` : livreurs (employee_id reference HR par valeur,
 *   sans FK) ;
 * - `restaurant_deliveries` : livraisons rattachees a une commande (statut,
 *   frais, contact de remise).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_delivery_zones')) {
            Schema::create('restaurant_delivery_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->string('name', 120);
                $table->unsignedInteger('fee_minor')->default(0);
                $table->unsignedInteger('min_order_minor')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'branch_id', 'name'], 'restaurant_delivery_zones_company_branch_name_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_delivery_zones IS 'Zones de livraison - nom unique par (tenant, branche), frais en minor units (RESTO-211/#6176).';");
        }

        if (! schemaTableExists('restaurant_delivery_riders')) {
            Schema::create('restaurant_delivery_riders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('name', 150);
                $table->string('phone', 40)->nullable();
                $table->string('vehicle_code', 40)->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_delivery_riders_company_branch_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_delivery_riders IS 'Livreurs - employee_id reference HR par valeur, sans FK (RESTO-211/#6176).';");
        }

        if (! schemaTableExists('restaurant_deliveries')) {
            Schema::create('restaurant_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('rider_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('fee_minor')->default(0);
                $table->timestamp('delivered_at')->nullable();
                $table->string('delivered_to_contact', 150)->nullable();

                $table->timestamps();

                $table->index(['company_id', 'order_id'], 'restaurant_deliveries_company_order_idx');
                $table->index(['company_id', 'rider_id'], 'restaurant_deliveries_company_rider_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_deliveries IS 'Livraisons de commande - frais en minor units, statut pending/assigned/picked/delivered (RESTO-211/#6176).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_deliveries');
        Schema::dropIfExists('restaurant_delivery_riders');
        Schema::dropIfExists('restaurant_delivery_zones');
    }
};
