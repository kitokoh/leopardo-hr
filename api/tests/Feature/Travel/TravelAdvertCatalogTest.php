<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-905 (#6108) — Référentiels des annonces payantes : types et
 * positions (CRUD tenant-scoped, unicité (company_id, code), isolation
 * cross-tenant, RBAC rôles opérationnels).
 *
 * #7420 : le contrat d'API est celui des modèles (`label`, pas `name`) — les
 * corps de requête/les assertions en `name` venaient d'une version antérieure
 * du schéma (migration 000017) et faisaient échouer le fichier avant la
 * moindre assertion. L'écriture des référentiels exige désormais un rôle
 * gestion (403), ce que la classe vérifiait sans que le contrôleur l'applique.
 */
class TravelAdvertCatalogTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
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

        $this->postJson('/api/v1/travel/advert-types', [
            'code' => 'image_banner',
            'label' => 'Bannière image',
        ])->assertStatus(201);

        $type = TravelAdvertType::query()->where('code', 'image_banner')->firstOrFail();

        // Unicité (company_id, code).
        $this->postJson('/api/v1/travel/advert-types', [
            'code' => 'image_banner',
            'label' => 'Doublon',
        ])->assertStatus(422);

        // Mise à jour + suppression.
        $this->putJson("/api/v1/travel/advert-types/{$type->id}", [
            'code' => 'image_banner',
            'label' => 'Bannière premium',
        ])->assertOk();

        $type->refresh();
        self::assertSame('Bannière premium', $type->label);

        $this->deleteJson("/api/v1/travel/advert-types/{$type->id}")->assertStatus(204);
        $this->assertDatabaseMissing('travel_advert_types', ['id' => $type->id]);
    }

    public function test_advert_position_crud_and_unicity(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/advert-positions', [
            'code' => 'home_top',
            'label' => 'Accueil — haut',
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/advert-positions', [
            'code' => 'home_top',
            'label' => 'Doublon',
        ])->assertStatus(422);

        $position = TravelAdvertPosition::query()->where('code', 'home_top')->firstOrFail();
        self::assertSame('Accueil — haut', $position->label);
    }

    public function test_catalog_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        app(TenantManager::class)->withinTenant($companyA, fn () => TravelAdvertType::query()->create([
            'company_id' => $companyA->id,
            'code' => 'only_a',
            'label' => 'Réservé A',
        ]));

        // L'utilisateur B ne voit pas les types de A et ne peut pas les modifier.
        $this->principal($companyB);
        $this->getJson('/api/v1/travel/advert-types')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $typeA = app(TenantManager::class)->withinTenant($companyA, fn () => TravelAdvertType::query()->where('company_id', $companyA->id)->firstOrFail());
        $this->putJson("/api/v1/travel/advert-types/{$typeA->id}", [
            'code' => 'piratage',
            'label' => 'Piratage',
        ])->assertStatus(404);
    }

    public function test_catalog_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        // Employé sans rôle manager : lecture OK, écriture refusée (#7420).
        $this->principal($company, role: 'employee', managerRole: null);

        $this->getJson('/api/v1/travel/advert-types')->assertOk();

        $this->postJson('/api/v1/travel/advert-types', ['code' => 'image_banner', 'label' => 'Bannière'])
            ->assertStatus(403);

        $this->postJson('/api/v1/travel/advert-positions', ['code' => 'home_top', 'label' => 'Accueil'])
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
