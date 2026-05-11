<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createContractSchemaIfNeeded();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createContractSchemaIfNeeded(): void
    {
        if (Schema::hasTable('contracts')) {
            return;
        }

        Schema::create('contracts', function ($t) {
            $t->increments('id');
            $t->uuid('company_id');
            $t->unsignedInteger('employee_id');
            $t->string('contract_type', 20);
            $t->string('reference', 50)->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('job_title', 150)->nullable();
            $t->unsignedInteger('department_id')->nullable();
            $t->unsignedInteger('position_id')->nullable();
            $t->decimal('base_salary', 12, 2)->default(0);
            $t->string('currency', 3)->default('DZD');
            $t->string('salary_frequency', 10)->default('monthly');
            $t->decimal('work_hours_per_week', 5, 2)->nullable();
            $t->date('probation_end_date')->nullable();
            $t->json('benefits')->nullable();
            $t->json('clauses')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_document_path')->nullable();
            $t->text('termination_reason')->nullable();
            $t->timestamp('terminated_at')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
        });

        if (! Schema::hasTable('contract_amendments')) {
            Schema::create('contract_amendments', function ($t) {
                $t->increments('id');
                $t->unsignedInteger('contract_id');
                $t->uuid('company_id');
                $t->string('amendment_type', 30);
                $t->json('changes');
                $t->date('effective_date');
                $t->text('reason')->nullable();
                $t->unsignedInteger('approved_by')->nullable();
                $t->string('document_path')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    private function makeManagerAndCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Contract Co',
            'slug' => 'contract-co',
            'sector' => 'construction',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'contract@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'MGR-C01',
            'first_name' => 'Boss',
            'last_name' => 'RH',
            'email' => 'boss@contract-co.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-C01',
            'first_name' => 'Yacine',
            'last_name' => 'B',
            'email' => 'yacine@contract-co.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    public function test_create_draft_contract(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/contracts', [
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-06-01',
            'base_salary' => 85000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.contract_type', 'cdi');
    }

    public function test_activate_draft_contract(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdd',
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'base_salary' => 50000,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        $response = $this->postJson("/api/v1/contracts/{$contract->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $this->assertNotNull($contract->fresh()->signed_at);
    }

    public function test_suspend_active_contract(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-01-01',
            'base_salary' => 70000,
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $response = $this->postJson("/api/v1/contracts/{$contract->id}/suspend");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'suspended');
    }

    public function test_terminate_contract_requires_reason(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-01-01',
            'base_salary' => 60000,
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $response = $this->postJson("/api/v1/contracts/{$contract->id}/terminate", [
            'termination_reason' => 'End of project',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'terminated');
        $this->assertNotNull($contract->fresh()->terminated_at);
    }

    public function test_renew_creates_new_contract(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdd',
            'start_date' => '2025-06-01',
            'end_date' => '2026-06-01',
            'base_salary' => 45000,
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $response = $this->postJson("/api/v1/contracts/{$contract->id}/renew", [
            'start_date' => '2026-06-02',
            'end_date' => '2027-06-01',
            'base_salary' => 50000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.base_salary', 50000);
        $this->assertEquals('expired', $contract->fresh()->status);
    }

    public function test_self_service_my_contracts(): void
    {
        [$company, $manager, $employee] = $this->makeManagerAndCompany();
        Sanctum::actingAs($employee);

        Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-01-01',
            'base_salary' => 55000,
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $response = $this->getJson('/api/v1/me/contracts');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'contract_type', 'status']]]);
        $response->assertJsonCount(1, 'data');
    }
}
