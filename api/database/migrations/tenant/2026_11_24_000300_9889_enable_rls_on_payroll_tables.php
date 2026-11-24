<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9889 (R8, etape 3 de l'ADR-9879) — RLS sur `pay_slips` et `payroll_runs`.
 *
 * ## Pourquoi ces deux tables apres `bulk_payment_claims`
 *
 * Ce sont les tables les plus sensibles du produit : donnees de paie
 * nominatives. L'etape 1 (#9879) avait couvert une table neuve et non
 * couplee ; ces deux-ci sont historiques, donc elles demandaient d'abord
 * l'inventaire des acces legitimes exige par l'ADR.
 *
 * ## Inventaire des acces legitimes (fait avant d'ecrire la policy)
 *
 * Recherche exhaustive des acces qui franchissent la frontiere tenant :
 *
 *  - `DB::table('pay_slips')` / `DB::table('payroll_runs')` :
 *      * `SocialDeclarationService::quarterPayrollData()` et
 *        `monthPayrollData()` — joignent `payroll_runs` et filtrent
 *        `payroll_runs.company_id = $companyId` EXPLICITEMENT. Scopes.
 *      * `PilotReportCommand::companyMetrics()` — execute dans
 *        `TenantManager::withinTenant($company, ...)`. Scopes.
 *  - `withoutGlobalScopes()` sur ces tables : AUCUN.
 *  - jobs multi-tenant : `ProcessBulkPaymentJob` traite UN run, donc UN
 *    tenant (le run porte `company_id`).
 *  - vues materialisees / tables d'agregat : aucune.
 *
 * Conclusion : **aucun acces inter-tenant legitime**. Aucune exemption n'est
 * necessaire, et aucune n'est posee — une exemption implicite serait
 * exactement le trou que cette migration ferme.
 *
 * ## ⚠️ Prerequis bloquant : le role applicatif doit etre NOSUPERUSER
 *
 * Mesure dans ce depot : le role applicatif est SUPERUSER. PostgreSQL fait
 * TOUJOURS bypasser RLS aux superusers, MEME avec `FORCE ROW LEVEL SECURITY`.
 * Cette migration pose donc la politique correctement, mais elle ne protege
 * reellement qu'apres la bascule decrite dans l'ADR (etape 4). Le test
 * `RlsTenantIsolationTest` se connecte avec un role limite dedie pour que la
 * preuve ne soit pas un faux positif.
 *
 * ## Le tenant est publie par `TenantManager`, pas par cette migration
 *
 * `TenantManager::setTenant()` / `restoreCompanyContext()` / `clearTenant()`
 * positionnent `app.current_company_id` (#9889). Sans ce cablage, la policy
 * serait inerte : `current_setting(..., true)` renvoie NULL et la comparaison
 * vaut NULL, donc aucune ligne n'est visible.
 *
 * Migration reentrante et `down()` complet.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['pay_slips', 'payroll_runs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }

            // 1) Activer RLS et forcer son application au proprietaire de la
            //    table (sans FORCE, le proprietaire — souvent le role
            //    applicatif — contournerait silencieusement la politique).
            DB::statement('ALTER TABLE '.$table.' ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE '.$table.' FORCE ROW LEVEL SECURITY');

            // 2) Politique : une ligne n'est visible/modifiable que si son
            //    company_id correspond au tenant courant, positionne par la
            //    session (`app.current_company_id`).
            //
            //    `current_setting(..., true)` = missing_ok : si la variable
            //    n'est PAS positionnee, la comparaison vaut NULL → AUCUNE
            //    ligne n'est visible. Fail-closed : oublier de positionner le
            //    tenant ne fuit rien.
            DB::statement('DROP POLICY IF EXISTS '.$table.'_tenant_isolation ON '.$table);
            DB::statement(<<<SQL
                CREATE POLICY {$table}_tenant_isolation
                ON {$table}
                USING (company_id::text = current_setting('app.current_company_id', true))
                WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }

            DB::statement('DROP POLICY IF EXISTS '.$table.'_tenant_isolation ON '.$table);
            DB::statement('ALTER TABLE '.$table.' NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE '.$table.' DISABLE ROW LEVEL SECURITY');
        }
    }
};
