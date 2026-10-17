<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mission Leopardo — Travel OS (#8644) — disposition PHYSIQUE des sièges.
 *
 * Jusqu'ici, le plan de sièges était reconstruit depuis `seat_capacity` par une
 * heuristique (2+2 au-delà de 15 places, 2+1 en deçà). Ce n'est pas la
 * disposition du véhicule : un car de 30 places en 2+1 s'affichait en 2+2.
 *
 * `seat_layout` stocke la géométrie réelle, sous forme d'objet JSON :
 *   - `seats_per_row` : 1..6 (colonnes de sièges par rangée) ;
 *   - `aisle_after`   : 1..(seats_per_row - 1), ou absent = aucun couloir.
 *
 * Colonne NULLABLE : un véhicule non configuré reste à NULL et retombe sur la
 * déduction par capacité. Aucune ligne existante n'est réécrite — la migration
 * est donc sans perte et strictement rétrocompatible.
 *
 * Idempotente : garde `Schema::hasColumn` puis `DROP CONSTRAINT IF EXISTS`
 * avant `ADD CONSTRAINT` → rejouable sans erreur.
 *
 * Le CHECK reste VOLONTAIREMENT structurel (objet JSON ou NULL) : la validité
 * métier (1..6, couloir avant la dernière colonne) est tranchée par
 * `SeatLayout` et par les FormRequests. Un CHECK contenant des casts
 * `::int` sur des clés JSON arbitraires pourrait lever pendant l'évaluation
 * (PostgreSQL ne garantit pas le court-circuit de `AND`), ce qui rendrait
 * l'écriture fragile pour un gain nul.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'travel_vehicles_seat_layout_check';

    public function up(): void
    {
        if (! schemaTableExists('travel_vehicles')) {
            return;
        }

        if (! Schema::hasColumn('travel_vehicles', 'seat_layout')) {
            Schema::table('travel_vehicles', function (Blueprint $table): void {
                $table->jsonb('seat_layout')->nullable();
            });

            DB::statement(
                'COMMENT ON COLUMN travel_vehicles.seat_layout IS '
                ."'Disposition physique des sieges : seats_per_row (1..6), aisle_after (optionnel) — #8644.'"
            );
        }

        // Le CHECK est (re)posé à chaque passage : il reste l'ensemble exact des
        // formes acceptées, même si la colonne préexistait sans contrainte.
        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE travel_vehicles ADD CONSTRAINT '.self::CONSTRAINT
            .' CHECK (seat_layout IS NULL OR jsonb_typeof(seat_layout) = \'object\')'
        );
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_vehicles')) {
            return;
        }

        if (! Schema::hasColumn('travel_vehicles', 'seat_layout')) {
            return;
        }

        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);

        Schema::table('travel_vehicles', function (Blueprint $table): void {
            $table->dropColumn('seat_layout');
        });
    }
};
