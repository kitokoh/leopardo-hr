<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6172 (RESTO-207) - RestaurantManager : inventaires et lignes de comptage.
 *
 * - `restaurant_inventory_counts` : session de comptage par branche (statut
 *   draft/in_progress/approved, workflow de validation approbateur) ;
 * - `restaurant_inventory_count_items` : lignes comptees (quantite attendue,
 *   comptee, variance, reason_code).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_inventory_counts')) {
            Schema::create('restaurant_inventory_counts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->timestamp('counted_at')->useCurrent();
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('counted_by_user_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_inventory_counts_company_branch_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_inventory_counts IS 'Sessions de comptage d inventaire par branche (RESTO-207/#6172).';");
        }

        if (! schemaTableExists('restaurant_inventory_count_items')) {
            Schema::create('restaurant_inventory_count_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('count_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('expected_qty', 12, 3)->nullable();
                $table->decimal('counted_qty', 12, 3)->nullable();
                $table->decimal('variance_qty', 12, 3)->nullable();
                $table->string('reason_code', 30)->nullable();

                $table->timestamps();

                $table->index(['company_id', 'count_id'], 'restaurant_inventory_count_items_company_count_idx');
                $table->unique(['company_id', 'count_id', 'ingredient_id'], 'restaurant_inventory_count_items_company_count_ingredient_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_inventory_count_items IS 'Lignes de comptage - unique par (tenant, inventaire, ingredient), variance calculee (RESTO-207/#6172).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_inventory_count_items');
        Schema::dropIfExists('restaurant_inventory_counts');
    }
};
