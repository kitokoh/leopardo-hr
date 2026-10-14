<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8423 (M-13/M-14) — Marketplace publique VTC : colonne
 * `tracking_code_hash` (SHA-256, 64 hex) sur `vtc_rides`.
 *
 * Suivi invité par référence + code (pattern #7395 / BOS-050 tranche 7) :
 * toute course créée porte le hash SHA-256 d'un code de suivi aléatoire
 * (8 symboles non ambigus, alphabet TravelTicket — recopié à la main par
 * un passager). Le code en clair n'est JAMAIS persisté ; il est présenté
 * une seule fois, dans la réponse de création de la réservation invitée
 * (`POST /public/vtc/marketplace/rides`, M-13) ; la vérification est une
 * comparaison de hash timing-safe (`TrackingSecretService`, socle
 * mutualisé BOS-050) — jamais un lookup par code en clair.
 *
 * Note d'ordonnancement : la colonne est livrée DÈS M-13 (et non M-14)
 * parce que la réservation invitée génère et retourne le code à la
 * création — le hash doit donc être persisté au moment où l'endpoint le
 * présente (le suivi M-14 ne fait que le consommer).
 *
 * Pas d'index dédié : la recherche de suivi est bornée par l'unique
 * tenant-first existant (company_id, reference) — le hash est comparé en
 * PHP, jamais requêté (« index (company_id, reference, tracking_code) ou
 * équivalent tenant-first » du plan : l'équivalent est déjà en place).
 *
 * Additive (nullable, aucun backfill : les courses antérieures gardent un
 * hash NULL et ne sont simplement pas suivies publiquement — fail-closed,
 * un hash NULL ne matche jamais), idempotente, down() limité à la colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_rides')) {
            return;
        }

        if (schemaHasColumn('vtc_rides', 'tracking_code_hash')) {
            return;
        }

        Schema::table('vtc_rides', function (Blueprint $table): void {
            $table->string('tracking_code_hash', 64)->nullable();
        });

        DB::statement("COMMENT ON COLUMN vtc_rides.tracking_code_hash IS 'Hash SHA-256 du code de suivi public de la course (#8423, marketplace VTC) — NULL = course anterieure au suivi invite, jamais de code en clair persiste.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('vtc_rides')) {
            return;
        }

        if (! schemaHasColumn('vtc_rides', 'tracking_code_hash')) {
            return;
        }

        Schema::table('vtc_rides', function (Blueprint $table): void {
            $table->dropColumn('tracking_code_hash');
        });
    }
};
