<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #6546 — RGPD : les données sensibles portées par employees.extra_data
 * (national_id, tax_identifier, blood_group) ne doivent fuiter ni sur la
 * liste /employees, ni hors du cercle salarial sur le détail.
 */
class EmployeeExtraDataRgpdTest extends TestCase
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

    /** @return array<string, mixed> */
    private function sensitiveExtraData(): array
    {
        return [
            'national_id' => 'NID-998877',
            'tax_identifier' => 'TAX-123456',
            'blood_group' => 'O+',
            'custom_field' => 'valeur non sensible',
        ];
    }

    public function test_employee_list_does_not_expose_extra_data(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => $this->sensitiveExtraData(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.extra_data');
        $response->assertJsonMissing(['national_id' => 'NID-998877']);
        $response->assertJsonMissing(['blood_group' => 'O+']);
    }

    public function test_employee_detail_masks_sensitive_keys_for_plain_employee(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $viewer */
        $viewer = Employee::factory()->create(['company_id' => $company->id]);
        $target = Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => $this->sensitiveExtraData(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/employees/'.$target->id);

        $response->assertOk();
        $response->assertJsonPath('data.extra_data.custom_field', 'valeur non sensible');
        $response->assertJsonMissingPath('data.extra_data.national_id');
        $response->assertJsonMissingPath('data.extra_data.tax_identifier');
        $response->assertJsonMissingPath('data.extra_data.blood_group');
    }

    public function test_principal_sees_sensitive_extra_data(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $target = Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => $this->sensitiveExtraData(),
        ]);

        $response = $this->actingAs($principal, 'sanctum')
            ->getJson('/api/v1/employees/'.$target->id);

        $response->assertOk();
        $response->assertJsonPath('data.extra_data.national_id', 'NID-998877');
        $response->assertJsonPath('data.extra_data.tax_identifier', 'TAX-123456');
        $response->assertJsonPath('data.extra_data.blood_group', 'O+');
    }
}
