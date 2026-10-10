<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_drivers`.
 *
 * Chauffeurs VTC/taxi : identité, statut (offline/available/busy/suspended),
 * véhicule courant (nullable), dernière position connue en colonnes
 * décimales WGS 84 + horodatage.
 *
 * DÉVIATION v1 DOCUMENTÉE (spec §5.1) : les positions sont stockées en
 * `decimal(10,7)` et non en `geography(Point,4326)` — le core géospatial
 * (BC-33) reconstruit la géographie À LA VOLÉE en requête
 * (ST_SetSRID(ST_MakePoint(...)) via EloquentNearestSearch), ce qui garde
 * PostGIS au cœur du matching SANS écriture SQL brute côté Eloquent
 * (décision D3 : zéro nouvelle dépendance composer). Migration vers
 * geography native + index GIST fonctionnel = évolution ultérieure.
 *
 * Tenant-first, aucune FK, réentrante + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_drivers')) {
            Schema::create('vtc_drivers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name', 120);
                $table->string('phone', 40)->nullable();
                $table->string('status', 20)->default('offline');
                $table->unsignedBigInteger('vehicle_id')->nullable();

                $table->decimal('current_latitude', 10, 7)->nullable();
                $table->decimal('current_longitude', 10, 7)->nullable();
                $table->timestamp('location_updated_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'vtc_drivers_company_status_idx');
                $table->index(['company_id', 'user_id'], 'vtc_drivers_company_user_idx');
                $table->index(['company_id', 'vehicle_id'], 'vtc_drivers_company_vehicle_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_drivers IS 'Chauffeurs VTC/taxi - statut dispatch, derniere position connue (decimal WGS 84, geography reconstruite par le core geo BC-33) (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_drivers');
    }
};
