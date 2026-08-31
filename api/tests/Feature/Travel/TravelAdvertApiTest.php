<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-905..908 (#6108..#6111) — Annonces : référentiels, tarifs, cycle
 * de vie.
 *
 * Couvre : référentiels opérationnels, tarifs en minor units cohérents avec
 * la devise tenant, calcul du prix SERVEUR, visibilité conditionnelle
 * (payée ET validée — critère d'acceptation), expiration par job et
 * renouvellement.
 */
class TravelAdvertApiTest extends TestCase
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

    /**
     * Référentiels + grille tarifaire du tenant.
     *
     * @return array{type: int, position: int}
     */
    private function referentials(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $type = TravelAdvertType::query()->create([
                'company_id' => $company->id,
                'code' => 'BANNER',
                'name' => 'Bannière',
            ]);
            $position = TravelAdvertPosition::query()->create([
                'company_id' => $company->id,
                'code' => 'HOME_TOP',
                'name' => 'Accueil haut',
            ]);
            TravelAdvertPrice::query()->create([
                'company_id' => $company->id,
                'type_id' => $type->id,
                'position_id' => $position->id,
                'price_per_image_minor' => 5000,
                'price_per_character_minor' => 10,
                'currency' => 'XAF',
            ]);

            return ['type' => $type->id, 'position' => $position->id];
        });
    }

    public function test_advert_is_visible_only_when_paid_and_validated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['type' => $typeId, 'position' => $positionId] = $this->referentials($company);

        // Soumission : prix calculé SERVEUR (5000 + 10 × 60 caractères = 5600).
        $advert = $this->postJson('/api/v1/travel/community/adverts', [
            'type_id' => $typeId,
            'position_id' => $positionId,
            'title' => 'Promotion week-end',
            'body' => str_repeat('a', 60),
            'image_path' => 'assets/ads/promo.png',
        ])->assertStatus(201)->json('data');

        $this->assertSame(5600, $advert['price_minor']);
        $this->assertSame('submitted', $advert['status']);

        // Non visible avant paiement + validation.
        $this->getJson('/api/v1/travel/community/adverts')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Payée mais pas validée → toujours invisible.
        $this->postJson("/api/v1/travel/community/adverts/{$advert['id']}/pay")->assertOk();
        $this->getJson('/api/v1/travel/community/adverts')->assertJsonCount(0, 'data');

        // Validée → visible.
        $this->postJson("/api/v1/travel/community/adverts/{$advert['id']}/validate", ['valid_days' => 30])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/travel/community/adverts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Promotion week-end');
    }

    public function test_prices_are_in_minor_units_and_tenant_currency(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/community/advert-types', ['code' => 'T1', 'name' => 'Type 1'])
            ->assertStatus(201);
        $this->postJson('/api/v1/travel/community/advert-positions', ['code' => 'P1', 'name' => 'Pos 1'])
            ->assertStatus(201);

        $typeId = TravelAdvertType::query()->value('id');
        $positionId = TravelAdvertPosition::query()->value('id');

        $this->postJson('/api/v1/travel/community/advert-prices', [
            'type_id' => $typeId,
            'position_id' => $positionId,
            'price_per_image_minor' => 2500,
            'price_per_character_minor' => 5,
        ])->assertStatus(201)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.price_per_image_minor', 2500);
    }

    public function test_advert_expires_and_can_be_renewed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['type' => $typeId, 'position' => $positionId] = $this->referentials($company);

        $advert = $this->postJson('/api/v1/travel/community/adverts', [
            'type_id' => $typeId,
            'position_id' => $positionId,
            'title' => 'Annonce courte',
            'body' => str_repeat('b', 30),
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/travel/community/adverts/{$advert['id']}/pay")->assertOk();

        // Valide 1 jour, puis on force l'expiration par le passé.
        $this->postJson("/api/v1/travel/community/adverts/{$advert['id']}/validate", ['valid_days' => 1])
            ->assertOk();

        app(TenantManager::class)->withinTenant($company, function () use ($advert): void {
            TravelAdvert::query()->whereKey($advert['id'])->update(['valid_until' => now()->subDay()]);
        });

        Artisan::call('leopardo:travel:expire-adverts');

        $this->assertSame('expired', TravelAdvert::query()->findOrFail($advert['id'])->status);
        $this->getJson('/api/v1/travel/community/adverts')->assertJsonCount(0, 'data');

        // Renouvellement (nouveau paiement) → republiée.
        $this->postJson("/api/v1/travel/community/adverts/{$advert['id']}/renew", ['valid_days' => 7])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }
}
