<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4428 — régression : EmployeeService::create() passait les clés sensibles
 * (company_id, role, manager_role, status, salary_base) à Employee::create(),
 * mais elles ne sont PLUS mass-assignables depuis le durcissement fillable
 * #3677 → Eloquent les abandonnait silencieusement :
 *   - salary_base fourni à la création → 0 en base (données paie corrompues) ;
 *   - role=manager + manager_role fournis → employé créé avec role='employee'
 *     (défaut colonne) → perte RBAC silencieuse ;
 *   - company_id fourni hors surface tenant (jobs/provisioning) → NULL.
 */
class EmployeeServiceCreateFillableTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_create_persists_salary_base_role_manager_role_and_company(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Acme Fillable QA',
            'slug' => 'acme-fillable-qa',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'company@acme-fillable.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'currency' => 'DZD',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal, [], 'sanctum');

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'email' => 'karim.benali@acme-fillable.test',
            'password' => 'ProvidedPass123!',
            'role' => 'manager',
            'manager_role' => 'rh',
            'salary_base' => 180000,
            'salary_type' => 'fixed',
            'contract_type' => 'CDI',
            'contract_start' => '2026-01-15',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'karim.benali@acme-fillable.test');

        DB::statement('SET search_path TO shared_tenants,public');

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->where('email', 'karim.benali@acme-fillable.test')
            ->first();

        $this->assertNotNull($employee, 'L\'employé doit exister en base.');
        $this->assertSame(
            $company->id,
            $employee->company_id,
            'company_id doit être persisté (tenant de l\'acteur) — #4428.'
        );
        $this->assertSame(
            'manager',
            $employee->role,
            'role=manager fourni doit être persisté (pas le défaut colonne employee) — #4428.'
        );
        $this->assertSame(
            'rh',
            $employee->manager_role,
            'manager_role=rh fourni doit être persisté — #4428.'
        );
        $this->assertSame(
            'active',
            $employee->status,
            'status actif doit être persisté.'
        );
        $this->assertSame(
            180000.0,
            (float) $employee->salary_base,
            'salary_base fourni doit être persisté (pas 0) — #4428.'
        );
    }

    public function test_created_manager_is_visible_in_tenant_scoped_list(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Acme Fillable QA 2',
            'slug' => 'acme-fillable-qa-2',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'company2@acme-fillable.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'currency' => 'DZD',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal, [], 'sanctum');

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Sofiane',
            'last_name' => 'Meziane',
            'email' => 'sofiane.meziane@acme-fillable.test',
            'password' => 'ProvidedPass123!',
            'role' => 'manager',
            'manager_role' => 'rh',
            'salary_base' => 150000,
        ])->assertCreated();

        // L'employé créé doit être visible dans la liste scopée tenant
        // (le scope BelongsToCompany filtre sur company_id courant).
        $list = $this->getJson('/api/v1/employees?search=Sofiane');
        $list->assertOk();
        $list->assertJsonFragment(['email' => 'sofiane.meziane@acme-fillable.test']);
    }

    public function test_update_persists_salary_base_and_manager_role(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Acme Fillable QA 3',
            'slug' => 'acme-fillable-qa-3',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'company3@acme-fillable.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'currency' => 'DZD',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal, [], 'sanctum');

        $created = $this->postJson('/api/v1/employees', [
            'first_name' => 'Yacine',
            'last_name' => 'Haddad',
            'email' => 'yacine.haddad@acme-fillable.test',
            'password' => 'ProvidedPass123!',
            'role' => 'employee',
            'salary_base' => 120000,
        ]);
        $created->assertCreated();
        $employeeId = $created->json('data.id');

        // PATCH : le manager met à jour salary_base + promeut en RH.
        $updated = $this->patchJson("/api/v1/employees/{$employeeId}", [
            'salary_base' => 220000,
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);
        $updated->assertOk();

        DB::statement('SET search_path TO shared_tenants,public');

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->where('email', 'yacine.haddad@acme-fillable.test')
            ->first();

        $this->assertNotNull($employee);
        $this->assertSame(220000.0, (float) $employee->salary_base, 'salary_base PATCHé doit être persisté — #4428.');
        $this->assertSame('manager', $employee->role, 'role promu via PATCH doit être persisté — #4428.');
        $this->assertSame('rh', $employee->manager_role, 'manager_role promu via PATCH doit être persisté — #4428.');
    }
}
