<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #7737 — marketplace inter-agences : nouvelle source de réservation
 * `marketplace` (achat via la place de marché publique, tenant résolu par
 * trajet).
 *
 * La contrainte CHECK `travel_bookings_source_check` (posée par la génération
 * TRAVEL-209/#6022) fige la liste des sources : elle est recréée avec la
 * valeur supplémentaire. `DROP … IF EXISTS` car la génération consolidée
 * (#7452) peut avoir créé la table sans cette contrainte.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE travel_bookings DROP CONSTRAINT IF EXISTS travel_bookings_source_check');
        DB::statement("ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_source_check CHECK (booking_source IN ('online', 'office', 'phone', 'partner', 'marketplace'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE travel_bookings DROP CONSTRAINT IF EXISTS travel_bookings_source_check');
        DB::statement("ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_source_check CHECK (booking_source IN ('online', 'office', 'phone', 'partner'))");
    }
};
