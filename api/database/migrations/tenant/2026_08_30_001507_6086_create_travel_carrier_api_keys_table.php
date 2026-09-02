<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6086 (TRAVEL-807) — travel_carrier_api_keys + clés externes de sync.
 *
 * Clés API des transporteurs (token partenaire, hashé au repos — jamais en
 * clair, pattern ZKTeco sync_token_hash) ; colonnes external_ref /
 * external_carrier_code sur routes et trajets avec index unique partiel
 * (company_id, external_ref) : un trajet synchronisé 2× est mis à jour, pas
 * dupliqué (acceptance TRAVEL-807).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_carrier_api_keys')) {
            Schema::create('travel_carrier_api_keys', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('carrier_id');
                $table->string('api_key_hash', 64);
                $table->string('label', 120)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'api_key_hash'], 'travel_carrier_api_keys_company_hash_unique');
                $table->index(['company_id', 'carrier_id'], 'travel_carrier_api_keys_company_carrier_idx');
            });

            DB::statement("COMMENT ON TABLE travel_carrier_api_keys IS 'Clés API transporteurs — hash uniquement (TRAVEL-807/#6086).'");
        }

        if (! Schema::hasColumn('travel_routes', 'external_ref')) {
            Schema::table('travel_routes', function (Blueprint $table): void {
                $table->string('external_ref', 100)->nullable()->after('code');
                $table->string('external_carrier_code', 60)->nullable()->after('external_ref');
            });

            DB::statement('CREATE UNIQUE INDEX travel_routes_company_external_ref_unique ON travel_routes (company_id, external_ref) WHERE external_ref IS NOT NULL');
        }

        if (! Schema::hasColumn('travel_trips', 'external_ref')) {
            Schema::table('travel_trips', function (Blueprint $table): void {
                $table->string('external_ref', 100)->nullable()->after('code');
                $table->string('external_carrier_code', 60)->nullable()->after('external_ref');
            });

            DB::statement('CREATE UNIQUE INDEX travel_trips_company_external_ref_unique ON travel_trips (company_id, external_ref) WHERE external_ref IS NOT NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS travel_trips_company_external_ref_unique');
        DB::statement('DROP INDEX IF EXISTS travel_routes_company_external_ref_unique');
        Schema::table('travel_trips', function (Blueprint $table): void {
            $table->dropColumn(['external_ref', 'external_carrier_code']);
        });
        Schema::table('travel_routes', function (Blueprint $table): void {
            $table->dropColumn(['external_ref', 'external_carrier_code']);
        });
        Schema::dropIfExists('travel_carrier_api_keys');
    }
};
