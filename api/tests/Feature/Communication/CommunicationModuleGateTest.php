<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-29 COMMUNICATION (R0, #7685) — enregistrement du module :
 * gate feature flag `communication` (403 si module inactif, fail-closed par
 * défaut), endpoint d'état du squelette, activation/désactivation par tenant
 * et présence dans Company::KNOWN_MODULES (reconstruction par l'admin
 * plateforme via PATCH /platform/companies/{id}/features).
 */
class CommunicationModuleGateTest extends TestCase
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
        $companyEnabled->setFeature(CommunicationFeatures::COMMUNICATION, true);
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

    public function test_communication_is_a_known_module(): void
    {
        // L'admin plateforme reconstruit `features` à partir de KNOWN_MODULES
        // (PATCH /platform/companies/{id}/features) : sans cette entrée, le
        // module ne serait ni activable ni exposé (leçons #7220/#7235).
        $this->assertContains(CommunicationFeatures::COMMUNICATION, Company::KNOWN_MODULES);
    }

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/communication/status')->assertStatus(401);
    }

    public function test_status_returns_403_when_module_disabled(): void
    {
        Sanctum::actingAs($this->employeeDisabled);

        $this->getJson('/api/v1/communication/status')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_status_returns_module_state_when_module_enabled(): void
    {
        Sanctum::actingAs($this->employeeEnabled);

        $this->getJson('/api/v1/communication/status')
            ->assertStatus(200)
            ->assertJsonPath('data.module', 'communication')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.stage', 'R4')
            ->assertJsonPath('data.capabilities.integrations', true)
            ->assertJsonPath('data.capabilities.sync', true)
            ->assertJsonPath('data.capabilities.classification', true)
            ->assertJsonPath('data.capabilities.follow_ups', true)
            ->assertJsonPath('data.capabilities.replies', false);
    }

    public function test_gate_is_evaluated_per_tenant(): void
    {
        // Le flag du tenant A n'ouvre rien au tenant B (isolation tenant).
        Sanctum::actingAs($this->employeeEnabled);
        $this->getJson('/api/v1/communication/status')->assertStatus(200);

        Sanctum::actingAs($this->employeeDisabled);
        $this->getJson('/api/v1/communication/status')->assertStatus(403);
    }

    public function test_module_can_be_deactivated_per_tenant(): void
    {
        // Kill switch opérationnel : désactiver le flag → 403 immédiat.
        $this->companyEnabled->setFeature(CommunicationFeatures::COMMUNICATION, false);
        $this->companyEnabled->save();

        // L'acteur est créé APRÈS la coupure (pattern DeliveryApiTest) : la
        // relation `company` d'un Employee existant garde l'instantané du flag
        // chargé à sa création, et c'est elle que lit TenantMiddleware —
        // vérifié contre PostgreSQL réel : avec $this->employeeEnabled, la
        // requête répondait 200 alors que public.companies portait bien false.
        Sanctum::actingAs($this->employee($this->companyEnabled));

        $this->getJson('/api/v1/communication/status')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }
}
