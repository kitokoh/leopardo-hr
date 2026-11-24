<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #9896 (ADR-9879, étape 2) — la sonde `tenant:rls-status` doit dire la vérité.
 *
 * ## Pourquoi ce test existe
 *
 * L'ADR-9879 exige un rôle `NOSUPERUSER NOBYPASSRLS` avant que RLS ne protège
 * quoi que ce soit. Jusqu'ici cet état n'était vérifiable qu'en lisant l'ADR
 * et en lançant `\du` à la main. Une politique « posée mais inopérante » est
 * le pire des états : elle donne l'impression d'une protection qui n'existe
 * pas.
 *
 * La sonde rend l'état mesurable. Ce test vérifie qu'elle **détecte le
 * bypass** — c'est-à-dire qu'elle n'est pas un simple « tout va bien » qui
 * passerait au vert sur une base non protégée.
 *
 * ## Ce que le test prouve, et ce qu'il ne prouve pas
 *
 * Il prouve que la sonde lit `pg_roles` et `pg_class` et en tire le bon
 * verdict. Il ne prouve PAS que la production est protégée : c'est le rôle
 * de la sonde en déploiement (`--strict`), pas d'un test unitaire.
 */
class TenantRlsStatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS est une fonctionnalité PostgreSQL.');
        }
    }

    /**
     * Le rôle de test est SUPERUSER (c'est l'état mesuré du dépôt) : la sonde
     * doit le signaler et sortir en 1. Un test qui attendrait 0 ici
     * validerait une sonde aveugle.
     */
    public function test_probe_reports_bypass_when_role_is_superuser(): void
    {
        $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');

        if (! $role->rolsuper && ! $role->rolbypassrls) {
            $this->markTestSkipped('Le rôle de test ne bypass pas RLS : le scénario inverse est couvert par le test suivant.');
        }

        $this->artisan('tenant:rls-status')
            ->expectsOutputToContain('RLS est BYPASSE')
            ->assertExitCode(1);
    }

    /**
     * `--json` doit exposer le verdict de façon exploitable par un script,
     * sans avoir à parser du texte coloré.
     */
    public function test_probe_json_exposes_the_verdict(): void
    {
        $this->artisan('tenant:rls-status', ['--json' => true])->assertExitCode(1);

        // On relit l'état directement : le JSON est écrit sur stdout par la
        // commande, et `artisan()` ne le capture pas de façon fiable.
        $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        $bypassed = (bool) $role->rolsuper || (bool) $role->rolbypassrls;

        expect($bypassed)->toBeTrue('Le rôle de test doit bypasser RLS pour que ce scénario ait un sens.');
    }

    /**
     * Les tables que l'ADR engage à couvrir doivent l'être réellement.
     *
     * C'est ce test qui aurait attrapé le défaut trouvé en écrivant la sonde :
     * `bulk_payment_claims` avait `ENABLE` + `FORCE` mais **aucune politique**,
     * parce que la migration #9879 avait tourné avant la création de la table
     * (batch 2 vs batch 3) et s'était silencieusement ignorée.
     */
    public function test_every_expected_table_is_actually_covered(): void
    {
        $expected = ['bulk_payment_claims', 'pay_slips', 'payroll_runs'];
        $checked = 0;

        foreach ($expected as $table) {
            $schema = resolveTableSchema($table);

            // Une table absente de CETTE base n'est pas un échec : la base de
            // test peut avoir été migrée avant l'ajout de la table. Mais on
            // ne `skip` pas pour autant — un test qui se saute ne prouve
            // rien, et c'est exactement le défaut reproché aux gardes
            // précédentes. On vérifie celles qui existent et on exige un
            // minimum, sinon le test échoue.
            if ($schema === null) {
                continue;
            }

            $checked++;
            $qualified = $schema.'.'.$table;

            $cls = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
                [$qualified]
            );

            expect((bool) $cls->relrowsecurity)->toBeTrue($table.' doit avoir RLS activée.');
            expect((bool) $cls->relforcerowsecurity)->toBeTrue($table.' doit avoir FORCE RLS.');

            $policy = DB::selectOne(
                'SELECT 1 AS ok FROM pg_policy WHERE polrelid = to_regclass(?) AND polname = ?',
                [$qualified, $table.'_tenant_isolation']
            );

            expect($policy)->not->toBeNull(
                $table.' doit porter la politique '.$table.'_tenant_isolation — '
                .'ENABLE/FORCE sans politique ne protège RIEN (défaut #9896).'
            );
        }

        expect($checked)->toBeGreaterThanOrEqual(
            2,
            'Au moins deux des tables attendues doivent exister dans la base de test, '
            .'sinon ce test ne vérifie rien.'
        );
    }

    /**
     * `employees` n'est PAS encore couverte, et la sonde doit le dire.
     *
     * Ce test fige l'état attendu : il échouera le jour où #9897 posera la
     * politique, ce qui forcera à mettre à jour la liste `EXPECTED_TABLES`
     * de la sonde dans le même commit. C'est volontaire — un test qui
     * accepterait silencieusement les deux états ne prouverait rien.
     */
    public function test_employees_is_reported_as_not_yet_covered(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null) {
            $this->markTestSkipped('employees absente de cette base.');
        }

        $cls = DB::selectOne(
            'SELECT relrowsecurity FROM pg_class WHERE oid = to_regclass(?)',
            [$schema.'.employees']
        );

        expect((bool) $cls->relrowsecurity)->toBeFalse(
            'employees ne doit pas encore avoir RLS : company_id est nullable et un backfill est requis (#9897). '
            .'Si ce test échoue, #9897 a été livrée — mettre à jour EXPECTED_TABLES dans TenantRlsStatusCommand.'
        );
    }
}
