<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7717 (BC-25 RESTAURANT) — l'unique (company_id, branch_id, status) posé par
 * 2026_08_30_001527_6173 sur `restaurant_pos_sessions` interdit deux sessions
 * FERMÉES sur la même branche : `ClosePosSessionAction` écrit `status='closed'`
 * sans suffixe, la 2e clôture viole la contrainte (23505) — le POS restaurant
 * casse au 2e jour d'exploitation d'une branche.
 *
 * Correctif **forward-only** (pattern éprouvé côté Retail, migration
 * 2026_09_19_000003_7674 sur `retail_pos_sessions`) :
 *
 *  1. drop de la contrainte unique `restaurant_pos_sessions_company_branch_status_unique`
 *     (DROP CONSTRAINT + DROP INDEX par ceinture — Blueprint::unique crée une
 *     contrainte sous Postgres) ;
 *  2. INDEX UNIQUE PARTIEL Postgres `(company_id, branch_id) WHERE status = 'open'` :
 *     l'invariant métier « une seule session OUVERTE par branche » est conservé,
 *     les sessions `closed`/`cancelled` coexistent librement.
 *
 * Aucun nettoyage de données requis : sous l'ancienne contrainte, au plus une
 * ligne par (company, branche, statut) existe — l'index partiel se crée sans
 * conflit. Idempotente (IF EXISTS / IF NOT EXISTS), rejouable sur base migrée
 * comme sur base fraîche (la table peut être absente si 6173 n'a pas tourné).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! schemaTableExists('restaurant_pos_sessions')) {
            return;
        }

        DB::statement('ALTER TABLE restaurant_pos_sessions DROP CONSTRAINT IF EXISTS restaurant_pos_sessions_company_branch_status_unique');
        DB::statement('DROP INDEX IF EXISTS restaurant_pos_sessions_company_branch_status_unique');

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS restaurant_pos_sessions_company_branch_open_unique ON restaurant_pos_sessions (company_id, branch_id) WHERE status = 'open'");

        DB::statement("COMMENT ON TABLE restaurant_pos_sessions IS 'Sessions de caisse POS - une seule session open par (tenant, branche) via index unique partiel (BC-25/#7717) ; les sessions fermees coexistent.';");
    }

    public function down(): void
    {
        // Forward-only : on retire uniquement ce que up() a ajouté, SANS
        // recréer la contrainte défectueuse (company, branch, status) — elle
        // recasserait le POS au premier historique de clôtures existant.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS restaurant_pos_sessions_company_branch_open_unique');
    }
};
