<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveAccrual;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class LeavePolicyApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createLeaveSchemaIfNeeded();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createLeaveSchemaIfNeeded(): void
    {
        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function ($t) {
                $t->increments('id');
                $t->uuid('company_id');
                $t->unsignedInteger('absence_type_id');
                $t->string('name', 150);
                $t->string('accrual_type', 20)->default('monthly');
                $t->decimal('accrual_amount', 8, 2)->default(0);
                $t->decimal('max_balance', 8, 2)->nullable();
                $t->boolean('carry_forward')->default(false);
                $t->decimal('carry_forward_max', 8, 2)->nullable();
                $t->unsignedInteger('carry_forward_expiry_days')->nullable();
                $t->boolean('requires_approval')->default(true);
                $t->unsignedSmallInteger('approval_levels')->nullable();
                $t->unsignedSmallInteger('min_notice_days')->nullable();
                $t->unsignedSmallInteger('max_consecutive_days')->nullable();
                $t->json('applicable_roles')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function ($t) {
                $t->increments('id');
                $t->uuid('company_id');
                $t->unsignedInteger('employee_id');
                $t->unsignedInteger('absence_type_id');
                $t->decimal('balance', 8, 2)->default(0);
                $t->decimal('used', 8, 2)->default(0);
                $t->decimal('pending', 8, 2)->default(0);
                $t->unsignedSmallInteger('year');
                $t->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('leave_accruals')) {
            Schema::create('leave_accruals', function ($t) {
                $t->increments('id');
                $t->uuid('company_id');
                $t->unsignedInteger('employee_id');
                $t->unsignedInteger('leave_policy_id');
                $t->decimal('amount', 8, 2);
                $t->string('type', 30);
                $t->string('description')->nullable();
                $t->date('effective_date');
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    private function makeManagerAndCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Leave Test Co',
            'slug' => 'leave-test',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'leave@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'MGR-001',
            'first_name' => 'Manager',
            'last_name' => 'Test',
            'email' => 'mgr@leave-test.com',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return [$company, $manager];
    }

    public function test_list_leave_policies(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);
        $otherCompany = Company::factory()->create();

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'requires_approval' => true,
        ]);

        LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => $absenceType->id,
            'name' => 'Standard Annual',
            'accrual_type' => 'monthly',
            'accrual_amount' => 2.5,
            'active' => true,
        ]);
        LeavePolicy::query()->create([
            'company_id' => $otherCompany->id,
            'absence_type_id' => AbsenceType::query()->create([
                'company_id' => $otherCompany->id,
                'code' => 'OTHER',
                'name' => 'Other Tenant Leave',
                'requires_approval' => true,
            ])->id,
            'name' => 'Other Tenant Policy',
            'accrual_type' => 'monthly',
            'accrual_amount' => 2.5,
            'active' => true,
        ]);

        $response = $this->getJson('/api/v1/leave-policies');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'accrual_type']]]);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Standard Annual');
    }

    public function test_store_leave_policy(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'SICK',
            'name' => 'Sick Leave',
            'requires_approval' => true,
        ]);

        $response = $this->postJson('/api/v1/leave-policies', [
            'absence_type_id' => $absenceType->id,
            'name' => 'Sick Policy',
            'accrual_type' => 'monthly',
            'accrual_amount' => 1.5,
            'max_balance' => 30,
            'carry_forward' => false,
            'requires_approval' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Sick Policy');
    }

    public function test_deactivate_leave_policy(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'COMP',
            'name' => 'Compensatory',
            'requires_approval' => false,
        ]);

        $policy = LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => $absenceType->id,
            'name' => 'To Deactivate',
            'accrual_type' => 'manual',
            'accrual_amount' => 0,
            'active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/leave-policies/{$policy->id}");

        $response->assertOk();
        $this->assertFalse($policy->fresh()->active);
    }

    public function test_leave_balances_are_scoped_to_actor_company_and_employee_role(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $otherEmployee = Employee::factory()->create(['company_id' => Company::factory()->create()->id]);
        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'ANNUAL-BAL',
            'name' => 'Annual Balance',
            'requires_approval' => true,
        ]);

        LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'balance' => 12,
            'used' => 2,
            'pending' => 1,
            'year' => 2026,
        ]);
        LeaveBalance::query()->create([
            'company_id' => $otherEmployee->company_id,
            'employee_id' => $otherEmployee->id,
            'absence_type_id' => $absenceType->id,
            'balance' => 99,
            'used' => 0,
            'pending' => 0,
            'year' => 2026,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/leave-balances?year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/leave-balances?year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id);
    }

    public function test_manager_can_store_accrual_and_balance_for_own_company_only(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'ACCRUAL',
            'name' => 'Accrual Leave',
            'requires_approval' => true,
        ]);
        $policy = LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => $absenceType->id,
            'name' => 'Accrual Policy',
            'accrual_type' => 'manual',
            'accrual_amount' => 0,
            'active' => true,
        ]);
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherPolicy = LeavePolicy::query()->create([
            'company_id' => $otherCompany->id,
            'absence_type_id' => AbsenceType::query()->create([
                'company_id' => $otherCompany->id,
                'code' => 'OTHER-ACC',
                'name' => 'Other Accrual',
                'requires_approval' => true,
            ])->id,
            'name' => 'Other Policy',
            'accrual_type' => 'manual',
            'accrual_amount' => 0,
            'active' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/leave-accruals', [
            'employee_id' => $otherEmployee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 2,
            'type' => 'accrual',
            'effective_date' => '2026-05-01',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/leave-accruals', [
            'employee_id' => $employee->id,
            'leave_policy_id' => $otherPolicy->id,
            'amount' => 2,
            'type' => 'accrual',
            'effective_date' => '2026-05-01',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/leave-accruals', [
            'employee_id' => $employee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 2.5,
            'type' => 'accrual',
            'description' => 'Manual opening balance',
            'effective_date' => '2026-05-01',
        ])->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id);

        $this->assertDatabaseHas('leave_accruals', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_policy_id' => $policy->id,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'year' => 2026,
        ]);
    }

    public function test_leave_accrual_index_is_tenant_scoped(): void
    {
        [$company, $manager] = $this->makeManagerAndCompany();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $policy = LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => AbsenceType::query()->create([
                'company_id' => $company->id,
                'code' => 'ACC-IDX',
                'name' => 'Accrual Index',
                'requires_approval' => true,
            ])->id,
            'name' => 'Index Policy',
            'accrual_type' => 'manual',
            'accrual_amount' => 0,
            'active' => true,
        ]);
        LeaveAccrual::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 1,
            'type' => 'accrual',
            'effective_date' => '2026-05-01',
        ]);
        LeaveAccrual::query()->create([
            'company_id' => Company::factory()->create()->id,
            'employee_id' => $employee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 99,
            'type' => 'accrual',
            'effective_date' => '2026-05-01',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/leave-accruals')
            ->assertOk()
            ->assertJsonPath('data.0.company_id', $company->id)
            ->assertJsonCount(1, 'data');
    }
}
