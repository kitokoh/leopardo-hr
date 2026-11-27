<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9975 — ADR-9879, lot 6 : paie, facturation, comptabilité, santé, identité.
 *
 * ## Périmètre de ce lot
 *
 * L'inventaire (`database/scripts/rls/inventory-company-id-tables.sh`) mesure
 * **523 tables** `shared_tenants` portant `company_id`. L'ADR-9879 en couvrait
 * 8, le lot #9924 en a ajouté 4 (RH nominatif). Ce lot couvre les **18 tables
 * les plus sensibles restantes**, choisies sur un critère unique : *une fuite
 * inter-tenant y est un incident RGPD ou financier, pas une gêne*.
 *
 * | Domaine | Tables | `company_id` |
 * |---|---|---|
 * | Paie | `employee_loans`, `payment_batches`, `payment_confirmations`, `pay_slip_lines` | 3 NOT NULL, 1 nullable |
 * | Facturation | `invoices`, `billing_collections` | NOT NULL |
 * | Comptabilité | `accounting_documents`, `accounting_document_lines`, `bank_statements`, `bank_statement_lines` | NOT NULL |
 * | Santé | `health_patients`, `health_consultations`, `health_prescriptions`, `health_prescription_items`, `health_invoices` | NOT NULL |
 * | Identité / RH | `employee_documents`, `employee_departures`, `contract_amendments`, `leave_accruals`, `leave_balance_logs` | 4 NOT NULL, 1 nullable |
 *
 * ## Pourquoi ces 18 et pas les 505 autres
 *
 * Ajouter 500 politiques d'un coup serait une faute : l'ADR exige un
 * inventaire des accès légitimes AVANT chaque politique, et une politique
 * inerte donne une **fausse impression de sécurité** — pire que l'absence.
 * Ces 18 tables ont été retenues parce que :
 *   - le risque de fuite est maximal (santé, paie, identité, argent) ;
 *   - l'inventaire est concluant : **aucun accès inter-tenant légitime**
 *     (vérifié : 0 appel `withoutGlobalScope(s)` sur ces modèles, sauf
 *     `BankStatementImportService` qui filtre explicitement par `company_id`) ;
 *   - `company_id` est renseigné partout, ou backfillable depuis la table
 *     parente (voir `BACKFILL` ci-dessous).
 *
 * ## Les deux colonnes nullables, et leur backfill
 *
 * `pay_slip_lines.company_id` et `leave_balance_logs.company_id` sont
 * nullables. Sous la politique, une ligne à `NULL` devient **invisible**
 * (`NULL = 'x'` vaut `NULL`, pas `TRUE`) : la migration backfill depuis la
 * table parente AVANT de poser la politique, puis **échoue bruyamment** s'il
 * reste un `NULL` — une panne au déploiement vaut mieux qu'une disparition
 * silencieuse en production (leçon #9897 sur `employees`).
 *
 * ## `BankStatementImportService` : le seul accès `withoutGlobalScopes()`
 *
 * Il appelle `withoutGlobalScopes()` sur `bank_statements` et
 * `bank_statement_lines`, mais **filtre explicitement par `company_id`** et
 * écrit `company_id` à l'insert. La politique est donc satisfaite — à une
 * condition : que le tenant soit publié dans la session. Le contrôleur passe
 * par `currentCompany()->id`, donc le tenant est publié par le middleware.
 * Aucun changement de code n'est requis ; c'est vérifié par le test.
 *
 * ## Prérequis bloquant, rappelé
 *
 * Tant que le rôle applicatif est SUPERUSER, PostgreSQL fait **toujours**
 * bypasser RLS, même avec `FORCE`. Ces politiques sont donc **inertes** en
 * production jusqu'à la bascule (#9974). Elles ne sont pas inutiles pour
 * autant : elles rendent la bascule possible sans nouvelle migration, et
 * `tenant:rls-status` les compte.
 */
return new class extends Migration
{
    /**
     * Tables du lot, avec la colonne de backfill éventuelle.
     *
     * `null` = `company_id` NOT NULL, rien à backfiller.
     * `[table_parente, colonne_fk]` = backfill depuis la table parente.
     *
     * @var array<string, array{0: string, 1: string}|null>
     */
    private const TABLES = [
        // Paie
        'employee_loans' => null,
        'payment_batches' => null,
        'payment_confirmations' => null,
        'pay_slip_lines' => ['pay_slips', 'pay_slip_id'],
        // Facturation
        'invoices' => null,
        'billing_collections' => null,
        // Comptabilité
        'accounting_documents' => null,
        'accounting_document_lines' => null,
        'bank_statements' => null,
        'bank_statement_lines' => null,
        // Santé
        'health_patients' => null,
        'health_consultations' => null,
        'health_prescriptions' => null,
        'health_prescription_items' => null,
        'health_invoices' => null,
        // Identité / RH
        'employee_documents' => null,
        'employee_departures' => null,
        'contract_amendments' => null,
        'leave_accruals' => null,
        'leave_balance_logs' => ['employees', 'employee_id'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $backfill) {
            if (! schemaTableExists($table)) {
                // Garde bruyant : une migration qui ne fait rien doit le dire.
                // Sans cela, `migrate:status` serait vert alors que la table
                // n'a aucune politique (le défaut #9896 exactement).
                logger()->warning('RLS #9975 : table absente, politique non posée', [
                    'table' => $table,
                ]);

                continue;
            }

            if ($backfill !== null) {
                $this->backfillCompanyId($table, $backfill[0], $backfill[1]);
            }

            $this->assertNoNullCompanyId($table);

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement(<<<SQL
                CREATE POLICY {$table}_tenant_isolation
                ON {$table}
                USING (company_id::text = current_setting('app.current_company_id', true))
                WITH CHECK (company_id::text = current_setting('app.current_company_id', true))
                SQL);
        }
    }

    /**
     * Renseigne `company_id` depuis la table parente, pour les lignes à NULL.
     *
     * Idempotent : ne touche que les lignes à NULL. Sur une base déjà
     * backfillée, c'est un `UPDATE … WHERE company_id IS NULL` qui ne modifie
     * rien.
     */
    private function backfillCompanyId(string $table, string $parent, string $foreignKey): void
    {
        if (! schemaTableExists($parent)) {
            return;
        }

        $updated = DB::update(<<<SQL
            UPDATE {$table} AS child
               SET company_id = parent.company_id
              FROM {$parent} AS parent
             WHERE child.{$foreignKey} = parent.id
               AND child.company_id IS NULL
               AND parent.company_id IS NOT NULL
            SQL);

        if ($updated > 0) {
            logger()->info('RLS #9975 : backfill company_id', [
                'table' => $table,
                'rows' => $updated,
            ]);
        }
    }

    /**
     * Une ligne à `company_id` NULL deviendrait INVISIBLE sous la politique.
     *
     * On échoue explicitement plutôt que de laisser la politique masquer des
     * données : une panne bruyante au déploiement vaut mieux qu'une absence
     * silencieuse en production.
     */
    private function assertNoNullCompanyId(string $table): void
    {
        $remaining = DB::table($table)->whereNull('company_id')->count();

        if ($remaining > 0) {
            throw new RuntimeException(
                "RLS #9975 : {$remaining} ligne(s) de `{$table}` sans company_id. "
                .'La politique les rendrait invisibles (NULL = \'x\' vaut NULL). '
                .'Backfiller company_id depuis la table parente puis relancer la migration.'
            );
        }
    }

    /**
     * Pas de `down()` destructif : retirer les politiques rendrait la base
     * MOINS protégée qu'avant. Un rollback légitime passe par une migration
     * dédiée, décidée explicitement.
     */
    public function down(): void
    {
        // Intentionnellement vide — voir le docblock.
    }
};
