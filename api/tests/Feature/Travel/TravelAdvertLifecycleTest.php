<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-907 (#6110) — Cycle de vie des annonces payantes : soumission avec
 * prix calculé serveur, paiement (contrat travel_payments), validation par
 * travel.manage, publication — une annonce n'est visible qu'une fois payée
 * ET validée. Idempotence du paiement, refus RBAC, isolation cross-tenant.
 */
class TravelAdvertLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private function login(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
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

    private function makeCatalog(Company $company, int $image = 50000, int $perChar = 25): array
    {
        $type = TravelAdvertType::query()->create([
            'company_id' => $company->id,
            'code' => 'image_banner',
            'name' => 'Bannière',
        ]);
        $position = TravelAdvertPosition::query()->create([
            'company_id' => $company->id,
            'code' => 'home_top',
            'name' => 'Accueil haut',
        ]);
        TravelAdvertPrice::query()->create([
            'company_id' => $company->id,
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'price_image_minor' => $image,
            'price_character_minor' => $perChar,
            'currency' => 'XAF',
        ]);

        return [$type, $position];
    }

    private function submit(Company $company, array $overrides = []): int
    {
        [$type, $position] = $this->makeCatalog($company);

        $response = $this->postJson('/api/v1/travel/adverts', array_merge([
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Promo Douala',
            'body_redacted' => 'Voyage promo',
        ], $overrides));

        return (int) $response->json('data.id');
    }

    public function test_submission_prices_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advertId = $this->submit($company);

        $this->getJson("/api/v1/travel/adverts/{$advertId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.price_image_minor', 50000)
            ->assertJsonPath('data.price_character_minor', 25)
            ->assertJsonPath('data.character_count', 13)
            ->assertJsonPath('data.total_minor', 50000 + (13 * 25));
    }

    public function test_submission_rejects_unknown_tenant_references_and_missing_tariff(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $foreignType = TravelAdvertType::query()->create([
            'company_id' => $other->id,
            'code' => 'foreign',
            'name' => 'Étranger',
        ]);
        $position = TravelAdvertPosition::query()->create([
            'company_id' => $company->id,
            'code' => 'home_top',
            'name' => 'Accueil',
        ]);

        // Type d'un autre tenant → 422.
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $foreignType->id,
            'advert_position_id' => $position->id,
            'title' => 'X',
            'body_redacted' => 'Y',
        ])->assertStatus(422);

        // Type du tenant mais AUCUN tarif configuré → 422 (prix serveur).
        $localType = TravelAdvertType::query()->create([
            'company_id' => $company->id,
            'code' => 'no_price',
            'name' => 'Sans tarif',
        ]);
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $localType->id,
            'advert_position_id' => $position->id,
            'title' => 'X',
            'body_redacted' => 'Y',
        ])->assertStatus(422);
    }

    public function test_pay_validate_publish_flow(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advertId = $this->submit($company);

        // Validation avant paiement → 422 (payée ET validée obligatoire).
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate", ['approved' => true])
            ->assertStatus(422);

        // Paiement cash : ligne travel_payments + statut paid.
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_at', fn ($v) => $v !== null);

        $this->assertSame(1, TravelPayment::query()->where('advert_id', $advertId)->count());
        $payment = TravelPayment::query()->where('advert_id', $advertId)->firstOrFail();
        $this->assertSame((int) TravelAdvert::query()->findOrFail($advertId)->payment_id, (int) $payment->id);

        // Paiement idempotent : rejeu → toujours une seule ligne.
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])->assertOk();
        $this->assertSame(1, TravelPayment::query()->where('advert_id', $advertId)->count());

        // Validation par un agent (non travel.manage) → 403.
        $this->login($company, role: 'manager', managerRole: 'agent');
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate", ['approved' => true])
            ->assertStatus(403);

        // Validation par principal → published avec expiration.
        $this->login($company, role: 'manager', managerRole: 'principal');
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate", ['approved' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.expires_at', fn ($v) => $v !== null);

        $advert = TravelAdvert::query()->findOrFail($advertId);
        $this->assertNotNull($advert->validated_at);
        $this->assertNotNull($advert->published_at);
        $this->assertGreaterThan(now()->addDays(29), $advert->expires_at);
    }

    public function test_rejected_advert_stays_invisible(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advertId = $this->submit($company);
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])->assertOk();

        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate", ['approved' => false, 'note' => 'contenu inapproprié'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.moderation_note', 'contenu inapproprié');

        // La liste filtrée published ne contient pas l'annonce rejetée.
        $this->getJson('/api/v1/travel/adverts?status=published')->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_access_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->login($companyA);
        $advertId = $this->submit($companyA);

        $this->login($companyB);
        $this->getJson("/api/v1/travel/adverts/{$advertId}")->assertStatus(404);
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])->assertStatus(404);
    }

    public function test_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company, role: 'employee', managerRole: null);

        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => 1,
            'advert_position_id' => 1,
            'title' => 'X',
            'body_redacted' => 'Y',
        ])->assertStatus(403);
    }
}
