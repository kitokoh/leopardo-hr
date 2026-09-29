<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-050 (#8208, tranche 7) — Suivi public des commandes restaurant :
 * colonne `tracking_secret_hash` (SHA-256, 64 hex) sur `restaurant_orders`.
 *
 * Le suivi public restaurant était le maillon faible des surfaces publiques
 * : référence SEULE (shop/kiosque) ou référence + slug (RESTO-902), sans
 * secret — contrairement aux autres verticales (TravelTicket,
 * HospitalityReservation `tracking_code_hash`). Désormais toute commande
 * créée via une surface publique porte le hash SHA-256 d'un secret aléatoire
 * (`TrackingSecretService`, socle mutualisé) ; le secret en clair n'est
 * JAMAIS persisté et n'est présenté qu'une fois, dans la réponse de
 * création.
 *
 * Additive, idempotente (Render rejoue des migrations, cf. AGENTS.md),
 * aucun backfill : les commandes existantes gardent un hash NULL et restent
 * suivies par l'ancien flux (référence seule) pendant la fenêtre de
 * dépréciation de 90 jours (en-têtes `Deprecation`/`Sunset` — voir
 * docs/architecture/PUBLIC_COMMERCE_CONVENTIONS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_orders')) {
            return;
        }

        if (schemaHasColumn('restaurant_orders', 'tracking_secret_hash')) {
            return;
        }

        Schema::table('restaurant_orders', function (Blueprint $table): void {
            $table->string('tracking_secret_hash', 64)->nullable();
        });

        DB::statement("COMMENT ON COLUMN restaurant_orders.tracking_secret_hash IS 'Hash SHA-256 du secret de suivi public (BOS-050 tranche 7, #8208) — NULL = commande antérieure, ancien flux référence seule déprécié (90 j).';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_orders')) {
            return;
        }

        if (! schemaHasColumn('restaurant_orders', 'tracking_secret_hash')) {
            return;
        }

        Schema::table('restaurant_orders', function (Blueprint $table): void {
            $table->dropColumn('tracking_secret_hash');
        });
    }
};
