<?php

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SalaryAdvanceSecurityTest extends TestCase
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

    public function test_employee_can_only_see_their_own_salary_advances(): void
    {
        $company = $this->createCompany('Company A');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee1->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employee1->id);
        $response->assertJsonPath('data.0.validation_status', 'pending');
        $response->assertJsonPath('data.0.employee.email', $employee1->email);
    }

    public function test_manager_cannot_list_salary_advances_of_another_tenant_employee(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeA = $this->createEmployee($companyA, 'employee');
        $employeeB = $this->createEmployee($companyB, 'employee');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/salary-advances?employee_id='.$employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', "Employ\u{00E9} introuvable dans votre entreprise.");
    }

    public function test_manager_can_only_see_salary_advances_within_their_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeA = $this->createEmployee($companyA, 'employee');
        $employeeB = $this->createEmployee($companyB, 'employee');

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/salary-advances');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.employee_id', $employeeA->id);
    }

    public function test_manager_cannot_view_salary_advance_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson("/api/v1/salary-advances/{$advanceB->id}");

        $response->assertStatus(404);
    }

    public function test_manager_cannot_approve_salary_advance_of_another_tenant(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $advanceB = SalaryAdvance::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advanceB->id}/approve", [
                'repayment_months' => 3,
            ]);

        $response->assertStatus(404);
    }

    public function test_employee_cannot_cancel_salary_advance_of_another_employee(): void
    {
        $company = $this->createCompany('Company A');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $advance2 = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->deleteJson("/api/v1/salary-advances/{$advance2->id}");

        $response->assertStatus(403);
    }

    public function test_non_manager_cannot_approve_salary_advance(): void
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
            ->putJson("/api/v1/salary-advances/{$advance->id}/approve", [
                'repayment_months' => 3,
            ]);

        $response->assertStatus(403);
    }

    public function test_salary_advance_double_validation_happy_path(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 750,
            'reason' => 'Transport familial',
            'status' => 'pending',
            'validation_status' => 'pending',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/manager-approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.validation_status', 'manager_approved')
            ->assertJsonPath('data.manager_approved_by', $manager->id);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/mark-paid", [
                'payment_reference' => 'CASH-2026-001',
                'payment_note' => 'Remis en main propre',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.validation_status', 'payment_declared')
            ->assertJsonPath('data.payment_reference', 'CASH-2026-001')
            ->assertJsonPath('data.payment_declared_by', $manager->id);

        $this->actingAs($employee, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/confirm-received")
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'employee_confirmed');

        $this->assertDatabaseHas('salary_advances', [
            'id' => $advance->id,
            'validation_status' => 'employee_confirmed',
            'payment_reference' => 'CASH-2026-001',
        ]);

        // PA2-PAY-008: each transition notifies the relevant party.
        $this->assertDatabaseHas('notifications', [
            'employee_id' => $employee->id,
            'type' => 'payroll',
        ]);
        $employeeNotifications = Notification::query()->where('employee_id', $employee->id)->pluck('data')->all();
        $this->assertCount(2, $employeeNotifications); // manager_approved + payment_declared

        $this->assertDatabaseHas('notifications', [
            'employee_id' => $manager->id,
            'type' => 'payroll',
        ]);
    }

    public function test_salary_advance_double_validation_writes_audit_log_for_every_transition(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 750,
            'reason' => 'Transport familial',
            'status' => 'pending',
            'validation_status' => 'pending',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/manager-approve")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/mark-paid", [
                'payment_reference' => 'CASH-2026-001',
            ])
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/confirm-received")
            ->assertOk();

        // The initial forceCreate() above writes a `created` row, then each
        // of the 3 update() calls writes its own `updated` row (PA2-PAY-001
        // explicit "audit" acceptance criterion), all scoped to this company
        // and this SalaryAdvance, with the acting employee correctly
        // attributed to each transition.
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertSame(
            3,
            AuditLog::query()->where('auditable_id', $advance->id)->where('action', 'updated')->count(),
            'Expected one audit_logs "updated" row per double-validation transition.'
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'updated',
            'auditable_type' => SalaryAdvance::class,
            'auditable_id' => $advance->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'action' => 'updated',
            'auditable_type' => SalaryAdvance::class,
            'auditable_id' => $advance->id,
        ]);

        $newValues = AuditLog::query()
            ->where('auditable_id', $advance->id)
            ->where('user_id', $manager->id)
            ->where('new_values->validation_status', 'manager_approved')
            ->first();
        $this->assertNotNull($newValues, 'Expected an audit_logs row capturing the manager-approve transition new_values.');
    }

    public function test_manager_cannot_mark_salary_advance_paid_before_manager_approval(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'pending',
            'validation_status' => 'pending',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/mark-paid")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Advance must be manager-approved before declaring payment.');
    }

    public function test_employee_cannot_confirm_salary_advance_before_payment_declaration(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 500,
            'status' => 'approved',
            'validation_status' => 'manager_approved',
            'manager_approved_by' => $manager->id,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/confirm-received")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment must be declared before employee confirmation.');
    }

    public function test_employee_cannot_confirm_salary_advance_of_another_employee(): void
    {
        $company = $this->createCompany('Company A');
        $manager = $this->createEmployee($company, 'manager', 'principal');
        $employee1 = $this->createEmployee($company, 'employee');
        $employee2 = $this->createEmployee($company, 'employee');

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee2->id,
            'amount' => 500,
            'status' => 'active',
            'validation_status' => 'payment_declared',
            'manager_approved_by' => $manager->id,
            'payment_declared_by' => $manager->id,
        ]);

        $this->actingAs($employee1, 'sanctum')
            ->putJson("/api/v1/salary-advances/{$advance->id}/confirm-received")
            ->assertStatus(403);
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
}
