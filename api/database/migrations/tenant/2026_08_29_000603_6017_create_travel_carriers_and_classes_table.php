<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6017 (TRAVEL-204) — travel_carriers + travel_classes.
 *
 * Compagnies de transport (bus/train/avion/bateau) et classes de service
 * (référentiel tenant-scoped, ex. Économique/Business) — spec §5.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_carriers')) {
            Schema::create('travel_carriers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->string('name', 120);
                $table->string('type', 20)->default('bus');
                $table->string('contact_phone', 40)->nullable();
                $table->unsignedBigInteger('logo_asset_id')->nullable();
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_carriers_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_carriers IS 'Compagnies de transport de la verticale TravelAgency — code unique par tenant (TRAVEL-204/#6017).'");
        }

        if (! schemaTableExists('travel_classes')) {
            Schema::create('travel_classes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('code', 40);
                $table->string('label', 120);
                $table->string('color', 7)->nullable();
                $table->unsignedSmallInteger('priority')->default(0);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_classes_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_classes IS 'Classes de service de la verticale TravelAgency — code unique par tenant (TRAVEL-204/#6017).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_classes');
        Schema::dropIfExists('travel_carriers');
    }
};
