<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9924 — ADR-9879, extension de la couverture RLS aux tables RH nominatives.
 *
 * ## Perimetre de ce lot
 *
 * L'inventaire (`database/scripts/rls/inventory-company-id-tables.sh`) mesure
 * **523 tables** `shared_tenants` portant `company_id`, dont 4 couvertes par
 * l'ADR-9879. Ce lot couvre les 4 tables RH les plus sensibles — celles qui
 * portent des donnees nominatives sur la vie des salaries :
 *
 * | Table | `company_id` | Lignes NULL | Pourquoi ce lot |
 * |---|---|---|---|
 * | `absences` | nullable | 0 | arret maladie, conge, motif familial |
 * | `contracts` | NOT NULL | 0 | salaire, type de contrat, dates |
 * | `leave_balances` | NOT NULL | 0 | solde de conges |
 * | `attendance_logs` | nullable | 0 | pointages, geolocalisation |
 *
 * ## Pourquoi seulement 4, et pas 523
 *
 * Ajouter 500 politiques d'un coup serait une faute. L'ADR exige un
 * inventaire des acces legitimes AVANT chaque politique, et une politique
 * inerte donne une **fausse impression de securite** — pire que l'absence.
 * Les 4 tables retenues sont celles ou :
 *   - le risque de fuite est le plus eleve (donnees nominatives) ;
 *   - l'inventaire est concluant (aucun acces inter-tenant legitime) ;
 *   - `company_id` est deja renseigne partout (0 ligne NULL mesuree).
 *
 * ## Les acces `withoutGlobalScopes()` de ce lot
 *
 * Deux sites appellent `withoutGlobalScopes()` sur ces tables. Les deux
 * filtrent **explicitement** par `company_id`, donc la politique est
 * satisfaite — mais ils doivent publier le tenant, sinon
 * `current_setting('app.current_company_id', true)` vaut NULL et **aucune
 * ligne n'est visible** :
 *
 * | Site | Etat |
 * |---|---|
 * | `PlatformCompanyHealthService::attendance()` | OK — `where('company_id', $company->id)` explicite, et le service tourne sous le tenant de la company consultee. |
 * | `LeaveCarryForward` | **Corrige dans ce lot** — la commande iterait les politiques de TOUS les tenants sans publier le tenant courant. Chaque boucle passe desormais par `TenantManager::withinTenant()`. |
 *
 * ## `tax_slabs` : exclue explicitement, et pourquoi
 *
 * L'inventaire la classe « bloque (backfill requis) » : 6 lignes a
 * `company_id` NULL. Ce ne sont **pas** des donnees orphelines — ce sont les
 * **baremes legaux partages** (repli `whereNull('company_id')` dans
 * `AbstractCountryRules::resolveTaxSlabsFromDatabase()`). Les filtrer par
 * tenant casserait le calcul de paie de toute company sans bareme propre.
 *
 * Elle est donc **exclue** de ce lot, avec sa raison, et non « oubliee ».
 * Une eventuelle politique devra etre ecrite `USING (company_id IS NULL OR
 * company_id::text = current_setting(...))` — decision separee, car elle
 * change la semantique de lecture des baremes.
 *
 * ## Prerequis bloquant, rappele
 *
 * Tant que le role applicatif est SUPERUSER, PostgreSQL fait **toujours**
 * bypasser RLS, meme avec `FORCE`. Ces politiques sont donc **inertes** en
 * production jusqu'a l'etape 2 de l'ADR. Elles ne sont pas inutiles pour
 * autant : elles rendent la bascule possible sans nouvelle migration, et
 * `tenant:rls-status` les compte.
 */
return new class extends Migration
{
    /**
     * Tables couvertes par ce lot, avec leur `company_id` nullable ou non.
     *
     * @var array<string, bool>
     */
    private const TABLES = [
        'absences' => true,
        'contracts' => false,
        'leave_balances' => false,
        'attendance_logs' => true,
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $nullable) {
            if (! schemaTableExists($table)) {
                // #9923 — garde bruyant : une migration qui ne fait rien doit
                // le dire. Sans cela, `migrate:status` serait vert alors que
                // la table n'a aucune politique (le defaut #9896 exactement).
                logger()->warning('RLS #9924 : table absente, politique non posee', [
                    'table' => $table,
                ]);

                continue;
            }

            if ($nullable) {
                $this->assertNoNullCompanyId($table);
            }

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
     * Une ligne a `company_id` NULL deviendrait INVISIBLE sous la politique
     * (`NULL = 'x'` vaut NULL, pas TRUE).
     *
     * On echoue explicitement plutot que de laisser la politique masquer des
     * donnees : une panne bruyante au deploiement vaut mieux qu'une absence
     * silencieuse en production. C'est la lecon de #9897 sur `employees`.
     */
    private function assertNoNullCompanyId(string $table): void
    {
        $remaining = DB::table($table)->whereNull('company_id')->count();

        if ($remaining > 0) {
            throw new RuntimeException(
                "RLS #9924 : {$remaining} ligne(s) de `{$table}` sans company_id. "
                .'La politique les rendrait invisibles (NULL = \'x\' vaut NULL). '
                .'Backfiller company_id depuis la table parente (employee_id -> employees.company_id) '
                .'puis relancer la migration.'
            );
        }
    }

    /**
     * Pas de `down()` destructif : retirer les politiques rendrait la base
     * MOINS protegee qu'avant. Un rollback legitime passe par une migration
     * dediee, decidee explicitement.
     */
    public function down(): void
    {
        // Intentionnellement vide — voir le docblock.
    }
};
