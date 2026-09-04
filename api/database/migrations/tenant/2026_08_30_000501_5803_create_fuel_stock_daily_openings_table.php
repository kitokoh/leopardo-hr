<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — ouvertures quotidiennes de cuves.
 *
 * `fuel_stock_daily_openings` : niveau de cuve au DÉBUT de la journée
 * commerciale, figé au PREMIER mouvement du jour (vente ou livraison).
 * Le rapprochement (FUEL-009) compare ensuite
 * attendu = ouverture + livraisons − ventes vs mesuré (niveau courant) :
 * sans ce snapshot, l'ouverture dérivée du niveau courant rend l'écart
 * toujours nul (circulaire) et la détection de vol/fuite impossible.
 *
 * Une ligne par (company_id, tank_id, open_date) — créée idempotemment par
 * le premier mouvement. Migration additive + idempotente (#1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_stock_daily_openings')) {
            Schema::create('fuel_stock_daily_openings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('tank_id')->index();
                $table->date('open_date');
                $table->bigInteger('opening_level_minor');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(['company_id', 'tank_id', 'open_date'], 'fuel_stock_openings_unique');

                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_openings_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_stock_daily_openings IS 'Niveau de cuve en début de journée, figé au premier mouvement (base du rapprochement FUEL-009) — #5803.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_daily_openings');
    }
};
