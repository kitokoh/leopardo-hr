<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-MOB-006 — Salary advance supporting document ("pieces").
 *
 * Mirrors AbsenceProofTest: the manager decision view needs "qui quoi
 * combien pourquoi et pieces" for salary advance requests too.
 */
class SalaryAdvanceProofTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        return $employee;
    }

    public function test_employee_can_attach_a_proof_document_when_requesting_an_advance(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $file = UploadedFile::fake()->create('quote.pdf', 150, 'application/pdf');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/salary-advances', [
                'amount' => 500,
                'reason' => 'Frais medicaux urgents',
                'proof' => $file,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.has_proof', true);

        $advance = SalaryAdvance::query()->firstOrFail();
        $this->assertNotNull($advance->proof_path);
        Storage::disk('local')->assertExists($advance->proof_path);
    }

    public function test_advance_created_without_proof_has_no_proof(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/salary-advances', [
                'amount' => 500,
                'reason' => 'Frais medicaux urgents',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.has_proof', false);
        $response->assertJsonPath('data.proof_path', null);
    }

    public function test_owning_employee_can_download_their_proof(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $path = UploadedFile::fake()->create('quote.pdf', 100)->store('salary_advances/proofs/'.$company->id, 'local');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->get("/api/v1/salary-advances/{$advance->id}/proof");

        $response->assertStatus(200);
    }

    public function test_manager_can_download_a_team_member_proof(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $path = UploadedFile::fake()->create('quote.pdf', 100)->store('salary_advances/proofs/'.$company->id, 'local');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->get("/api/v1/salary-advances/{$advance->id}/proof");

        $response->assertStatus(200);
    }

    public function test_other_employee_cannot_download_a_colleague_proof(): void
    {
        $company = $this->createCompany('Company A');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $path = UploadedFile::fake()->create('quote.pdf', 100)->store('salary_advances/proofs/'.$company->id, 'local');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee1->id,
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        $response = $this->actingAs($employee2, 'sanctum')
            ->get("/api/v1/salary-advances/{$advance->id}/proof");

        $response->assertStatus(403);
    }

    public function test_manager_cannot_download_proof_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $path = UploadedFile::fake()->create('quote.pdf', 100)->store('salary_advances/proofs/'.$companyB->id, 'local');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->get("/api/v1/salary-advances/{$advanceB->id}/proof");

        $response->assertStatus(404);
    }

    public function test_download_returns_404_when_no_proof_attached(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->get("/api/v1/salary-advances/{$advance->id}/proof");

        $response->assertStatus(404);
    }
}
