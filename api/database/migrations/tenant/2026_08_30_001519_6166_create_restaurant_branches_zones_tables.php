<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6166 (RESTO-201) - RestaurantManager : branches, zones, tables.
 *
 * Referentiel physique des points de vente :
 * - `restaurant_branches` : etablissement (code unique par tenant) ;
 * - `restaurant_zones` : salles/terrasses rattachees a une branche ;
 * - `restaurant_tables` : tables de service, rattachees a une branche (option zone).
 *
 * Toute table est tenant-scoped (`company_id` uuid non nullable, tenant-first),
 * sans FK (pattern Travel moderne) : colonnes simples + index nommes.
 * Migration idempotente (garde `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_branches')) {
            Schema::create('restaurant_branches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->string('name', 150);
                $table->string('address', 255)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('timezone', 50)->default('UTC');
                $table->char('currency', 3)->default('DZD');
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'restaurant_branches_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_branches IS 'Branches/etablissements de la verticale RestaurantManager - code unique par tenant (RESTO-201/#6166).';");
        }

        if (! schemaTableExists('restaurant_zones')) {
            Schema::create('restaurant_zones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->string('name', 120);
                $table->string('color', 7)->nullable();
                $table->smallInteger('sort_order')->default(0);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_zones_company_branch_idx');
                $table->unique(['company_id', 'branch_id', 'name'], 'restaurant_zones_company_branch_name_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_zones IS 'Zones (salles/terrasses) d une branche - nom unique par (tenant, branche) (RESTO-201/#6166).';");
        }

        if (! schemaTableExists('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->string('label', 80);
                $table->unsignedSmallInteger('capacity')->default(2);
                $table->unsignedSmallInteger('min_covers')->nullable();
                $table->boolean('is_mergeable')->default(false);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_tables_company_branch_idx');
                $table->unique(['company_id', 'branch_id', 'label'], 'restaurant_tables_company_branch_label_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_tables IS 'Tables de service - libelle unique par (tenant, branche) (RESTO-201/#6166).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('restaurant_zones');
        Schema::dropIfExists('restaurant_branches');
    }
};
