<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1765 — Les champs numériques envoyés en chaîne (formulaires HTML,
 * TextField Flutter) ne doivent plus provoquer de TypeError → HTTP 500 à la
 * création d'employé. `StoreEmployeeRequest::prepareForValidation()` normalise
 * `salary_base`/`hourly_rate` (float) et `schedule_id` (int) avant le DTO typé.
 */
class EmployeeNumericCastTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
        $this->manager = $manager;
    }

    public function test_employee_creation_accepts_salary_as_numeric_string(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Ali',
            'last_name' => 'Said',
            'email' => 'ali.said@x.dz',
            'hire_date' => '2026-08-01',
            'role' => 'employee',
            'password' => 'secret1234',
            'send_invitation' => false,
            'salary_base' => '40000',
            'hourly_rate' => '1500.50',
        ]);

        // Avant le correctif : HTTP 500 (TypeError dans CreateEmployeeDTO).
        $response->assertCreated();

        $this->assertDatabaseHas('employees', [
            'email' => 'ali.said@x.dz',
            'salary_base' => 40000,
            'hourly_rate' => 1500.50,
        ]);
    }

    public function test_employee_creation_still_accepts_numeric_values(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Zohra',
            'last_name' => 'B',
            'email' => 'zohra.b@x.dz',
            'hire_date' => '2026-08-01',
            'role' => 'employee',
            'password' => 'secret1234',
            'send_invitation' => false,
            'salary_base' => 60000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employees', [
            'email' => 'zohra.b@x.dz',
            'salary_base' => 60000,
        ]);
    }
}
