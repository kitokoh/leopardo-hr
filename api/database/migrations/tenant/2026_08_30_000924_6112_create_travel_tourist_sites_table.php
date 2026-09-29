<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6112 (TRAVEL-909) — Sites touristiques (annuaire legacy gv-back).
 *
 * `travel_tourist_sites` : nom, description redigée, ville (référentiel
 * tenant-scoped), coordonnées, image, statut. Recherche par ville.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Issue #7452 — la table est créée par
        // `2026_08_30_000018_6112_create_travel_tourist_sites_table.php` ; cette
        // génération ne rattrape que `image_asset_id` (les autres colonnes existent).
        if (schemaTableExists('travel_tourist_sites')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_tourist_sites', 'image_asset_id')) {
                    $table->unsignedBigInteger('image_asset_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // #8207 (BOS-018) — symétrique EXACT de up() : cette génération ne
        // rattrape que `image_asset_id`, son rollback ne droppe QUE cette
        // colonne. La version précédente droppait aussi `company_id`, `name`,
        // `status`, `created_at`… — des colonnes créées par la migration
        // propriétaire `2026_08_30_000018_6112_create_travel_tourist_sites_table.php`.
        // Un rollback du batch laissait donc la table vivante mais vidée de
        // son schéma (la migration propriétaire restant « migrée », aucun
        // mécanisme ne restaurait les colonnes).
        if (schemaHasColumn('travel_tourist_sites', 'image_asset_id')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('image_asset_id');
            });
        }
    }
};
