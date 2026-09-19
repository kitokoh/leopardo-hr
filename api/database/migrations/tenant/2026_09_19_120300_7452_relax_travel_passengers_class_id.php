<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7452 — `travel_passengers.class_id` accepte NULL : l'import legacy crée des
 * passagers sans classe.
 *
 * Le chemin API (`CreateBookingAction`) exige une classe par passager et
 * continue de la fournir. Mais `TravelLegacyImportService::importBooking()`
 * (chemin d'import des exports legacy, #6113) écrit explicitement
 * `class_id => null` — les données historiques ne portent pas de classes — et
 * chaque import de réservation échouait en 23502 (`null value in column
 * "class_id"`), mesuré ×5 dans `TravelLegacyImportTest` une fois les défauts
 * amont (routes/trips sans code) corrigés. Même arbitrage que
 * `2026_09_15_000001_7452_relax_legacy_travel_not_null` : le schéma doit
 * refléter la réalité des écrivains ; l'intégrité du chemin API reste
 * garantie par sa validation (`class_id` requis).
 *
 * Idempotent (DROP NOT NULL rejouable), forward-only.
 *
 * @see https://github.com/kitokoh/leopardo-hr/issues/7452
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_passengers') && schemaHasColumn('travel_passengers', 'class_id')) {
            DB::statement('ALTER TABLE travel_passengers ALTER COLUMN class_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Forward-only : remettre NOT NULL exigerait une classe pour des
        // passagers importés qui n'en ont jamais eu.
    }
};
