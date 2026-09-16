<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces : minor units, devise
 * du tenant, références du même tenant, unicité (type, position), bornes
 * strictement positives.
 *
 * #7420 : contrat rétabli (`price_per_image_minor` / `price_per_character_minor`,
 * pas `price_image_minor`) et devise rendue optionnelle côté API — omise, elle
 * prend celle du tenant, fournie, elle doit être cohérente avec elle.
 */
class TravelAdvertPriceTest extends TestCase
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

    private function makeType(Company $company, string $code = 'image_banner'): TravelAdvertType
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelAdvertType => TravelAdvertType::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'label' => 'Bannière',
        ]));
    }

    private function makePosition(Company $company, string $code = 'home_top'): TravelAdvertPosition
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelAdvertPosition => TravelAdvertPosition::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'label' => 'Accueil haut',
        ]));
    }

    public function test_price_crud_in_minor_units(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);
        $type = $this->makeType($company);
        $position = $this->makePosition($company);

        // Devise omise → celle du tenant (XAF).
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 50000,
            'price_per_character_minor' => 25,
        ])->assertStatus(201);

        $price = TravelAdvertPrice::query()->where('advert_type_id', $type->id)->firstOrFail();
        self::assertSame(50000, $price->price_per_image_minor);
        self::assertSame(25, $price->price_per_character_minor);
        self::assertSame('XAF', $price->currency);

        // Doublon (type, position) → 422 (et non 500 : contrainte unique du
        // schéma validée applicativement).
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 1000,
            'price_per_character_minor' => 10,
        ])->assertStatus(422);

        // Mise à jour des montants.
        $this->putJson("/api/v1/travel/advert-prices/{$price->id}", [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 50000,
            'price_per_character_minor' => 30,
        ])->assertOk();

        $price->refresh();
        self::assertSame(30, $price->price_per_character_minor);

        // Montants nuls ou négatifs refusés.
        $this->putJson("/api/v1/travel/advert-prices/{$price->id}", [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => -1,
            'price_per_character_minor' => 30,
        ])->assertStatus(422);

        $this->deleteJson("/api/v1/travel/advert-prices/{$price->id}")->assertStatus(204);
    }

    public function test_price_requires_same_tenant_references_and_currency(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->activateTravel($company);
        $this->principal($company);

        $foreignType = $this->makeType($other, 'foreign');
        $position = $this->makePosition($company);

        // Type d'un autre tenant → 422.
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $foreignType->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
        ])->assertStatus(422);

        // Devise différente du tenant → 422.
        $type = $this->makeType($company, 'local');
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
            'currency' => 'DZD',
        ])->assertStatus(422);
    }

    public function test_price_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $type = $this->makeType($company);
        $position = $this->makePosition($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
        ])->assertStatus(403);
    }

    public function test_price_list_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $typeA = $this->makeType($companyA);
        $posA = $this->makePosition($companyA);

        $this->principal($companyA);
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $typeA->id,
            'advert_position_id' => $posA->id,
            'price_per_image_minor' => 100,
            'price_per_character_minor' => 10,
        ])->assertStatus(201);

        // Le tenant B ne voit rien.
        $this->principal($companyB);
        $this->getJson('/api/v1/travel/advert-prices')->assertOk()->assertJsonCount(0, 'data');
    }
}
