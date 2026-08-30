<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6227 (RESTO-806) — configurations d'intégration des apps de
 * livraison (marketplaces Uber Eats / Glovo).
 *
 * Une ligne par (tenant, provider) : activation, identifiant externe du
 * restaurant chez la marketplace (résout le tenant depuis le webhook) et
 * secret HMAC chiffré au repos (signature des webhooks entrants, pattern
 * RestaurantPaymentCallback). UNIQUE (company_id, provider) → idempotence
 * de configuration ; provider borné (CHECK uber_eats|glovo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_delivery_app_configs')) {
            Schema::create('restaurant_delivery_app_configs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // uber_eats | glovo — CHECK restaurant_delivery_app_configs_provider_check
                $table->string('provider', 30);
                $table->boolean('enabled')->default(false);
                $table->string('external_restaurant_id', 120);
                $table->text('webhook_secret_encrypted')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'provider'], 'resto_delivery_app_configs_company_provider_unique');
                $table->unique(['provider', 'external_restaurant_id'], 'resto_delivery_app_configs_provider_external_unique');
            });

            $schema = resolveTableSchema('restaurant_delivery_app_configs');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'restaurant_delivery_app_configs_provider_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"restaurant_delivery_app_configs\" ADD CONSTRAINT restaurant_delivery_app_configs_provider_check "
                    ."CHECK (provider IN ('uber_eats','glovo')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_delivery_app_configs');
    }
};
