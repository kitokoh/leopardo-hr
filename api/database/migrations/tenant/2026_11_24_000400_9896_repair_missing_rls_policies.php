<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9896 — réparation : réapplique les politiques RLS manquantes.
 *
 * ## Le défaut que cette migration corrige
 *
 * Constaté avec `php artisan tenant:rls-status` sur une base réelle :
 *
 *     | bulk_payment_claims | etape 1 (#9879) | oui | oui | oui | non | non couverte |
 *
 * La table existait, `ENABLE`/`FORCE` étaient posés, mais **aucune politique**
 * n'était présente — alors que la migration #9879 était enregistrée comme
 * passée.
 *
 * Cause mesurée dans `shared_tenants.migrations` :
 *
 *     batch 2 : 2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims
 *     batch 3 : 2026_11_24_000100_9874_create_bulk_payment_claims_table
 *
 * La migration RLS a tourné **avant** la création de la table. Son garde
 * `if (! schemaTableExists(...)) { return; }` l'a donc silencieusement
 * ignorée — et Laravel l'a marquée « Ran » définitivement. Aucune
 * réexécution n'était possible : `migrate` ne rejoue pas une migration
 * enregistrée.
 *
 * ## Pourquoi c'est un vrai problème, pas un accident de sandbox
 *
 * Le garde « table absente → return » est correct en soi (il rend la
 * migration tolérante à un ordre de déploiement), mais il est **silencieux** :
 * l'état final est « migration passée, protection absente ». Rien ne le
 * signale. C'est exactement le mode de défaillance que l'ADR-9879 cherche à
 * éliminer — une politique qu'on croit posée.
 *
 * Le même trou se produirait sur toute base où l'ordre des lots diffère
 * (restauration partielle, `migrate --path` sélectif, branche mergée dans un
 * ordre différent).
 *
 * ## Ce que fait cette migration
 *
 * Elle **réconcilie l'état voulu avec l'état réel** : pour chaque table que
 * l'ADR engage à couvrir et qui existe, elle repose `ENABLE` + `FORCE` + la
 * politique. Idempotente (`DROP POLICY IF EXISTS` puis `CREATE`), donc sans
 * effet sur une base déjà correcte.
 *
 * Elle ne remplace pas les migrations d'origine : celles-ci restent la
 * source de vérité de la définition. Celle-ci est un filet de réparation,
 * et `tenant:rls-status` reste la sonde qui prouve le résultat.
 */
return new class extends Migration
{
    /**
     * Tables couvertes par l'ADR-9879, avec leur étape.
     *
     * `employees` est volontairement ABSENTE : sa couverture (étape 4, #9897)
     * exige d'abord le backfill de `company_id`, qui est nullable. Poser la
     * politique maintenant rendrait invisibles toutes les lignes à
     * `company_id` NULL — voir l'issue #9897.
     *
     * @var list<string>
     */
    private const TABLES = ['bulk_payment_claims', 'pay_slips', 'payroll_runs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }

            DB::statement('ALTER TABLE '.$table.' ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE '.$table.' FORCE ROW LEVEL SECURITY');

            DB::statement('DROP POLICY IF EXISTS '.$table.'_tenant_isolation ON '.$table);
            DB::statement(<<<SQL
                CREATE POLICY {$table}_tenant_isolation
                ON {$table}
                USING (company_id::text = current_setting('app.current_company_id', true))
                WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
                SQL);
        }
    }

    /**
     * Pas de `down()` destructif.
     *
     * Cette migration ne crée aucun objet propre : elle réaffirme l'état
     * défini par #9879 et #9889. Un `down()` qui désactiverait RLS rendrait
     * la base MOINS protégée qu'avant son exécution — c'est précisément le
     * défaut corrigé dans `RlsTenantIsolationTest` (#9896). Un rollback
     * légitime passe par les migrations d'origine.
     */
    public function down(): void
    {
        // Intentionnellement vide — voir le docblock.
    }
};
