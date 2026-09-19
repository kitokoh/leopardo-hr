<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7641 (TRAVEL-DISTRIBUTION) — travel_distributor_keys.
 *
 * Clés API de LECTURE multiples par distributeur (plateformes de vente
 * externes) : nom, scopes de lecture (catalogue/booking), rotation,
 * révocation et stats d'usage. Miroir du pattern travel_carrier_api_keys
 * (TRAVEL-807/#6086) : token hashé au repos (SHA-256), jamais en clair.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_distributor_keys')) {
            Schema::create('travel_distributor_keys', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 120);
                $table->string('api_key_hash', 64);
                $table->json('scopes');
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->unsignedBigInteger('usage_count')->default(0);
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'api_key_hash'], 'travel_distributor_keys_company_hash_unique');
                $table->index(['company_id', 'enabled'], 'travel_distributor_keys_company_enabled_idx');
            });

            DB::statement("COMMENT ON TABLE travel_distributor_keys IS 'Clés API de lecture des distributeurs — hash uniquement (TRAVEL-DISTRIBUTION/#7641).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_distributor_keys');
    }
};
