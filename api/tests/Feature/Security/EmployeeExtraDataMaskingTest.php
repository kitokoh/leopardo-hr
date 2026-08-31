<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6546 (audit surface API M3) — extra_data exposait en clair
 * national_id / tax_identifier / blood_group (pièce d'identité + données
 * de santé, RGPD sensibles) sur la LISTE /employees et le détail.
 *
 * Correctif : whitelist de clés non sensibles (department, job_title,
 * work_location, education_level) + extra_data retiré de la projection
 * liste.
 */
class EmployeeExtraDataMaskingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function employeeWithSensitiveExtraData(): Employee
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => [
                'department' => 'Comptabilité',
                'job_title' => 'Comptable senior',
                'work_location' => 'Alger',
                'education_level' => 'Master',
                'national_id' => '199012345678901',
                'tax_identifier' => 'TIN-987654321',
                'blood_group' => 'O+',
            ],
        ]);

        return $employee;
    }

    public function test_employee_list_does_not_expose_sensitive_extra_data(): void
    {
        $employee = $this->employeeWithSensitiveExtraData();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $employee->company_id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        /** @var array<string, mixed> $payload */
        $payload = $response->json('data.0.extra_data');

        // Clés non sensibles conservées.
        $this->assertSame('Comptabilité', $payload['department']);
        $this->assertSame('Comptable senior', $payload['job_title']);
        $this->assertSame('Alger', $payload['work_location']);
        $this->assertSame('Master', $payload['education_level']);

        // Données sensibles JAMAIS exposées (identité + santé).
        $this->assertArrayNotHasKey('national_id', $payload);
        $this->assertArrayNotHasKey('tax_identifier', $payload);
        $this->assertArrayNotHasKey('blood_group', $payload);
    }

    public function test_employee_detail_does_not_expose_sensitive_extra_data(): void
    {
        $employee = $this->employeeWithSensitiveExtraData();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $employee->company_id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees/'.$employee->id);

        $response->assertOk();
        /** @var array<string, mixed> $payload */
        $payload = $response->json('data.extra_data');

        $this->assertSame('Comptabilité', $payload['department']);
        $this->assertArrayNotHasKey('national_id', $payload);
        $this->assertArrayNotHasKey('tax_identifier', $payload);
        $this->assertArrayNotHasKey('blood_group', $payload);
    }

    public function test_employee_self_view_is_masked_too(): void
    {
        $employee = $this->employeeWithSensitiveExtraData();

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/employees/'.$employee->id);

        $response->assertOk();
        /** @var array<string, mixed> $payload */
        $payload = $response->json('data.extra_data');
        $this->assertArrayNotHasKey('national_id', $payload);
        $this->assertArrayNotHasKey('blood_group', $payload);
    }
}
