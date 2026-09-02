<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-303 (#6033) — CRUD des bureaux de vente.
 *
 * Couvre le CRUD complet, le RBAC et l'isolation cross-tenant (404, jamais
 * 403 sur la ressource elle-même).
 */
class TravelOfficeCrudTest extends TestCase
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

    public function test_principal_can_create_office(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $cityId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/offices', [
            'name' => 'Bureau Centre-ville',
            'city_id' => $cityId,
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Bureau Centre-ville']);
    }

    public function test_create_office_with_city_of_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignCityId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelCity::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->postJson('/api/v1/travel/offices', [
            'name' => 'Bureau',
            'city_id' => $foreignCityId,
        ])->assertStatus(422);
    }

    public function test_show_office_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $officeId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/offices/{$officeId}")->assertStatus(404);
    }

    public function test_update_and_delete_office(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $officeId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return TravelOffice::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/travel/offices/{$officeId}", ['name' => 'Nouveau Bureau'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Nouveau Bureau']);

        $this->deleteJson("/api/v1/travel/offices/{$officeId}")->assertStatus(204);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/offices')->assertStatus(401);
    }
}
