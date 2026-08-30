<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-905 (#6108) — Référentiels des annonces payantes (legacy gv-back,
 * spec §3) : types d'annonce (image/texte/…) et positions de publication.
 * Tenant-scoped, unicité (company_id, code).
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
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'code'], 'travel_advert_types_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_advert_types IS 'Types d'annonces payantes (TRAVEL-905/#6108).'");
        }

        if (! schemaTableExists('travel_advert_positions')) {
            Schema::create('travel_advert_positions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'code'], 'travel_advert_positions_company_code_unique');
            });

            DB::statement("COMMENT ON TABLE travel_advert_positions IS 'Positions de publication des annonces payantes (TRAVEL-905/#6108).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_advert_positions');
        Schema::dropIfExists('travel_advert_types');
    }
};
