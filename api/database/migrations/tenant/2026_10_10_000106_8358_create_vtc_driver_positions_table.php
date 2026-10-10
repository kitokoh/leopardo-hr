<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_driver_positions`.
 *
 * Historique des positions chauffeurs (données personnelles — RGPD :
 * rétention `vtc.positions_retention_days`, purge planifiée VTC-06).
 * **Unique (company_id, driver_id, recorded_at)** : l'ingestion est
 * IDEMPOTENTE — un rejeu réseau de la même position ne duplique jamais
 * (VTC-05). Coordonnées décimales WGS 84 (dérivation v1 — voir 000102).
 * Tenant-first, aucune FK, réentrante + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_driver_positions')) {
            Schema::create('vtc_driver_positions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('driver_id');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->timestamp('recorded_at');
                $table->string('source', 20)->default('app');

                $table->timestamps();

                $table->unique(['company_id', 'driver_id', 'recorded_at'], 'vtc_driver_positions_company_driver_at_unique');
            });

            DB::statement("COMMENT ON TABLE vtc_driver_positions IS 'Positions chauffeurs VTC - unique (company_id, driver_id, recorded_at) idempotent, retention RGPD 30 j purge VTC-06 (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_driver_positions');
    }
};
