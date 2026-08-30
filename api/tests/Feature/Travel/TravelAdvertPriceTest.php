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
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces : minor units, devise
 * du tenant, références du même tenant, unicité (type, position, devise),
 * bornes non négatives.
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
        return TravelAdvertType::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Bannière',
        ]);
    }

    private function makePosition(Company $company, string $code = 'home_top'): TravelAdvertPosition
    {
        return TravelAdvertPosition::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Accueil haut',
        ]);
    }

    public function test_price_crud_in_minor_units(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);
        $type = $this->makeType($company);
        $position = $this->makePosition($company);

        $created = $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_image_minor' => 50000,
            'price_character_minor' => 25,
        ])->assertStatus(201)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.price_image_minor', 50000);

        $priceId = (int) $created->json('data.id');

        // Doublon (type, position, devise) → 422.
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
        ])->assertStatus(422);

        // Mise à jour des montants.
        $this->putJson("/api/v1/travel/advert-prices/{$priceId}", ['price_character_minor' => 30])
            ->assertOk()
            ->assertJsonPath('data.price_character_minor', 30);

        // Montants négatifs refusés.
        $this->putJson("/api/v1/travel/advert-prices/{$priceId}", ['price_image_minor' => -1])
            ->assertStatus(422);

        $this->deleteJson("/api/v1/travel/advert-prices/{$priceId}")->assertStatus(204);
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
        ])->assertStatus(422);

        // Devise différente du tenant → 422.
        $type = $this->makeType($company, 'local');
        $this->postJson('/api/v1/travel/advert-prices', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'currency' => 'DZD',
        ])->assertStatus(422);
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
            'price_image_minor' => 100,
        ])->assertStatus(201);

        // Le tenant B ne voit rien.
        $this->principal($companyB);
        $this->getJson('/api/v1/travel/advert-prices')->assertOk()->assertJsonCount(0, 'data');
    }
}
