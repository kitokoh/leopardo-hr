<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * IAM-L2 (#9092) — sélection du contexte tenant.
 *
 * ## Pourquoi une classe dédiée, sur deux schémas RÉELS
 *
 * Le défaut corrigé n'existe QUE quand une personne appartient à deux
 * entreprises : `AuthService::findEmployeeInTenantSchemas()` retournait le
 * premier employé trouvé, dans l'ordre de `public.companies`. Le harnais
 * `CreatesMvpSchema` est mono-schéma — il ne peut pas représenter deux
 * appartenances réelles (audit IAM §4.3). On crée donc deux schémas tenant
 * distincts, comme `GoogleIdentityMultiTenantTest` et
 * `TenantAmbiguityProofTest`.
 *
 * ## Ce que ces tests prouvent
 *
 * 1. `GET /auth/contexts` liste **toutes** les entreprises de la personne ;
 * 2. `POST /auth/context` sur une entreprise NON possédée rend **403** — le
 *    test critique : aucune escalade, aucun jeton émis ;
 * 3. le jeton réémis est **borné** à l'entreprise choisie (ability
 *    `tenant_company:<id>`) ;
 * 4. la sélection **n'élargit pas** les permissions ;
 * 5. une personne de deux entreprises obtient **deux rôles différents** —
 *    scénario #5 de la matrice, enfin couvert.
 */
final class TenantContextSelectionTest extends TestCase
{
    use CreatesMvpSchema;

    private const SCHEMA_A = 't_ctx_a';

    private const SCHEMA_B = 't_ctx_b';

    private const EMAIL = 'multi@example.com';

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

    public function test_contexts_lists_every_company_of_the_person(): void
    {
        [$companyA, $companyB] = $this->seedTwoMemberships();

        $this->actingAsMultiTenantEmployee();

        $response = $this->getJson('/api/v1/auth/contexts');

        $response->assertOk();

        /** @var list<array{company_id: string}> $contexts */
        $contexts = $response->json('data');

        $ids = array_column($contexts, 'company_id');

        $this->assertCount(2, $contexts, 'Les DEUX entreprises doivent être listées.');
        $this->assertContains((string) $companyA->id, $ids);
        $this->assertContains((string) $companyB->id, $ids);
    }

    public function test_employee_cannot_select_a_company_they_do_not_belong_to(): void
    {
        $this->seedTwoMemberships();

        // Entreprise TIERCE : la personne n'y a AUCUN lien.
        /** @var Company $foreign */
        $foreign = Company::factory()->create([
            'name' => 'Foreign Corp',
            'schema_name' => 't_ctx_foreign',
        ]);

        $this->actingAsMultiTenantEmployee();

        $response = $this->postJson('/api/v1/auth/context', [
            'company_id' => (string) $foreign->id,
        ]);

        // LE test critique : escalade refusée, aucun jeton émis.
        $response->assertStatus(403);
        $response->assertJson(['error' => 'TENANT_CONTEXT_FORBIDDEN']);
        $this->assertNull($response->json('data.token'), 'Aucun jeton ne doit être émis.');
    }

    public function test_issued_token_is_bound_to_the_selected_company(): void
    {
        [$companyA] = $this->seedTwoMemberships();

        $this->actingAsMultiTenantEmployee();

        $response = $this->postJson('/api/v1/auth/context', [
            'company_id' => (string) $companyA->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.company_id', (string) $companyA->id);
        $response->assertJsonPath('data.tenant_schema', self::SCHEMA_A);

        $plainToken = (string) $response->json('data.token');
        $this->assertNotSame('', $plainToken, 'Un jeton doit être émis.');

        // Le jeton porte bien l'ability bornée à l'entreprise choisie.
        $tokenId = (int) explode('|', $plainToken, 2)[0];
        $stored = DB::table('personal_access_tokens')->where('id', $tokenId)->first();

        $this->assertNotNull($stored, 'Le jeton doit être persisté.');
        $abilities = json_decode((string) $stored->abilities, true);

        $this->assertIsArray($abilities);
        $this->assertContains('tenant_company:'.$companyA->id, $abilities);
        $this->assertContains('tenant_schema:'.self::SCHEMA_A, $abilities);
    }

    public function test_selecting_context_does_not_widen_permissions(): void
    {
        [$companyA] = $this->seedTwoMemberships();

        $this->actingAsMultiTenantEmployee();

        $response = $this->postJson('/api/v1/auth/context', [
            'company_id' => (string) $companyA->id,
        ]);

        $response->assertOk();

        // Le rôle rendu est celui de l'employé DANS cette entreprise — un
        // simple employé, jamais promu par la sélection.
        $response->assertJsonPath('data.role', 'employee');
        $this->assertNull($response->json('data.manager_role'));
    }

    public function test_person_in_two_companies_gets_different_roles(): void
    {
        [$companyA, $companyB] = $this->seedTwoMemberships();

        $this->actingAsMultiTenantEmployee();

        $contexts = $this->getJson('/api/v1/auth/contexts')->json('data');

        $byCompany = [];
        foreach ($contexts as $context) {
            $byCompany[$context['company_id']] = $context;
        }

        // Scénario #5 de la matrice : la MÊME personne, DEUX rôles distincts.
        $this->assertSame('employee', $byCompany[(string) $companyA->id]['role']);
        $this->assertSame('manager', $byCompany[(string) $companyB->id]['role']);
        $this->assertSame('principal', $byCompany[(string) $companyB->id]['manager_role']);

        // Et les permissions suivent le rôle de CHAQUE entreprise.
        $this->assertNotContains('is_principal', $byCompany[(string) $companyA->id]['permissions']);
        $this->assertContains('is_principal', $byCompany[(string) $companyB->id]['permissions']);
    }

    /**
     * Crée deux schémas tenant réels, deux entreprises, un `users` et deux
     * appartenances : la personne est `employee` chez A, `manager/principal`
     * chez B.
     *
     * @return array{0: Company, 1: Company}
     */
    private function seedTwoMemberships(): array
    {
        $this->createProofSchema(self::SCHEMA_A, 'CTX-A', 'employee', null);
        $this->createProofSchema(self::SCHEMA_B, 'CTX-B', 'manager', 'principal');

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'name' => 'Ctx Alpha',
            'schema_name' => self::SCHEMA_A,
        ]);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'name' => 'Ctx Beta',
            'schema_name' => self::SCHEMA_B,
        ]);

        $employeeA = $this->attachEmployeeToCompany(self::SCHEMA_A, 'CTX-A', $companyA);
        $employeeB = $this->attachEmployeeToCompany(self::SCHEMA_B, 'CTX-B', $companyB);

        /** @var User $user */
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'password_hash' => Hash::make(self::PASSWORD),
        ]);

        DB::table('user_employee_links')->insert([
            [
                'user_id' => $user->id,
                'employee_id' => $employeeA->id,
                'company_id' => $companyA->id,
                'status' => 'active',
                'linked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'employee_id' => $employeeB->id,
                'company_id' => $companyB->id,
                'status' => 'active',
                'linked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [$companyA, $companyB];
    }

    /**
     * Authentifie l'appelant comme l'employé du schéma A (l'identité de
     * session), sans passer par le login HTTP : on teste la SÉLECTION, pas
     * l'authentification (déjà couverte ailleurs).
     */
    private function actingAsMultiTenantEmployee(): void
    {
        // L'identité de session est celle de l'employé du schéma A : on pose
        // donc le search_path AVANT de le charger (sans quoi la requête ne
        // résout aucune table `employees`).
        $previous = DB::selectOne('SHOW search_path');
        $previousPath = is_object($previous) && isset($previous->search_path) && is_string($previous->search_path)
            ? $previous->search_path
            : null;

        try {
            DB::statement('SET search_path TO "'.self::SCHEMA_A.'",public');

            /** @var Employee $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('email', self::EMAIL)
                ->firstOrFail();
        } finally {
            if ($previousPath !== null && $previousPath !== '') {
                DB::statement('SET search_path TO '.$previousPath);
            }
        }

        Sanctum::actingAs($employee, ['*']);
    }

    private function createProofSchema(string $schema, string $matricule, string $role, ?string $managerRole): void
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
            'CREATE TABLE IF NOT EXISTS %s.employee_module_grants (
                id serial PRIMARY KEY,
                employee_id integer NOT NULL,
                company_id uuid NULL,
                module_key varchar(60) NOT NULL,
                granted_at timestamptz NULL,
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )',
            $schema,
        ));

        DB::statement(sprintf(
            'INSERT INTO %s.employees (matricule, first_name, last_name, email, phone, password_hash, role, manager_role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $schema,
        ), [
            $matricule,
            'Multi',
            'Person',
            self::EMAIL,
            '+221770000009',
            Hash::make(self::PASSWORD),
            $role,
            $managerRole,
            'active',
        ]);
    }

    private function attachEmployeeToCompany(string $schema, string $matricule, Company $company): Employee
    {
        DB::statement(
            sprintf('UPDATE %s.employees SET company_id = ? WHERE matricule = ?', $schema),
            [$company->id, $matricule],
        );

        $previous = DB::selectOne('SHOW search_path');
        $previousPath = is_object($previous) && isset($previous->search_path) && is_string($previous->search_path)
            ? $previous->search_path
            : null;

        try {
            DB::statement('SET search_path TO "'.$schema.'",public');

            /** @var Employee $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('matricule', $matricule)
                ->firstOrFail();

            return $employee;
        } finally {
            if ($previousPath !== null && $previousPath !== '') {
                DB::statement('SET search_path TO '.$previousPath);
            }
        }
    }

    private function dropProofSchemas(): void
    {
        foreach ([self::SCHEMA_A, self::SCHEMA_B, 't_ctx_foreign'] as $schema) {
            DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::table('public.companies')
                ->whereIn('schema_name', [self::SCHEMA_A, self::SCHEMA_B, 't_ctx_foreign'])
                ->delete();
            DB::table('public.user_employee_links')
                ->whereIn('company_id', function ($query): void {
                    $query->select('id')->from('public.companies')
                        ->whereIn('schema_name', [self::SCHEMA_A, self::SCHEMA_B, 't_ctx_foreign']);
                })
                ->delete();
            DB::table('public.users')->where('email', self::EMAIL)->delete();
        } catch (\Throwable) {
            // Table absente selon le stade du harnais : sans objet.
        }
    }
}
