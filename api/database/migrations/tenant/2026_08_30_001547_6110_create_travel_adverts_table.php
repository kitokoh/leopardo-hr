<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-907 (#6110) — Annonces payantes (spec §3) : soumission, prix
 * calculé serveur (snapshot du tarif), paiement, validation par
 * `travel.manage`, publication horodatée avec expiration. Une annonce n'est
 * visible qu'une fois payée ET validée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migration fantôme neutralisée (round 18, PM 2026-09-05) : 3 générations
        // parallèles créaient travel_adverts. Le schéma canonique est porté par
        // 2026_08_30_001548 (contenu_redacted/price_minor/validity_days/validated —
        // cf. modèle TravelAdvert + enums AdvertStatus). Celle-ci (draft/published)
        // s'exécutait en premier et figeait un schéma obsolète. Voir R7 fuel_imports.
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_adverts');
    }
};
