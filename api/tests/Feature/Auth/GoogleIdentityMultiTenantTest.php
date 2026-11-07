<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #9096 — « même personne, deux schémas tenant RÉELS ».
 *
 * ## Pourquoi une classe dédiée
 *
 * Le test d'origine (`GoogleIdentityBindingTest::test_google_callback_rejects_ambiguous_email_across_tenants`)
 * créait deux entreprises dans **un seul** schéma. Or `employees_email_unique`
 * est un index **par schéma tenant** : le second INSERT levait
 * `SQLSTATE[23505]`, un artefact du harnais mono-schéma (`CreatesMvpSchema`),
 * pas un comportement de production (audit IAM §4.2 #5). Le test ne prouvait
 * donc rien sur la résolution cross-tenant.
 *
 * Il vit ici, sur le harnais `CreatesMvpSchema` — le seul qui crée de vrais
 * schémas tenant séparés (même base que `TenantAmbiguityProofTest`). Le trait
 * `RefreshTenantDatabase` ne convient pas : il enveloppe le test dans une
 * transaction, et le DDL PostgreSQL y est transactionnel — un `CREATE SCHEMA`
 * suivi d'une requête en échec avorte la transaction entière (`25P02`).
 *
 * ## Ce que le test prouve
 *
 * On interroge directement `AuthService::resolveEmployeeByEmail()` — l'unité
 * réellement en cause (#6531) — plutôt que le callback HTTP complet, dont le
 * chemin touche des tables hors du périmètre de cette preuve. Avec deux
 * employés homonymes dans deux schémas distincts, la résolution est **bornée
 * au tenant** : elle ne lève plus, et rend l'employé de SON schéma.
 */
final class GoogleIdentityMultiTenantTest extends TestCase
{
    use CreatesMvpSchema;

    private const SCHEMA_A = 't_dup_a';

    private const SCHEMA_B = 't_dup_b';

    private const EMAIL = 'dup@example.com';

    private const PASSWORD = 'UnMdp!Solide2026x';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Preuve multi-schémas : PostgreSQL uniquement.');
        }

        $this->dropProofSchemas();
    }

    protected function tearDown(): void
    {
        $this->dropProofSchemas();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_same_email_in_two_real_tenant_schemas_resolves_within_one_tenant(): void
    {
        $this->createProofSchema(self::SCHEMA_A, 'DUP-A');
        $this->createProofSchema(self::SCHEMA_B, 'DUP-B');

        $companyA = $this->seedMembership(self::SCHEMA_A, 'DUP-A', 'Dup Alpha');
        $companyB = $this->seedMembership(self::SCHEMA_B, 'DUP-B', 'Dup Beta');

        // Les deux employés homonymes existent bel et bien, chacun dans SON
        // schéma : c'est la situation que le harnais mono-schéma ne pouvait
        // pas représenter (le 2ᵉ INSERT levait 23505).
        $this->assertSame(1, $this->countInSchema(self::SCHEMA_A));
        $this->assertSame(1, $this->countInSchema(self::SCHEMA_B));

        /** @var AuthService $auth */
        $auth = app(AuthService::class);

        $resolved = $auth->resolveEmployeeByEmail(self::EMAIL);

        // Plus de 23505 : la résolution est bornée au tenant.
        $this->assertNotNull($resolved, 'La résolution doit aboutir, pas lever.');
        $employee = $resolved['employee'];
        $schema = $resolved['tenant_schema'];

        // L'employé rendu appartient à UN des deux schémas — jamais aux deux.
        $this->assertContains(
            $schema,
            [self::SCHEMA_A, self::SCHEMA_B],
            'La résolution doit désigner un schéma tenant réel.',
        );

        $expectedCompanyId = $schema === self::SCHEMA_A ? $companyA->id : $companyB->id;
        $this->assertSame(
            $expectedCompanyId,
            $employee->company_id,
            'L\'employé rendu doit être celui du schéma résolu : jamais de fuite cross-tenant.',
        );
    }

    /**
     * Crée un schéma tenant réel avec sa table `employees` (miroir de la
     * migration `tenant/2026_04_01_000101`).
     */
    private function createProofSchema(string $schema, string $matricule): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS '.$schema);

        DB::statement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s.employees (
                id serial PRIMARY KEY,
                matricule varchar(40) NOT NULL,
                first_name varchar(80) NOT NULL,
                last_name varchar(80) NOT NULL,
                email varchar(150) NOT NULL,
                phone varchar(40) NULL,
                password_hash varchar(255) NOT NULL,
                role varchar(20) NOT NULL DEFAULT \'employee\',
                manager_role varchar(30) NULL,
                status varchar(20) NOT NULL DEFAULT \'active\',
                company_id uuid NULL,
                google_id varchar(255) NULL,
                two_fa_enabled_at timestamptz NULL,
                last_login_at timestamptz NULL,
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )',
            $schema,
        ));

        DB::statement(sprintf(
            'INSERT INTO %s.employees (matricule, first_name, last_name, email, phone, password_hash, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            $schema,
        ), [
            $matricule,
            'Dup',
            'Person',
            self::EMAIL,
            '+221770000001',
            Hash::make(self::PASSWORD),
            'employee',
            'active',
        ]);
    }

    /**
     * Enregistre l'appartenance : entreprise pointant sur le schéma, puis
     * `company_id` posé sur l'employé — le geste de production.
     */
    private function seedMembership(string $schema, string $matricule, string $companyName): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $companyName,
            'schema_name' => $schema,
        ]);

        DB::statement(
            sprintf('UPDATE %s.employees SET company_id = ? WHERE matricule = ?', $schema),
            [$company->id, $matricule],
        );

        return $company;
    }

    private function countInSchema(string $schema): int
    {
        $row = DB::selectOne(
            sprintf('SELECT count(*) AS n FROM %s.employees WHERE email = ?', $schema),
            [self::EMAIL],
        );

        return (int) $row->n;
    }

    private function dropProofSchemas(): void
    {
        foreach ([self::SCHEMA_A, self::SCHEMA_B] as $schema) {
            DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::table('public.companies')
                ->whereIn('schema_name', [self::SCHEMA_A, self::SCHEMA_B])
                ->delete();
            DB::table('public.user_lookups')
                ->where('email', self::EMAIL)
                ->delete();
        } catch (\Throwable) {
            // Table absente selon le stade du harnais : sans objet.
        }
    }
}
