<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6671 — GET /contracts/templates répondait 500 INTERNAL_ERROR en
 * prod quel que soit le pays. Vérifié sur main : le happy path (200) et le
 * 422 (pays non supporté) sont corrects ; ces tests de non-régression
 * verrouillent le contrat (la cause prod restante = retard de déploiement
 * #6670 / déploiement partiel — le bundle est désormais tolérant aux
 * clauses manquantes).
 */
class ContractTemplatesEndpointTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    private Employee $rh;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);
        $this->rh = $rh;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    public function test_templates_returns_200_for_supported_countries(): void
    {
        Sanctum::actingAs($this->manager);

        foreach (['DZ', 'MA', 'SN', 'TN'] as $country) {
            $response = $this->getJson("/api/v1/contracts/templates?country={$country}&contract_type=cdi")
                ->assertOk();

            $data = $response->json('data');
            $this->assertSame($country, $data['country']);
            $this->assertSame('cdi', $data['contract_type']);
            $this->assertNotEmpty($data['clauses'], "clauses vides pour {$country}");
            $this->assertNotEmpty($data['legal_references']['code'], "référence légale absente pour {$country}");
        }
    }

    public function test_templates_accepts_all_contract_types_and_rh_role(): void
    {
        Sanctum::actingAs($this->rh);

        foreach (['cdi', 'cdd', 'stage', 'freelance', 'interim'] as $type) {
            $this->getJson("/api/v1/contracts/templates?country=DZ&contract_type={$type}")
                ->assertOk()
                ->assertJsonPath('data.contract_type', $type);
        }
    }

    public function test_templates_returns_422_for_unsupported_country(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/contracts/templates?country=XX&contract_type=cdi')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CONTRACT_TEMPLATE_NOT_FOUND');
    }

    public function test_templates_returns_403_for_employee_role(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/contracts/templates?country=DZ&contract_type=cdi')
            ->assertForbidden();
    }
}
