<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3055 — GET /employees/{employeeId}/leave-balances doit être gardé :
 * un employé ne lit que ses propres soldes, les managers lisent ceux de
 * leur entreprise. (La copie Absence n'avait aucune garde → fuite.) 
 */
class LeaveBalancesRoleGuardTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function ($table): void {
                $table->id();
                $table->string('company_id');
                $table->foreignId('employee_id');
                $table->foreignId('absence_type_id');
                $table->integer('year');
                $table->decimal('balance', 8, 2)->default(0);
                $table->decimal('used', 8, 2)->default(0);
                $table->decimal('pending', 8, 2)->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('absence_types')) {
            Schema::create('absence_types', function ($table): void {
                $table->id();
                $table->string('company_id');
                $table->string('code');
                $table->string('name');
                $table->boolean('requires_approval')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('absence_types');
        parent::tearDown();
    }

    private function makeContext(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        /** @var AbsenceType $type */
        $type = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'ANNUAL',
            'name' => 'Congés annuels',
            'requires_approval' => true,
        ]);

        LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'year' => (int) now()->format('Y'),
            'balance' => 15,
            'used' => 2,
            'pending' => 0,
        ]);

        return [$company, $manager, $employee];
    }

    public function test_employee_can_read_own_balances_but_not_colleague_s(): void
    {
        [, $manager, $employee] = $this->makeContext();
        /** @var Employee $colleague */
        $colleague = Employee::factory()->create(['company_id' => $employee->company_id]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$employee->id}/leave-balances")->assertOk();
        $this->getJson("/api/v1/employees/{$colleague->id}/leave-balances")->assertForbidden();
        $this->getJson("/api/v1/employees/{$manager->id}/leave-balances")->assertForbidden();
    }

    public function test_manager_can_read_any_employee_balances_of_own_company(): void
    {
        [, $manager, $employee] = $this->makeContext();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/leave-balances")->assertOk();
    }

    public function test_employee_of_other_company_cannot_read(): void
    {
        [$company, $manager, $employee] = $this->makeContext();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherEmployee);

        // 403 (pas manager, pas soi-même) — jamais de fuite cross-tenant.
        $this->getJson("/api/v1/employees/{$employee->id}/leave-balances")->assertForbidden();
    }
}
