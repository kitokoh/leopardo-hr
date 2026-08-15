<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #3811 — `commissions.payment_id` n'a pas d'index unique : deux webhooks de
 * paiement concurrents (Stripe/Chargily) peuvent franchir le garde `exists()`
 * de CommissionService::recordCommissionForPayment et insérer deux commissions
 * pour le même paiement (double rémunération partenaire). L'index unique rend
 * la course atomique : la seconde insertion échoue en 23505, désormais
 * rattrapée par le service (idempotent, cf. CommissionService).
 *
 * Table publique (module growth) — migration volontairement tolérante pour
 * Render (idempotente, schéma conditionnel).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commissions') || ! Schema::hasColumn('commissions', 'payment_id')) {
            return;
        }

        // Dédoublonnage préalable : ne garder que la plus ancienne commission
        // par payment_id (si des doublons historiques existent déjà).
        DB::statement('
            DELETE FROM commissions a
            USING commissions b
            WHERE a.payment_id = b.payment_id
              AND a.id > b.id
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS commissions_payment_id_unique
            ON commissions (payment_id)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS commissions_payment_id_unique');
    }
};
