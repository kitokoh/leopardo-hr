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
 * TRAVEL-905/906 (#6108/#6109) — Référentiels annonces (types, positions)
 * et grille tarifaire : CRUD, unicité par tenant, devise tenant.
 */
class TravelAdvertReferenceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_advert_type_crud(): void
    {
        $this->actingManager();

        $this->postJson('/api/v1/travel/advert-types', [
            'code' => 'sponso',
            'label' => 'Sponsoring',
        ])->assertStatus(201);

        $type = TravelAdvertType::query()->where('code', 'sponso')->firstOrFail();

        $this->getJson('/api/v1/travel/advert-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'sponso']);

        $this->deleteJson("/api/v1/travel/advert-types/{$type->id}")->assertStatus(204);
        self::assertNull(TravelAdvertType::query()->find($type->id));
    }

    public function test_advert_type_code_unique_per_tenant(): void
    {
        $this->actingManager();

        $this->postJson('/api/v1/travel/advert-types', ['code' => 'dup', 'label' => 'Un'])->assertStatus(201);

        $this->postJson('/api/v1/travel/advert-types', ['code' => 'dup', 'label' => 'Deux'])
            ->assertStatus(422); // unicité (company_id, code) validée applicativement
    }

    public function test_advert_position_crud(): void
    {
        $this->actingManager();

        $this->postJson('/api/v1/travel/advert-positions', [
            'code' => 'home_top',
            'label' => 'Haut de page d\'accueil',
        ])->assertStatus(201);

        $this->getJson('/api/v1/travel/advert-positions')
            ->assertOk()
            ->assertJsonFragment(['code' => 'home_top']);
    }

    public function test_advert_price_requires_tenant_currency(): void
    {
        $this->actingManager();
        [$type, $position] = $this->tenants->withinTenant($this->company, function (): array {
            return [
                TravelAdvertType::factory()->create(),
                TravelAdvertPosition::factory()->create(),
            ];
        });

        // Devise incohérente avec le tenant (XAF) → 422.
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
            'currency' => 'USD',
        ])->assertStatus(422);

        // Devise cohérente → 201.
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
            'currency' => 'XAF',
        ])->assertStatus(201);
    }

    public function test_advert_price_rejects_zero_amounts(): void
    {
        $this->actingManager();
        [$type, $position] = $this->tenants->withinTenant($this->company, function (): array {
            return [
                TravelAdvertType::factory()->create(),
                TravelAdvertPosition::factory()->create(),
            ];
        });

        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 0,
            'price_per_character_minor' => 100,
            'currency' => 'XAF',
        ])->assertStatus(422);
    }

    public function test_cross_tenant_reference_is_rejected(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignType = $this->tenants->withinTenant($companyB, fn (): TravelAdvertType => TravelAdvertType::factory()->create());

        $this->actingManager();

        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $foreignType->id,
            'advert_position_id' => 1,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
            'currency' => 'XAF',
        ])->assertStatus(422);
    }
}
