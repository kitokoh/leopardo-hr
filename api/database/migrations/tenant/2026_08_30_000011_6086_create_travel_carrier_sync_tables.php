<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6086 (TRAVEL-807) — Synchronisation des trajets transporteurs
 * (API entrante).
 *
 * - `travel_carrier_tokens` : jeton par (tenant, transporteur) pour l'API
 *   entrante — seul le hash SHA-256 est persisté (jamais le jeton en clair).
 * - `travel_trips.external_id` : clé externe (id du trajet chez le
 *   transporteur) pour un upsert idempotent — une synchronisation rejouée
 *   ne duplique jamais un trajet (contrainte unique tenant/carrier/external).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_carrier_tokens')) {
            Schema::create('travel_carrier_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('carrier_id');
                $table->string('name', 80)->nullable();
                $table->string('token_hash', 64);
                $table->boolean('active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'carrier_id'], 'travel_carrier_tokens_company_carrier_unique');
            });
        }

        if (! schemaHasColumn('travel_trips', 'external_id')) {
            Schema::table('travel_trips', function (Blueprint $table): void {
                $table->string('external_id', 120)->nullable();
                $table->unique(
                    ['company_id', 'carrier_id', 'external_id'],
                    'travel_trips_company_carrier_external_unique',
                );
            });
        }

        // Routes synchronisées par un transporteur (upsert par clé externe).
        if (! schemaHasColumn('travel_routes', 'external_id')) {
            Schema::table('travel_routes', function (Blueprint $table): void {
                $table->unsignedBigInteger('carrier_id')->nullable();
                $table->string('external_id', 120)->nullable();
                $table->unique(
                    ['company_id', 'carrier_id', 'external_id'],
                    'travel_routes_company_carrier_external_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_routes', function (Blueprint $table): void {
            $table->dropUnique('travel_routes_company_carrier_external_unique');
            $table->dropColumn(['carrier_id', 'external_id']);
        });

        Schema::table('travel_trips', function (Blueprint $table): void {
            $table->dropUnique('travel_trips_company_carrier_external_unique');
            $table->dropColumn('external_id');
        });

        Schema::dropIfExists('travel_carrier_tokens');
    }
};
