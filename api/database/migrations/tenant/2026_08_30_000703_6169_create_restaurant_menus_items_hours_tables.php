<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6169 (RESTO-204) - RestaurantManager : menus, lignes de menu, horaires.
 *
 * - `restaurant_menus` : carte/formule (code unique par tenant, fenetre starts_at/ends_at) ;
 * - `restaurant_menu_items` : produits composant un menu, ordonnes par position ;
 * - `restaurant_hours` : horaires d ouverture par branche et jour de semaine.
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_menus')) {
            Schema::create('restaurant_menus', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->unsignedInteger('price_minor')->default(0);
                $table->char('currency', 3)->default('DZD');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_menus_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_menus IS 'Menus/formules - code unique par tenant, prix en minor units (RESTO-204/#6169).';");
        }

        if (! schemaTableExists('restaurant_menu_items')) {
            Schema::create('restaurant_menu_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedSmallInteger('position')->default(0);
                $table->boolean('is_optional')->default(false);

                $table->timestamps();

                $table->unique(['company_id', 'menu_id', 'product_id'], 'restaurant_menu_items_company_menu_product_unique');
                $table->index(['company_id', 'menu_id'], 'restaurant_menu_items_company_menu_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_menu_items IS 'Produits d un menu - unique par (tenant, menu, produit), ordre via position (RESTO-204/#6169).';");
        }

        if (! schemaTableExists('restaurant_hours')) {
            Schema::create('restaurant_hours', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedSmallInteger('day_of_week');
                $table->time('opens_at')->nullable();
                $table->time('closes_at')->nullable();
                $table->boolean('is_closed')->default(false);

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_hours_company_branch_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_hours IS 'Horaires d ouverture par branche et jour de semaine (RESTO-204/#6169).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_hours');
        Schema::dropIfExists('restaurant_menu_items');
        Schema::dropIfExists('restaurant_menus');
    }
};
