<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_fare_profiles`.
 *
 * Grilles tarifaires : devise + montants en MINOR UNITS (base, par km, par
 * minute, minimum) — `is_default` désigne la grille appliquée par défaut
 * aux estimations (unicité logique portée par l'application, une seule
 * grille par défaut par tenant). Tenant-first, aucune FK, réentrante +
 * down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_fare_profiles')) {
            Schema::create('vtc_fare_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 80);
                $table->string('currency', 3);
                $table->unsignedInteger('base_minor')->default(0);
                $table->unsignedInteger('per_km_minor')->default(0);
                $table->unsignedInteger('per_minute_minor')->default(0);
                $table->unsignedInteger('minimum_minor')->default(0);
                $table->boolean('is_default')->default(false);

                $table->timestamps();

                $table->index(['company_id', 'is_default'], 'vtc_fare_profiles_company_default_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_fare_profiles IS 'Grilles tarifaires VTC - montants en minor units, une grille par defaut par tenant (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_fare_profiles');
    }
};
