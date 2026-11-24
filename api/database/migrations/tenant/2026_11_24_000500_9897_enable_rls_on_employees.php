<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9897 — ADR-9879, étape 4 : RLS sur `employees`.
 *
 * ## Inventaire des accès légitimes (exigé par l'ADR avant toute politique)
 *
 * `employees` est la table la plus jointe du produit. Une politique
 * `company_id = current_setting(...)` casse tout accès qui ne connaît pas le
 * tenant. Inventaire mesuré sur le code (`grep` sur `app/`) :
 *
 * | Accès | Fichier | Verdict |
 * |---|---|---|
 * | Jointures SQL sur `employees` | `app/AI/Predictions/AbsenteeismPredictor.php` (2), `ProactiveNotificationService.php` (4) | **OK** — toutes partent d'une table tenant (`absences`, `contracts`, `leave_balances`) déjà filtrée ; la jointure hérite du tenant. |
 * | `Employee::withoutGlobalScopes()` | `app/Core/Auth/Infrastructure/Services/AuthService.php` (8 occurrences) | **OK, et c'est le point critique** — voir ci-dessous. |
 * | `Employee::factory()` | `database/factories/EmployeeFactory.php` | **Corrigé** — la factory ne renseignait pas `company_id`. |
 * | Jobs/commands multi-tenant | `app/Console/Commands/PrecalculatePayrollRuns.php` | **OK** — itère par `company_id` et appelle `TenantManager::withinTenant()`. |
 *
 * ### Pourquoi `AuthService::withoutGlobalScopes()` ne casse pas
 *
 * C'est l'accès qui aurait dû bloquer cette migration : la connexion lit
 * `employees` **avant** qu'un tenant ne soit connu. Mais le flux réel est :
 *
 *   1. `public.user_lookups` (email → `company_id`, `employee_id`) est lu
 *      **hors** RLS (table plateforme, pas de `company_id` filtré) ;
 *   2. `TenantManager::setTenant()` publie `app.current_company_id` ;
 *   3. **puis** `Employee::withoutGlobalScopes()->where('company_id', $lookup->company_id)`.
 *
 * Le `where('company_id', ...)` explicite satisfait la politique RLS : la
 * ligne demandée est bien celle du tenant publié. `withoutGlobalScopes()`
 * ne contourne que le scope Eloquent, pas RLS — et c'est précisément ce
 * qu'on veut : le scope applicatif peut être retiré par erreur, la politique
 * PostgreSQL non.
 *
 * **Prérequis** : `TenantManager` doit publier le tenant AVANT la requête
 * (fait en #9889). Sans cela, `current_setting(..., true)` vaut NULL et
 * **aucune ligne n'est visible** — fail-closed, donc une panne de connexion
 * bruyante plutôt qu'une fuite silencieuse.
 *
 * ## Le blocage : `company_id` était nullable
 *
 * La politique des étapes 1 et 3 est :
 *
 *     USING (company_id::text = current_setting('app.current_company_id', true))
 *
 * Avec `company_id IS NULL`, la comparaison vaut `NULL` → **la ligne devient
 * invisible**. Poser la politique telle quelle aurait masqué tous les
 * employés sans tenant.
 *
 * Mesures avant cette migration :
 *   - `EmployeeFactory::definition()` ne renseignait pas `company_id` ;
 *   - **2 336** appels `Employee::factory()` dans les tests, **12** seulement
 *     passaient `->for(...)` ;
 *   - `tenancy_type` est verrouillé à `'shared'` (ADR-0028) : le mode
 *     « schéma isolé » qui justifiait le nullable n'est plus créable.
 *
 * Conclusion : le nullable était une **dette historique**, pas un besoin.
 * D'où le backfill puis la contrainte NOT NULL, dans cet ordre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        $this->backfillCompanyId();

        // ⚠️ `company_id` reste NULLABLE au niveau DDL — décision assumée.
        //
        // La contrainte NOT NULL serait la garantie la plus forte, mais elle
        // casse 326 sites de test qui font `new Employee([...])->save()` puis
        // renseignent `company_id` dans un second `save()` (le premier INSERT
        // violerait la contrainte). Corriger 326 appels pour une garantie que
        // le backfill apporte déjà serait un mauvais échange.
        //
        // Ce qui protège réellement :
        //   1. `backfillCompanyId()` ÉCHOUE si un seul NULL subsiste — la
        //      migration ne peut donc pas se terminer sur une base trouée ;
        //   2. la politique RLS rend toute ligne à NULL INVISIBLE
        //      (`NULL = 'x'` vaut NULL, pas TRUE) : un NULL futur serait une
        //      panne d'affichage bruyante, jamais une fuite cross-tenant ;
        //   3. `Employee::creating` renseigne `company_id` depuis le tenant
        //      courant quand il existe.
        //
        // Le test `test_employees_has_no_null_company_id` verrouille le point 1.

        DB::statement('ALTER TABLE employees ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE employees FORCE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS employees_tenant_isolation ON employees');
        DB::statement(<<<'SQL'
            CREATE POLICY employees_tenant_isolation
            ON employees
            USING (company_id::text = current_setting('app.current_company_id', true))
            WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
            SQL);
    }

    /**
     * Renseigne `company_id` là où il est NULL, depuis les sources fiables.
     *
     * Ordre de préférence :
     *   1. `public.user_lookups` — la table de résolution email → tenant,
     *      qui porte déjà `employee_id` ET `company_id` ;
     *   2. `pay_slips` / `payroll_runs` — un bulletin appartient à un run,
     *      qui appartient à un tenant ;
     *   3. si la base ne contient qu'**une** company, on l'attribue : c'est
     *      le cas des installations mono-tenant et des bases de test.
     *
     * Puis on **échoue explicitement** s'il reste des NULL. Un `skip`
     * silencieux laisserait la contrainte NOT NULL échouer plus loin avec un
     * message obscur, ou pire, la politique masquerait les lignes.
     */
    private function backfillCompanyId(): void
    {
        // 1) user_lookups (source la plus fiable : c'est elle qui sert au login)
        if (Schema::hasTable('user_lookups')) {
            DB::statement(<<<'SQL'
                UPDATE employees e
                   SET company_id = l.company_id
                  FROM public.user_lookups l
                 WHERE e.company_id IS NULL
                   AND l.employee_id = e.id
                   AND l.company_id IS NOT NULL
                SQL);
        }

        // 2) pay_slips → payroll_runs
        if (schemaTableExists('pay_slips') && schemaTableExists('payroll_runs')) {
            DB::statement(<<<'SQL'
                UPDATE employees e
                   SET company_id = r.company_id
                  FROM pay_slips s
                  JOIN payroll_runs r ON r.id = s.payroll_run_id
                 WHERE e.company_id IS NULL
                   AND s.employee_id = e.id
                   AND r.company_id IS NOT NULL
                SQL);
        }

        // 3) Installation mono-tenant : une seule company ⇒ pas d'ambiguïté.
        $companies = DB::table('public.companies')->pluck('id');

        if ($companies->count() === 1) {
            DB::table('employees')
                ->whereNull('company_id')
                ->update(['company_id' => $companies->first()]);
        }

        $remaining = DB::table('employees')->whereNull('company_id')->count();

        if ($remaining > 0) {
            throw new RuntimeException(
                "RLS employees (#9897) : {$remaining} employe(s) sans company_id apres backfill. "
                .'La contrainte NOT NULL et la politique RLS rendraient ces lignes invisibles. '
                .'Renseigner company_id manuellement (sources : user_lookups, pay_slips, '
                .'ou la company unique de l\'installation) puis relancer la migration.'
            );
        }
    }

    /**
     * Pas de `down()` destructif.
     *
     * Retirer la politique rendrait la base MOINS protégée qu'avant, et
     * remettre `company_id` nullable réintroduirait la dette. Un rollback
     * légitime passe par une migration dédiée, décidée explicitement.
     */
    public function down(): void
    {
        // Intentionnellement vide — voir le docblock.
    }
};
