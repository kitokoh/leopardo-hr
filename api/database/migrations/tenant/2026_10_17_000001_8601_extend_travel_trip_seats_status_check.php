<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mission Leopardo — Travel OS (#8601) — élargissement du CHECK de statut.
 *
 * Le plan de sièges doit distinguer, pour une place réellement bloquée, un
 * état `blocked` (blocage opérateur) et `unavailable` (indisponibilité
 * documentée) au-delà des trois valeurs historiques
 * `free`/`reserved`/`sold` — qui restent la source de vérité du stock et ne
 * changent jamais de sens.
 *
 * Les états de PRÉSENTATION (`selected`, `held`, `pending_payment`) ne sont
 * PAS ajoutés : ils ne sont jamais écrits dans cette colonne (ils sont
 * projetés par l'UI), donc le CHECK reste l'ensemble exact des valeurs
 * persistables.
 *
 * Migration idempotente : `DROP CONSTRAINT IF EXISTS` puis `ADD CONSTRAINT`
 * → rejouable sans erreur, et sans toucher aux lignes existantes.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'travel_trip_seats_status_check';

    public function up(): void
    {
        if (! schemaTableExists('travel_trip_seats')) {
            return;
        }

        DB::statement('ALTER TABLE travel_trip_seats DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE travel_trip_seats ADD CONSTRAINT '.self::CONSTRAINT
            ." CHECK (status IN ('free', 'reserved', 'sold', 'blocked', 'unavailable'))"
        );
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_trip_seats')) {
            return;
        }

        DB::statement('ALTER TABLE travel_trip_seats DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE travel_trip_seats ADD CONSTRAINT '.self::CONSTRAINT
            ." CHECK (status IN ('free', 'reserved', 'sold'))"
        );
    }
};
