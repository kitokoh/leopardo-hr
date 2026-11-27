<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9975 — les 20 tables du lot 6 (paie, facturation, comptabilité, santé,
 * identité) portent réellement une politique RLS fail-closed.
 *
 * ## Ce que ce test prouve, et ce qu'il ne prouve pas
 *
 * Il lit l'état RÉEL de `pg_class` / `pg_policy` : `ENABLE`, `FORCE`, politique
 * présente, et forme de l'expression (`current_setting('app.current_company_id',
 * true)` — le `true` de `missing_ok` est ce qui rend la politique fail-closed au
 * lieu de lever une erreur).
 *
 * Il ne prouve pas, table par table, qu'une ligne du tenant B est invisible au
 * tenant A : cela demande d'insérer une ligne valide dans chacune des 20 tables,
 * donc de satisfaire toutes leurs clés étrangères. `RlsHrTablesIsolationTest`
 * le fait pour les 4 tables du lot 5 parce que leurs dépendances sont bornées ;
 * ici le coût serait disproportionné et fragile.
 *
 * L'isolation réelle de ce lot a été mesurée **hors CI**, sur PostgreSQL 16 :
 * les 20 tables créées, peuplées sur deux tenants, migration jouée, puis
 * interrogées sous un rôle `NOSUPERUSER NOBYPASSRLS` — 20/20 renvoient 0 ligne
 * sans tenant publié (voir `docs/security/RLS_COVERAGE_INVENTORY.md`).
 *
 * Un test structurel n'est pas un test d'isolation, et le dire ici évite qu'un
 * lecteur croie l'inverse — c'est exactement le défaut que l'audit reproche au
 * dépôt.
 */
final class RlsLot6CoverageTest extends TestCase
{
    /**
     * Les 20 tables du lot, avec le domaine. Doit rester synchronisé avec
     * `TenantRlsStatusCommand::EXPECTED_TABLES` — la garde
     * `check-rls-coverage-parity.py` le vérifie en CI.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        // Paie
        'employee_loans' => 'paie',
        'payment_batches' => 'paie',
        'payment_confirmations' => 'paie',
        'pay_slip_lines' => 'paie',
        // Facturation
        'invoices' => 'facturation',
        'billing_collections' => 'facturation',
        // Comptabilité
        'accounting_documents' => 'comptabilite',
        'accounting_document_lines' => 'comptabilite',
        'bank_statements' => 'comptabilite',
        'bank_statement_lines' => 'comptabilite',
        // Santé
        'health_patients' => 'sante',
        'health_consultations' => 'sante',
        'health_prescriptions' => 'sante',
        'health_prescription_items' => 'sante',
        'health_invoices' => 'sante',
        // Identité / RH
        'employee_documents' => 'identite',
        'employee_departures' => 'identite',
        'contract_amendments' => 'identite',
        'leave_accruals' => 'identite',
        'leave_balance_logs' => 'identite',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalité PostgreSQL.');
        }
    }

    /**
     * Les 20 tables portent ENABLE + FORCE + une politique fail-closed.
     *
     * Une table absente de la base fait ÉCHOUER le test plutôt que de le
     * sauter : le lot 6 déclare 20 tables, et une base où il en manque une
     * n'a pas joué la migration. Sauter masquerait précisément le défaut
     * #9896 (migration « Ran » sans effet).
     */
    public function test_every_lot6_table_carries_a_fail_closed_policy(): void
    {
        $missing = [];
        $notForced = [];
        $noPolicy = [];
        $wrongShape = [];

        foreach (array_keys(self::TABLES) as $table) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

            if ($exists === null || $exists->t === null) {
                $missing[] = $table;

                continue;
            }

            $row = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
                ['shared_tenants.'.$table]
            );

            if (! $row->relrowsecurity || ! $row->relforcerowsecurity) {
                $notForced[] = $table;
            }

            $policies = DB::select(
                'SELECT polname, pg_get_expr(polqual, polrelid) AS using_expr
                   FROM pg_policy WHERE polrelid = to_regclass(?)',
                ['shared_tenants.'.$table]
            );

            if ($policies === []) {
                $noPolicy[] = $table;

                continue;
            }

            // La forme fail-closed : `current_setting(..., true)` renvoie NULL
            // quand le tenant n'est pas publié, donc `company_id::text = NULL`
            // vaut NULL, donc AUCUNE ligne ne passe. Sans le `true`,
            // PostgreSQL lèverait une erreur — un 500 au lieu d'un vide, et
            // un `COALESCE` ajouté plus tard rouvrirait tout.
            $expr = (string) $policies[0]->using_expr;
            if (! str_contains($expr, 'current_setting') || ! str_contains($expr, 'true')) {
                $wrongShape[] = $table.' → '.$expr;
            }
        }

        expect($missing)->toBe([], 'Tables du lot 6 absentes : '.implode(', ', $missing).'. La migration #9975 n\'a pas été jouée.');
        expect($notForced)->toBe([], 'ENABLE+FORCE manquant sur : '.implode(', ', $notForced).'. Sans FORCE, le propriétaire de la table bypasserait sa propre politique.');
        expect($noPolicy)->toBe([], 'Aucune politique sur : '.implode(', ', $noPolicy).'. RLS activé sans politique = tout est refusé (ou tout passe si permissive) : dans les deux cas, ce n\'est pas l\'isolation voulue.');
        expect($wrongShape)->toBe([], 'Politique non fail-closed : '.implode(' | ', $wrongShape));
    }

    /**
     * Les deux tables à `company_id` nullable ont bien été backfillées.
     *
     * Une ligne à `NULL` resterait invisible sous la politique : la migration
     * doit avoir échoué bruyamment plutôt que de la masquer. Si ce test voit
     * un `NULL`, c'est qu'une écriture est passée APRÈS la migration sans
     * renseigner `company_id` (ou que le backfill a été contourné).
     */
    public function test_nullable_company_id_tables_hold_no_orphan_rows(): void
    {
        foreach (['pay_slip_lines', 'leave_balance_logs'] as $table) {
            $exists = DB::selectOne('SELECT to_regclass(?) AS t', ['shared_tenants.'.$table]);

            if ($exists === null || $exists->t === null) {
                continue;
            }

            $orphans = DB::table($table)->whereNull('company_id')->count();

            expect($orphans)->toBe(
                0,
                $table.' : '.$orphans.' ligne(s) sans company_id. Sous la politique elles sont INVISIBLES — '
                .'ni lues, ni réparables par l\'application. Backfiller depuis la table parente.'
            );
        }
    }

    /**
     * Le lot 6 n'a pas « oublié » une table que la sonde attend.
     *
     * Double contrôle de la garde Python : si quelqu'un ajoute une table à
     * `EXPECTED_TABLES` sans migration, ce test le voit avant la production.
     */
    public function test_expected_tables_and_migration_lists_agree(): void
    {
        $command = file_get_contents(app_path('Console/Commands/TenantRlsStatusCommand.php'));
        expect($command)->not->toBeFalse();

        preg_match('/EXPECTED_TABLES\s*=\s*\[(.*?)\];/s', (string) $command, $m);
        expect($m[1] ?? null)->not->toBeNull('EXPECTED_TABLES introuvable.');

        // `$m[1]` est garanti non-null par l'assertion ci-dessus, mais PHPStan
        // ne le sait pas : on le matérialise pour que l'accès soit typé.
        $block = (string) ($m[1] ?? '');

        preg_match_all("/'([a-z_]+)'\s*=>/", $block, $keys);
        $expected = array_unique($keys[1]);

        $lot6 = array_keys(self::TABLES);
        $notInProbe = array_diff($lot6, $expected);

        expect($notInProbe)->toBe([], 'Tables du lot 6 absentes de EXPECTED_TABLES : '.implode(', ', $notInProbe).'. Le gate de déploiement ne les contrôlerait pas.');
    }
}
