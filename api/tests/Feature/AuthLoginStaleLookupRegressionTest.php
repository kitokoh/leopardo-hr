<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Régression session QA expert 2026-08-15 — login 500 (issue #2652).
 *
 * Cause racine constatée en production : une entrée `public.user_lookups`
 * périmée pointe vers un schéma tenant qui n'existe plus (reset de la démo,
 * drop/recréation de schéma). Le chemin rapide de `AuthService::login()`
 * fait `setTenantSearchPath($lookupSchema)` puis interroge `employees`
 * SANS vérifier que le schéma/table existe, contrairement à
 * `findEmployeeInTenantSchemas()` qui passe par `tenantEmployeesTableExists()`.
 * Résultat : QueryException → 500 `{"message":"Server Error"}` pour tout
 * utilisateur existant dont le lookup est périmé.
 *
 * Comportement attendu : dégradation propre — soit l'employé est retrouvé via
 * le scan des schémas, soit la réponse est 401 INVALID_CREDENTIALS contrôlée.
 */
class AuthLoginStaleLookupRegressionTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        DB::table('public.user_lookups')->truncate();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function seedEmployeeInSharedTenant(): Company
    {
        $company = Company::query()->create([
            'name' => 'Company Ghost',
            'slug' => 'company-ghost',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'ghost@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $sensitiveEmployee0 = new Employee([
            'email' => 'manager@ghost.test',
        ]);
        $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee0->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ])->save();

        return $company;
    }

    private function seedStaleLookup(string $email, string $schemaName, int $employeeId, string $companyId): void
    {
        // L'Employee sync automatiquement user_lookups au save ; on simule la
        // péremption en mettant à jour le schéma vers une valeur fantôme.
        DB::table('public.user_lookups')
            ->where('email', $email)
            ->update(['schema_name' => $schemaName]);
    }

    public function test_login_with_stale_lookup_schema_does_not_500(): void
    {
        $company = $this->seedEmployeeInSharedTenant();
        $employee = Employee::query()->where('email', 'manager@ghost.test')->firstOrFail();

        // Le lookup pointe vers un schéma qui n'existe pas (reset démo).
        $this->seedStaleLookup(
            'manager@ghost.test',
            'ghost_schema_dropped',
            (int) $employee->id,
            (string) $company->id,
        );

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@ghost.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        // NI 500 ni exception : soit login réussi via fallback (200),
        // soit 401 INVALID_CREDENTIALS contrôlée — jamais 500.
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 401, 422]);
    }

    public function test_login_with_stale_lookup_recovers_employee_via_schema_scan(): void
    {
        $company = $this->seedEmployeeInSharedTenant();
        $employee = Employee::query()->where('email', 'manager@ghost.test')->firstOrFail();

        $this->seedStaleLookup(
            'manager@ghost.test',
            'ghost_schema_dropped',
            (int) $employee->id,
            (string) $company->id,
        );

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@ghost.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        // L'employé existe réellement dans shared_tenants : le scan des
        // schémas doit le retrouver → login OK (200) avec token.
        $response->assertOk();
        $response->assertJsonPath('data.email', 'manager@ghost.test');
        $this->assertNotNull($response->json('token'));
    }
}
