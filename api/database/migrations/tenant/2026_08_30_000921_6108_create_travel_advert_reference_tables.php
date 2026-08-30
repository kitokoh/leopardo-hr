<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6108 (TRAVEL-905) — Annonces : référentiels types + positions.
 *
 * `travel_advert_types` (nature d'annonce) et `travel_advert_positions`
 * (emplacements de publication) — tenant-scoped, code unique par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_advert_types')) {
            Schema::create('travel_advert_types', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('label', 120);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->unique(['company_id', 'code'], 'travel_advert_types_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_advert_types IS 'Types d'annonces payantes (TRAVEL-905/#6108).'");
        }

        if (! schemaTableExists('travel_advert_positions')) {
            Schema::create('travel_advert_positions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('label', 120);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->unique(['company_id', 'code'], 'travel_advert_positions_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_advert_positions IS 'Positions de publication des annonces (TRAVEL-905/#6108).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_advert_positions');
        Schema::dropIfExists('travel_advert_types');
    }
};
