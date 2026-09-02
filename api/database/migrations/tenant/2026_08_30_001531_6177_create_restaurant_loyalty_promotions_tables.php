<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6177 (RESTO-212) - RestaurantManager : fidelite et promotions.
 *
 * - `restaurant_loyalty_programs` : reglage du programme (points par montant,
 *   taux de redemption) ;
 * - `restaurant_loyalty_customers` : solde de points par client (unique par tenant) ;
 * - `restaurant_loyalty_points_movements` : journal des mouvements de points (delta) ;
 * - `restaurant_promotions` : offres (code unique par tenant, type percent/amount,
 *   fenetre de validite).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_loyalty_programs')) {
            Schema::create('restaurant_loyalty_programs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedInteger('points_per_amount_minor')->default(100);
                $table->unsignedInteger('redeem_rate_minor')->default(100);
                $table->boolean('is_active')->default(true);

                $table->timestamps();
            });

            DB::statement("COMMENT ON TABLE restaurant_loyalty_programs IS 'Reglage du programme de fidelite par tenant (RESTO-212/#6177).';");
        }

        if (! schemaTableExists('restaurant_loyalty_customers')) {
            Schema::create('restaurant_loyalty_customers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('customer_contact_id');
                $table->integer('points')->default(0);
                $table->string('tier_code', 20)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'customer_contact_id'], 'restaurant_loyalty_customers_company_customer_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_loyalty_customers IS 'Solde de points par client - unique par (tenant, contact client) (RESTO-212/#6177).';");
        }

        if (! schemaTableExists('restaurant_loyalty_points_movements')) {
            Schema::create('restaurant_loyalty_points_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('customer_id');
                $table->integer('delta');
                $table->string('reason_code', 30);
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'customer_id'], 'restaurant_loyalty_points_movements_company_customer_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_loyalty_points_movements IS 'Journal des mouvements de points de fidelite - delta signe (RESTO-212/#6177).';");
        }

        if (! schemaTableExists('restaurant_promotions')) {
            Schema::create('restaurant_promotions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('code', 40);
                $table->string('title', 150);
                $table->string('discount_type', 20)->default('percent');
                $table->unsignedInteger('value_minor')->default(0);
                $table->unsignedInteger('min_order_minor')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_promotions_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_promotions IS 'Promotions - code unique par tenant, remise percent/amount en minor units (RESTO-212/#6177).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_promotions');
        Schema::dropIfExists('restaurant_loyalty_points_movements');
        Schema::dropIfExists('restaurant_loyalty_customers');
        Schema::dropIfExists('restaurant_loyalty_programs');
    }
};
