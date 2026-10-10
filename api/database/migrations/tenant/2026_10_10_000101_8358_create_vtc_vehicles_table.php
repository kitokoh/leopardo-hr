<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_vehicles`.
 *
 * Véhicules de la flotte VTC/taxi : plaque unique par tenant, catégorie
 * (berline/van/moto), capacité, statut opérationnel. Tenant-first :
 * `company_id` UUID non nullable en tête de toutes les contraintes/index,
 * aucune FK (colonnes simples + index nommés — conventions
 * MIGRATIONS_CONVENTIONS), réentrante (schemaTableExists) + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_vehicles')) {
            Schema::create('vtc_vehicles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('plate', 20);
                $table->string('brand', 60)->nullable();
                $table->string('model', 60)->nullable();
                $table->string('color', 40)->nullable();
                $table->unsignedTinyInteger('seats')->default(4);
                $table->string('category', 20)->default('berline');
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'plate'], 'vtc_vehicles_company_plate_unique');
                $table->index(['company_id', 'status'], 'vtc_vehicles_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_vehicles IS 'Véhicules VTC/taxi - plaque unique par tenant, catégorie berline/van/moto (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_vehicles');
    }
};
