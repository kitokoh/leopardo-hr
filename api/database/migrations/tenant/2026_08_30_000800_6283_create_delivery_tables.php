<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6283 (DELIVERY-102) — BC-26 DELIVERY : schéma tenant du module de livraison.
 *
 * - `delivery_deliveries` : livraisons/colis (agrégat racine) — référence
 *   `DLV-YYYY-NNNNNN` unique par tenant, source (manual/restaurant/retail/
 *   ecommerce/crm/field) + source_reference avec unique (company_id, source,
 *   source_reference) → zéro doublon par commande source ;
 * - `delivery_routes` : tournées (1 livreur + 1 véhicule par date, uniques
 *   tenant-first) ;
 * - `delivery_stops` : arrêts ordonnés (route, livraison, ETA/ETD, POD) ;
 * - `delivery_events` : tracking idempotent (unique (company_id, delivery_id,
 *   type, event_at)) ;
 * - `delivery_cod_settlements` : règlements COD (expected/collected/commission
 *   en minor units, référence écriture BC-08).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommés), réentrante
 * (schemaTableExists) + down() complet — conventions MIGRATIONS_CONVENTIONS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('delivery_deliveries')) {
            Schema::create('delivery_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->string('source', 20);
                $table->string('source_reference', 120)->nullable();
                $table->string('type', 20)->default('parcel');
                $table->string('status', 20)->default('created');

                $table->unsignedInteger('weight_grams')->nullable();
                $table->unsignedInteger('volume_cm3')->nullable();
                $table->unsignedInteger('declared_value_minor')->default(0);
                $table->unsignedInteger('cod_amount_minor')->nullable();

                $table->string('pickup_contact', 150)->nullable();
                $table->text('pickup_address')->nullable();
                $table->string('dropoff_contact', 150);
                $table->string('dropoff_phone', 40)->nullable();
                $table->text('dropoff_address');
                $table->timestamp('window_from')->nullable();
                $table->timestamp('window_to')->nullable();

                $table->uuid('idempotency_key')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('returned_at')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'delivery_deliveries_company_reference_unique');
                $table->unique(['company_id', 'source', 'source_reference'], 'delivery_deliveries_company_source_ref_unique');
                $table->index(['company_id', 'status', 'created_at'], 'delivery_deliveries_company_status_date_idx');
                $table->index(['company_id', 'source'], 'delivery_deliveries_company_source_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_deliveries IS 'Livraisons - reference unique par tenant, source+source_reference idempotents, COD en minor units (DELIVERY-102/#6283).';");
        }

        if (! schemaTableExists('delivery_routes')) {
            Schema::create('delivery_routes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->date('route_date');
                $table->unsignedBigInteger('driver_id')->nullable(); // employee HR par valeur, sans FK
                $table->string('vehicle_code', 40)->nullable(); // référence BC-18 par valeur
                $table->string('zone', 120)->nullable();
                $table->string('status', 20)->default('draft');

                $table->unsignedInteger('deliveries_count')->default(0);
                $table->unsignedInteger('delivered_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('cod_collected_minor')->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->uuid('idempotency_key')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'route_date', 'driver_id'], 'delivery_routes_company_date_driver_unique');
                $table->index(['company_id', 'route_date'], 'delivery_routes_company_date_idx');
                $table->index(['company_id', 'status'], 'delivery_routes_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_routes IS 'Tournees - 1 livreur + 1 vehicule par date, cloture idempotente, totaux denormalises (DELIVERY-102/#6283).';");
        }

        if (! schemaTableExists('delivery_stops')) {
            Schema::create('delivery_stops', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('route_id');
                $table->unsignedBigInteger('delivery_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 20)->default('pending');

                $table->text('address');
                $table->string('contact', 150)->nullable();
                $table->string('phone', 40)->nullable();
                $table->timestamp('eta')->nullable();
                $table->timestamp('etd')->nullable();
                $table->unsignedBigInteger('proof_id')->nullable(); // référence BC-20 documents par valeur
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('delivered_at')->nullable();

                $table->timestamps();

                $table->unique(['route_id', 'delivery_id'], 'delivery_stops_route_delivery_unique');
                $table->index(['company_id', 'route_id'], 'delivery_stops_company_route_idx');
                $table->index(['company_id', 'status'], 'delivery_stops_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_stops IS 'Arrets de tournee - ordre de passage, ETA/ETD, POD (proof_id BC-20), statut (DELIVERY-102/#6283).';");
        }

        if (! schemaTableExists('delivery_events')) {
            Schema::create('delivery_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('delivery_id');
                $table->string('type', 30);
                $table->timestamp('event_at');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('origin', 20)->default('mobile'); // mobile | edge | api
                $table->uuid('idempotency_key')->nullable();
                $table->json('payload')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'delivery_id', 'type', 'event_at'], 'delivery_events_company_delivery_type_at_unique');
                $table->index(['company_id', 'delivery_id'], 'delivery_events_company_delivery_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_events IS 'Evenements de tracking - idempotents (unique company/delivery/type/event_at), geo optionnelle (DELIVERY-102/#6283).';");
        }

        if (! schemaTableExists('delivery_cod_settlements')) {
            Schema::create('delivery_cod_settlements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('route_id');
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedInteger('expected_minor')->default(0);
                $table->unsignedInteger('collected_minor')->default(0);
                $table->unsignedInteger('commission_minor')->default(0);
                $table->string('status', 20)->default('pending'); // pending|collected|settled|reconciled
                $table->string('accounting_ref', 120)->nullable(); // référence écriture BC-08
                $table->timestamp('settled_at')->nullable();
                $table->uuid('idempotency_key')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'route_id'], 'delivery_cod_settlements_company_route_unique');
                $table->index(['company_id', 'status'], 'delivery_cod_settlements_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_cod_settlements IS 'Reglements COD - expected/collected/commission en minor units, posting BC-08 idempotent (DELIVERY-102/#6283).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_cod_settlements');
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('delivery_stops');
        Schema::dropIfExists('delivery_routes');
        Schema::dropIfExists('delivery_deliveries');
    }
};
