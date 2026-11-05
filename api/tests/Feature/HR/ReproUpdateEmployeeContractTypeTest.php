<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Reproduction du bug UC-07 : PUT /api/v1/employees/{id} avec un champ
 * validé par UpdateEmployeeRequest mais absent du constructeur de
 * UpdateEmployeeDTO (contract_type, matricule) -> 500
 * "Unknown named parameter $contract_type".
 */
class ReproUpdateEmployeeContractTypeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_update_employee_with_contract_type(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'contract_type' => 'CDD',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'contract_type' => 'CDD',
        ]);
    }

    public function test_update_employee_with_matricule(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'matricule' => 'MAT-9001',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'matricule' => 'MAT-9001',
        ]);
    }
}
