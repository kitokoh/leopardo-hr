<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6226 (RESTO-805) — Commande en ligne publique : jeton signé par tenant.
 *
 * Un jeton actif par tenant (hash SHA-256 seul persisté, jamais le jeton en
 * clair — pattern TravelPublicShopToken #6114). Les endpoints publics
 * (`/public/restaurant/*`) résolvent le tenant par ce jeton : aucune donnée
 * d'un autre tenant n'est accessible (critère d'acceptation RESTO-805).
 * La rotation (régénération) invalide l'ancien jeton.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_public_shop_tokens')) {
            Schema::create('restaurant_public_shop_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->unique();
                $table->string('token_hash', 64);
                $table->string('name', 80)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_public_shop_tokens');
    }
};
