<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9879 (R8) — filet de sécurité RLS sur `bulk_payment_claims`.
 *
 * Constat de la revue : `grep -rl "ENABLE ROW LEVEL SECURITY" database/migrations`
 * renvoyait 0. L'isolation multi-tenant reposait UNIQUEMENT sur deux
 * mécanismes applicatifs (search_path + scope Eloquent). Une requête qui
 * contourne Eloquent (`DB::table()`, SQL brut, `withoutGlobalScopes()` oublié)
 * lisait les données de tous les tenants sans qu'aucune barrière ne l'arrête.
 *
 * On commence par la table la plus récente et la moins couplée
 * (`bulk_payment_claims`, #9874) : aucune lecture inter-tenant légitime n'en
 * dépend, donc le risque de régression est nul. Les tables historiques
 * (`pay_slips`, `employees`) demandent un inventaire des accès légitimes
 * avant d'être couvertes — c'est le plan de l'issue #9879.
 *
 * ⚠️ LIMITE IMPORTANTE, mesurée dans ce dépôt : le rôle applicatif
 * (`leopardo`) est **SUPERUSER**. PostgreSQL fait TOUJOURS bypasser RLS aux
 * superusers, même avec `FORCE ROW LEVEL SECURITY`. Cette migration pose donc
 * la politique correctement, mais elle ne protège réellement qu'une fois
 * l'application connectée avec un rôle NON-superuser. C'est documenté dans
 * l'ADR et vérifié par le test `RlsTenantIsolationTest`, qui se connecte
 * explicitement avec un rôle limité.
 *
 * Migration réentrante et `down()` complet.
 */
return new class extends Migration
{
    private const TABLE = 'bulk_payment_claims';

    public function up(): void
    {
        if (! schemaTableExists(self::TABLE)) {
            return;
        }

        // 1) Activer RLS et forcer son application au propriétaire de la table
        //    (sans FORCE, le propriétaire — souvent le rôle applicatif —
        //    contournerait silencieusement la politique).
        DB::statement('ALTER TABLE '.self::TABLE.' ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE '.self::TABLE.' FORCE ROW LEVEL SECURITY');

        // 2) Politique : une ligne n'est visible/modifiable que si son
        //    company_id correspond au tenant courant, positionné par la
        //    session (`app.current_company_id`).
        //
        //    `current_setting(..., true)` = missing_ok : si la variable n'est
        //    PAS positionnée, la comparaison vaut NULL → AUCUNE ligne n'est
        //    visible. C'est le comportement fail-closed voulu : oublier de
        //    positionner le tenant ne fuit rien.
        DB::statement('DROP POLICY IF EXISTS bulk_payment_claims_tenant_isolation ON '.self::TABLE);
        DB::statement(<<<'SQL'
            CREATE POLICY bulk_payment_claims_tenant_isolation
            ON bulk_payment_claims
            USING (company_id::text = current_setting('app.current_company_id', true))
            WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
            SQL);
    }

    public function down(): void
    {
        if (! schemaTableExists(self::TABLE)) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS bulk_payment_claims_tenant_isolation ON '.self::TABLE);
        DB::statement('ALTER TABLE '.self::TABLE.' NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE '.self::TABLE.' DISABLE ROW LEVEL SECURITY');
    }
};
