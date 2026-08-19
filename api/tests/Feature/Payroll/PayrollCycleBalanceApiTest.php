<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4500 — PayrollCycleController::employeeBalance doit retourner
 * exactement { "data": {...} } sans clés plates au niveau racine.
 *
 * Avant le correctif, la réponse était ['data' => $payload] + $payload,
 * ce qui aplatissait les clés au niveau racine — contrat incohérent avec
 * myBalance et tous les autres endpoints payroll.
 */
class PayrollCycleBalanceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    /** @test */
    public function test_manager_employee_balance_uses_standard_data_envelope(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/employees/{$this->employee->id}/balance")
            ->assertOk();

        // SC-001 : la réponse ne contient QUE la clé "data" au niveau racine.
        $topLevelKeys = array_keys($response->json());
        $this->assertSame(['data'], $topLevelKeys, 'La réponse doit être { "data": {...} } uniquement (pas de clés plates au niveau racine).');

        // La clé "data" doit contenir les champs du bilan paie.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('gross_due', $data);
        $this->assertArrayHasKey('paid', $data);
        // Le contrat (modèle mobile PayrollBalance + issue #4500) expose
        // `remaining` — pas `balance` (renommé lors de l'harmonisation).
        $this->assertArrayHasKey('remaining', $data);
        $this->assertArrayHasKey('pay_slip', $data);
    }

    /** @test */
    public function test_employee_cannot_access_another_employee_balance(): void
    {
        // Un employé simple ne peut pas consulter le solde d'un autre employé.
        /** @var Employee $anotherEmployee */
        $anotherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($anotherEmployee);

        $this->getJson("/api/v1/employees/{$this->employee->id}/balance")
            ->assertForbidden();
    }

    /** @test */
    public function test_employee_can_access_own_balance_via_employee_route(): void
    {
        // Un employé peut consulter son propre solde (employee_id = son id).
        Sanctum::actingAs($this->employee);

        $response = $this->getJson("/api/v1/employees/{$this->employee->id}/balance")
            ->assertOk();

        $this->assertArrayHasKey('data', $response->json());
    }

    /** @test */
    public function test_manager_cannot_access_employee_from_other_company(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/employees/{$foreignEmployee->id}/balance")
            ->assertNotFound();
    }
}
