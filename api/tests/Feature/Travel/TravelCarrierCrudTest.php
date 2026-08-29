<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-304 (#6034) — CRUD des compagnies de transport.
 *
 * Couvre le CRUD complet, la validation du type (enum `CarrierType`) et
 * l'isolation cross-tenant.
 */
class TravelCarrierCrudTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_principal_can_create_carrier(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/carriers', [
            'code' => 'CAR-001',
            'name' => 'Trans Cameroun',
            'type' => 'bus',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'CAR-001', 'type' => 'bus']);
    }

    public function test_invalid_type_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/carriers', [
            'code' => 'CAR-002',
            'name' => 'Unknown Corp',
            'type' => 'spaceship',
        ])->assertStatus(422);
    }

    public function test_show_carrier_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $carrierId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelCarrier::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/carriers/{$carrierId}")->assertStatus(404);
    }

    public function test_update_and_delete_carrier(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $carrierId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return TravelCarrier::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/travel/carriers/{$carrierId}", ['name' => 'Nouveau Nom'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Nouveau Nom']);

        $this->deleteJson("/api/v1/travel/carriers/{$carrierId}")->assertStatus(204);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/carriers')->assertStatus(401);
    }
}
