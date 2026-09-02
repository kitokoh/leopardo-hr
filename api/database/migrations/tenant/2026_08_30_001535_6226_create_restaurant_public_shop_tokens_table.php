<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6226 (RESTO-805) - RestaurantManager : jetons de la boutique publique.
 *
 * La commande en ligne publique (menu public par tenant) est protegee par un
 * jeton signe par tenant (pattern TRAVEL-1001/#6114) : un jeton par tenant,
 * seul le hash SHA-256 est persiste, la rotation invalide l'ancien jeton.
 * `company_id` non nullable ; scope BelongsToCompany → aucune fuite
 * cross-tenant (critere d'acceptation RESTO-805).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_public_shop_tokens')) {
            Schema::create('restaurant_public_shop_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('token_hash', 64)->unique();
                $table->string('name', 100)->default('default');
                $table->boolean('active')->default(true);
                $table->timestamp('last_used_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'active'], 'restaurant_shop_tokens_company_active_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_public_shop_tokens IS 'Jetons signes de la boutique publique RestaurantManager : hash SHA-256 seul, rotation possible (RESTO-805/#6226).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_public_shop_tokens');
    }
};
