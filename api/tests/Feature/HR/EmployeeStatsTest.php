<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #eval-2026-11-23 — `GET /v1/employees/stats` (KPIs d'effectif).
 *
 * La route a ete ajoutee pour homogeneiser la famille `/stats` (cf.
 * `/absences/stats`, `/expense/stats`) : elle n'existait pas, alors que la
 * matrice RH la documentait. Ce test verrouille le contrat :
 *
 *  - forme de la reponse (`EmployeeStatsService::overview`) ;
 *  - perimetre EQUIPE : un superviseur ne lit que son equipe, jamais
 *    l'effectif complet (sinon les stats sont une fuite laterale) ;
 *  - RBAC : un employe lambda recoit 403 (policy `viewAny`) ;
 *  - isolation tenant : les employes d'une autre societe ne comptent jamais ;
 *  - filtres `from`/`to` valides, format de date invalide -> 422.
 */
class EmployeeStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_principal_reads_company_wide_stats(): void
    {
        [$company, $principal] = $this->createActors();

        $this->createEmployee($company, 'a@stats.test', 'employee', null, 'active', 500000.0);
        $this->createEmployee($company, 'b@stats.test', 'employee', null, 'active', 300000.0);
        $this->createEmployee($company, 'c@stats.test', 'employee', null, 'archived', null);

        Sanctum::actingAs($principal);

        $response = $this->getJson('/api/v1/employees/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'by_status',
                    'by_role',
                    'by_manager_role',
                    'by_contract_type',
                    'by_department',
                    'headcount' => ['active', 'archived', 'suspended'],
                    'contracts_expiring' => ['in_30_days', 'in_60_days', 'in_90_days'],
                    'payroll' => ['currency', 'monthly_base_total', 'average_base', 'headcount_with_salary'],
                    'seniority' => ['average_months', 'new_hires_90_days'],
                ],
            ]);

        // 3 employes crees + le principal lui-meme.
        $this->assertSame(4, $response->json('data.total'));
        $this->assertSame(3, $response->json('data.headcount.active'));
        $this->assertSame(1, $response->json('data.headcount.archived'));
        $this->assertSame('DZD', $response->json('data.payroll.currency'));
        $this->assertSame(2, $response->json('data.payroll.headcount_with_salary'));
        $this->assertSame(800000.0, (float) $response->json('data.payroll.monthly_base_total'));
        $this->assertSame(400000.0, (float) $response->json('data.payroll.average_base'));
    }

    public function test_superviseur_only_sees_his_team(): void
    {
        [$company, $principal] = $this->createActors();

        $superviseur = $this->createEmployee($company, 'sup@stats.test', 'manager', 'superviseur', 'active', null);

        // Deux membres rattaches au superviseur, deux hors de son equipe.
        $this->createEmployee($company, 'team1@stats.test', 'employee', null, 'active', null, $superviseur->id);
        $this->createEmployee($company, 'team2@stats.test', 'employee', null, 'active', null, $superviseur->id);
        $this->createEmployee($company, 'other1@stats.test', 'employee', null, 'active', null);
        $this->createEmployee($company, 'other2@stats.test', 'employee', null, 'active', null);

        Sanctum::actingAs($superviseur);

        $response = $this->getJson('/api/v1/employees/stats');

        $response->assertOk();

        // Le superviseur se voit lui-meme + ses 2 rattaches : jamais les 2 autres.
        $this->assertSame(3, $response->json('data.total'));
        $this->assertSame(3, $response->json('data.headcount.active'));

        // Le principal, lui, voit bien tout le monde.
        Sanctum::actingAs($principal);
        $this->assertSame(6, $this->getJson('/api/v1/employees/stats')->json('data.total'));
    }

    public function test_employee_cannot_read_stats(): void
    {
        [$company, $principal] = $this->createActors();
        $employee = $this->createEmployee($company, 'plain@stats.test', 'employee', null, 'active', null);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/employees/stats')->assertForbidden();
    }

    public function test_stats_never_count_another_tenant(): void
    {
        [$company, $principal] = $this->createActors();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->createEmployee($other, 'foreign@stats.test', 'employee', null, 'active', 999999.0);

        Sanctum::actingAs($principal);

        $response = $this->getJson('/api/v1/employees/stats');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total')); // le principal seul
        $this->assertSame('DZD', $response->json('data.payroll.currency'));
    }

    public function test_stats_accepts_date_filters_and_rejects_bad_format(): void
    {
        [$company, $principal] = $this->createActors();

        Sanctum::actingAs($principal);

        $this->getJson('/api/v1/employees/stats?from=2020-01-01&to=2030-12-31')->assertOk();
        $this->getJson('/api/v1/employees/stats?from=01/01/2020')->assertStatus(422);
    }

    public function test_stats_groups_by_department(): void
    {
        [$company, $principal] = $this->createActors();

        $department = $this->createDepartment($company->id, 'Cuisine', $principal);

        $this->createEmployee($company, 'cook1@stats.test', 'employee', null, 'active', null, null, $department->id);
        $this->createEmployee($company, 'cook2@stats.test', 'employee', null, 'active', null, null, $department->id);

        Sanctum::actingAs($principal);

        /** @var array<int, array{id: string, name: string, count: int}> $byDepartment */
        $byDepartment = $this->getJson('/api/v1/employees/stats')->json('data.by_department');

        $cuisine = collect($byDepartment)->firstWhere('id', $department->id);

        $this->assertNotNull($cuisine);
        $this->assertSame('Cuisine', $cuisine['name']);
        $this->assertSame(2, $cuisine['count']);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function createActors(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        $suffix = substr((string) $company->id, 0, 8);
        $principal = $this->createEmployee($company, 'principal.'.$suffix.'@stats.test', 'manager', 'principal', 'active', null);

        return [$company, $principal];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
        string $status = 'active',
        ?float $salaryBase = null,
        ?int $managerId = null,
        ?int $departmentId = null,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();

        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => $status,
            'salary_base' => $salaryBase ?? 0.0,
            // Enum DB : fixed|hourly|daily. `fixed` = salaire mensuel.
            'salary_type' => 'fixed',
            'manager_id' => $managerId,
            'department_id' => $departmentId,
            'contract_start' => now()->subYear()->toDateString(),
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }

    private function createDepartment(string $companyId, string $name, ?Employee $manager): Department
    {
        $department = Department::create(['name' => $name, 'manager_id' => $manager?->id]);
        $department->company_id = $companyId;
        $department->save();

        return $department;
    }
}
