<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-001, #7943) — enregistrement de la verticale :
 * gate feature flag `hospitality` (403 HOSPITALITY_SOLUTION_INACTIVE si la
 * solution est inactive, fail-closed par défaut), sonde d'état du squelette,
 * et présence dans Company::KNOWN_MODULES (reconstruction par l'admin
 * plateforme via PATCH /platform/companies/{id}/features — leçons
 * #7220/#7235).
 */
class HospitalityModuleStatusTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyEnabled;

    private Company $companyDisabled;

    private Employee $employeeEnabled;

    private Employee $employeeDisabled;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyEnabled */
        $companyEnabled = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyEnabled->setFeature('hospitality', true);
        $companyEnabled->save();
        $this->companyEnabled = $companyEnabled;

        /** @var Company $companyDisabled */
        $companyDisabled = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyDisabled = $companyDisabled;

        $this->employeeEnabled = $this->employee($this->companyEnabled);
        $this->employeeDisabled = $this->employee($this->companyDisabled);
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    public function test_hospitality_is_a_known_module(): void
    {
        // Sans cette entrée, l'admin plateforme ne pourrait jamais activer
        // ni exposer la verticale (leçons #7220/#7235).
        $this->assertContains('hospitality', Company::KNOWN_MODULES);
    }

    public function test_hospitality_flag_is_registered_fail_closed(): void
    {
        $flags = config('feature-flags.flags');

        $this->assertArrayHasKey('hospitality', $flags);
        $this->assertSame('solution', $flags['hospitality']['scope']);
        $this->assertFalse($flags['hospitality']['default']);
        $this->assertTrue($flags['hospitality']['killable']);
    }

    public function test_manifest_is_registered_in_solution_catalogue(): void
    {
        $catalogue = app(\App\Core\Solutions\SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('hospitality'));
        $this->assertSame('HospitalityManager', $catalogue->resolve('hospitality')->name());
    }

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/hospitality/status')->assertStatus(401);
    }

    public function test_status_returns_403_when_solution_disabled(): void
    {
        Sanctum::actingAs($this->employeeDisabled);

        $this->getJson('/api/v1/hospitality/status')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HOSPITALITY_SOLUTION_INACTIVE');
    }

    public function test_status_returns_module_state_when_solution_enabled(): void
    {
        Sanctum::actingAs($this->employeeEnabled);

        $this->getJson('/api/v1/hospitality/status')
            ->assertStatus(200)
            ->assertJsonPath('data.module', 'hospitality')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.stage', 'HOSP-004')
            ->assertJsonPath('data.capabilities.properties', true)
            ->assertJsonPath('data.capabilities.reservations', true);
    }
}
