<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Evaluation;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EvaluationSecurityTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_cannot_create_evaluation_for_employee_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/evaluations', [
                'employee_id' => $employeeB->id,
                'period' => '2026-Q1',
                'score' => 4.5,
                'overall_comment' => 'Nice work',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_view_evaluation_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $evaluationB = Evaluation::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'evaluator_id' => $managerB->id,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/evaluations/{$evaluationB->id}");

        // BelongsToCompany trait scopes queries to actor's company_id
        // So EvaluationB is not found for ManagerA, returning 404 (ModelNotFound)
        $response->assertStatus(403);
    }

    public function test_manager_cannot_update_evaluation_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $evaluationB = Evaluation::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'evaluator_id' => $managerB->id,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/evaluations/{$evaluationB->id}", [
                'score' => 5.0,
            ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_delete_evaluation_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $evaluationB = Evaluation::create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'evaluator_id' => $managerB->id,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->deleteJson("/api/v1/evaluations/{$evaluationB->id}");

        $response->assertStatus(403);
    }

    public function test_employee_cannot_view_evaluation_of_another_employee(): void
    {
        $company = $this->createCompany('Company A');

        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');
        $manager = $this->createEmployee($company, 'manager', 'principal');

        $evaluation2 = Evaluation::create([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q1',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->getJson("/api/v1/evaluations/{$evaluation2->id}");

        $response->assertStatus(403);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
