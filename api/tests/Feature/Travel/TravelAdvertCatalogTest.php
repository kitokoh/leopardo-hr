<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-905 (#6108) — Référentiels des annonces payantes : types et
 * positions (CRUD tenant-scoped, unicité (company_id, code), isolation
 * cross-tenant, RBAC rôles opérationnels).
 */
class TravelAdvertCatalogTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company, string $role = 'manager', string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_advert_type_crud_and_unicity(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $created = $this->postJson('/api/v1/travel/advert-types', [
            'code' => 'image_banner',
            'name' => 'Bannière image',
        ])->assertStatus(201)
            ->assertJsonPath('data.code', 'image_banner');

        $typeId = (int) $created->json('data.id');

        // Unicité (company_id, code).
        $this->postJson('/api/v1/travel/advert-types', [
            'code' => 'image_banner',
            'name' => 'Doublon',
        ])->assertStatus(422);

        // Mise à jour + suppression.
        $this->putJson("/api/v1/travel/advert-types/{$typeId}", ['name' => 'Bannière premium'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bannière premium');

        $this->deleteJson("/api/v1/travel/advert-types/{$typeId}")->assertStatus(204);
        $this->assertDatabaseMissing('travel_advert_types', ['id' => $typeId]);
    }

    public function test_advert_position_crud_and_unicity(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/advert-positions', [
            'code' => 'home_top',
            'name' => 'Accueil — haut',
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/advert-positions', [
            'code' => 'home_top',
            'name' => 'Doublon',
        ])->assertStatus(422);
    }

    public function test_catalog_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        TravelAdvertType::query()->create([
            'company_id' => $companyA->id,
            'code' => 'only_a',
            'name' => 'Réservé A',
        ]);

        // L'utilisateur B ne voit pas les types de A et ne peut pas les modifier.
        $this->principal($companyB);
        $this->getJson('/api/v1/travel/advert-types')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $typeA = TravelAdvertType::query()->where('company_id', $companyA->id)->firstOrFail();
        $this->putJson("/api/v1/travel/advert-types/{$typeA->id}", ['name' => 'piratage'])
            ->assertStatus(404);
    }

    public function test_catalog_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        // Employé sans rôle manager : lecture OK, écriture refusée.
        $this->principal($company, role: 'employee', managerRole: null);

        $this->getJson('/api/v1/travel/advert-types')->assertOk();
        $this->postJson('/api/v1/travel/advert-types', ['code' => 'x', 'name' => 'X'])
            ->assertStatus(403);
    }

    public function test_catalog_requires_feature_flag(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->principal($company);

        $this->getJson('/api/v1/travel/advert-types')->assertStatus(403);
    }
}
