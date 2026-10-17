<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mission Leopardo — Travel OS (#8652) — sièges physiquement absents.
 *
 * `absent_seats` vit À L'INTÉRIEUR de la colonne JSONB `seat_layout` posée par
 * #8644 : aucune colonne nouvelle n'est nécessaire, donc aucune donnée n'est
 * déplacée ni réécrite. Cette migration ne fait que deux choses, toutes deux
 * idempotentes :
 *
 *   1. (re)poser le CHECK structurel `seat_layout IS NULL OR jsonb_typeof(...)
 *      = 'object'` — l'ensemble exact des formes acceptées reste le même ;
 *   2. documenter la forme étendue dans le COMMENT de la colonne, pour qu'un
 *      lecteur de la base sache que `absent_seats` en fait partie.
 *
 * Rétrocompatibilité TOTALE : un véhicule dont `seat_layout` est NULL (tous les
 * véhicules antérieurs à #8644) reste NULL et retombe sur la déduction par
 * capacité. Aucune ligne existante n'est touchée.
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
            // La colonne n'existe pas (base antérieure à #8644) : rien à
            // documenter, et surtout rien à créer ici — #8644 en est le
            // propriétaire. On ne duplique pas sa responsabilité.
            return;
        }

        DB::statement('ALTER TABLE travel_vehicles DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE travel_vehicles ADD CONSTRAINT '.self::CONSTRAINT
            .' CHECK (seat_layout IS NULL OR jsonb_typeof(seat_layout) = \'object\')'
        );

        DB::statement(
            'COMMENT ON COLUMN travel_vehicles.seat_layout IS '
            ."'Disposition physique des sieges : seats_per_row (1..6), aisle_after (optionnel), absent_seats (optionnel, numeros de sieges physiquement absents) — #8644/#8652.'"
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

        // On ne retire QUE la documentation : la colonne et sa contrainte
        // appartiennent à #8644, et `absent_seats` n'a jamais eu de structure
        // propre à défaire (il vit dans le JSON).
        DB::statement(
            'COMMENT ON COLUMN travel_vehicles.seat_layout IS '
            ."'Disposition physique des sieges : seats_per_row (1..6), aisle_after (optionnel) — #8644.'"
        );
    }
};
