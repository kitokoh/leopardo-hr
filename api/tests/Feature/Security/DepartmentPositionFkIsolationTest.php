<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Position;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4398 — les FK références des endpoints HR (department.manager_id,
 * position.department_id) doivent être validées DANS la compagnie de
 * l'acteur. Avant : validation `integer|min:1` seule → un manager pouvait
 * référencer un employé / département d'une AUTRE compagnie (relations
 * cassées, données sales — famille #3065/#3428).
 */
class DepartmentPositionFkIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['name' => 'Fk Company A']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['name' => 'Fk Company B']);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        $this->companyA = $companyA;
        $this->companyB = $companyB;
        $this->managerA = $managerA;
        $this->employeeA = $employeeA;
        $this->employeeB = $employeeB;
    }

    public function test_department_manager_id_from_other_company_is_rejected(): void
    {
        Sanctum::actingAs($this->managerA);

        $this->postJson('/api/v1/departments', [
            'name' => 'Cross Tenant Dept',
            'manager_id' => $this->employeeB->id, // employé de la compagnie B
        ])->assertStatus(422)
            ->assertJsonValidationErrors('manager_id');

        $this->assertDatabaseMissing('departments', ['name' => 'Cross Tenant Dept']);
    }

    public function test_department_manager_id_from_same_company_is_accepted(): void
    {
        Sanctum::actingAs($this->managerA);

        $this->postJson('/api/v1/departments', [
            'name' => 'Valid Dept',
            'manager_id' => $this->employeeA->id,
        ])->assertStatus(201);
    }

    public function test_position_department_id_from_other_company_is_rejected(): void
    {
        // company_id n'est pas fillable sur Department (#4151) — assignation explicite.
        $foreignDepartment = new Department(['name' => 'Foreign Dept']);
        $foreignDepartment->company_id = $this->companyB->id;
        $foreignDepartment->save();

        Sanctum::actingAs($this->managerA);

        $this->postJson('/api/v1/positions', [
            'name' => 'Cross Tenant Position',
            'department_id' => $foreignDepartment->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('department_id');

        $this->assertDatabaseMissing('positions', ['name' => 'Cross Tenant Position']);
    }

    public function test_position_department_id_from_same_company_is_accepted(): void
    {
        $ownDepartment = new Department(['name' => 'Own Dept']);
        $ownDepartment->company_id = $this->companyA->id;
        $ownDepartment->save();

        Sanctum::actingAs($this->managerA);

        $this->postJson('/api/v1/positions', [
            'name' => 'Valid Position',
            'department_id' => $ownDepartment->id,
        ])->assertStatus(201);
    }
}
