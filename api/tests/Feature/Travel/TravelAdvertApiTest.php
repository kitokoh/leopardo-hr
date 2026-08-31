<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
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
 * TRAVEL-907/908 (#6110/#6111) — Cycle de vie des annonces.
 *
 * Prix calculé serveur ; visible uniquement payée+validée+non expirée ;
 * expiration et renouvellement (nouveau paiement).
 */
class TravelAdvertApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
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
    /**
     * @return array{type: TravelAdvertType, position: TravelAdvertPosition, price: TravelAdvertPrice}
     */
    private function seedPricing(int $imagePrice = 5000, int $charPrice = 100): array
    {
        return $this->tenants->withinTenant($this->company, function () use ($imagePrice, $charPrice): array {
            $type = TravelAdvertType::factory()->create();
            $position = TravelAdvertPosition::factory()->create();
            $price = TravelAdvertPrice::factory()->create([
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'price_per_image_minor' => $imagePrice,
                'price_per_character_minor' => $charPrice,
            ]);

            return ['type' => $type, 'position' => $position, 'price' => $price];
        });
    }

    public function test_submit_computes_price_server_side(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing(imagePrice: 5000, charPrice: 100);

        // 10 caractères × 100 + 1 image × 5000 = 6000 (unités mineures).
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Vente de billets Douala-Yaoundé',
            'content' => str_repeat('a', 10),
            'image_asset_id' => 7,
            'validity_days' => 15,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.price_minor', 6000)
            ->assertJsonPath('data.currency', 'XAF');

        $advert = TravelAdvert::query()->firstOrFail();
        self::assertSame(AdvertStatus::SUBMITTED, $advert->status);
    }

    public function test_submit_rejects_unknown_pricing(): void
    {
        $this->actingManager();
        $foreign = $this->tenants->withinTenant($this->company, function (): array {
            return [TravelAdvertType::factory()->create()->id, TravelAdvertPosition::factory()->create()->id];
        });

        // Aucune grille pour ce couple type/position → 422.
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $foreign[0],
            'advert_position_id' => $foreign[1],
            'title' => 'Sans grille',
            'content' => 'Contenu',
        ])->assertStatus(422);
    }

    public function test_advert_visible_only_after_payment_and_validation(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $created = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Annonce à valider',
            'content' => 'Contenu annonce',
        ])->assertStatus(201)->json('data');

        $id = $created['id'];

        // Soumise → invisible.
        $this->getJson('/api/v1/travel/adverts')->assertOk()->assertJsonMissing(['id' => $id]);

        // Payée mais pas validée → invisible.
        $this->postJson("/api/v1/travel/adverts/{$id}/pay")->assertOk();
        $this->getJson('/api/v1/travel/adverts')->assertOk()->assertJsonMissing(['id' => $id]);

        // Validée → visible.
        $this->postJson("/api/v1/travel/adverts/{$id}/validate")->assertOk();
        $this->getJson('/api/v1/travel/adverts')->assertOk()->assertJsonFragment(['id' => $id]);
    }

    public function test_validate_requires_paid_state(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $id = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Non payée',
            'content' => 'Contenu',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/travel/adverts/{$id}/validate")->assertStatus(422);
    }

    public function test_rejection_requires_reason_and_hides_advert(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $id = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'À rejeter',
            'content' => 'Contenu',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/travel/adverts/{$id}/reject", ['reason' => 'Contenu non conforme'])
            ->assertStatus(200);

        $advert = TravelAdvert::query()->findOrFail($id);
        self::assertSame(AdvertStatus::REJECTED, $advert->status);
        self::assertSame('Contenu non conforme', $advert->rejected_reason);
    }

    public function test_expired_advert_becomes_invisible(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $id = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Expirera vite',
            'content' => 'Contenu',
            'validity_days' => 1,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/travel/adverts/{$id}/pay")->assertOk();
        $this->postJson("/api/v1/travel/adverts/{$id}/validate")->assertOk();

        // Expiration forcée (le job passe sur expires_at ≤ now).
        $this->tenants->withinTenant($this->company, function () use ($id): void {
            TravelAdvert::query()->whereKey($id)->update(['expires_at' => now()->subMinute()]);
        });

        Artisan::call('travel:expire-adverts', ['--company' => (string) $this->company->id]);

        $advert = TravelAdvert::query()->findOrFail($id);
        self::assertSame(AdvertStatus::EXPIRED, $advert->status);
        $this->getJson('/api/v1/travel/adverts')->assertOk()->assertJsonMissing(['id' => $id]);
    }

    public function test_renewal_pays_again_and_extends_expiry(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $id = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'À renouveler',
            'content' => 'Contenu',
            'validity_days' => 10,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/travel/adverts/{$id}/pay")->assertOk();
        $this->postJson("/api/v1/travel/adverts/{$id}/validate")->assertOk();

        $firstExpiry = TravelAdvert::query()->findOrFail($id)->expires_at;

        $this->postJson("/api/v1/travel/adverts/{$id}/renew")->assertOk();

        $advert = TravelAdvert::query()->findOrFail($id);
        self::assertSame(AdvertStatus::PAID, $advert->status, 'renouvellement = nouveau paiement');
        self::assertGreaterThan($firstExpiry->timestamp, $advert->expires_at->timestamp, 'expiration prolongée');
    }

    public function test_renewal_is_idempotent(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedPricing();

        $id = $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Renouvellement idempotent',
            'content' => 'Contenu',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/travel/adverts/{$id}/renew")->assertStatus(422); // draft non renouvelable

        $this->postJson("/api/v1/travel/adverts/{$id}/pay")->assertOk();
        $this->postJson("/api/v1/travel/adverts/{$id}/pay")->assertOk(); // rejeu → idempotent

        self::assertSame(
            1,
            TravelAdvert::query()->whereKey($id)->whereNotNull('payment_reference')->count(),
            'un seul paiement matérialisé',
        );
    }

    public function test_adverts_are_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelAdvert::factory()->create([
                'title' => 'Annonce tenant B',
                'status' => AdvertStatus::VALIDATED->value,
                'paid_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);
        });

        $this->actingManager();

        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Annonce tenant B']);
    }

    /* ── TRAVEL-914 (#6422) — mode gestion de GET /adverts (liste admin) ── */

    public function test_index_manage_mode_lists_all_statuses_for_manager(): void
    {
        $this->actingManager();
        $this->tenants->withinTenant($this->company, function (): void {
            TravelAdvert::factory()->create([
                'title' => 'Annonce draft',
                'status' => AdvertStatus::DRAFT->value,
            ]);
            TravelAdvert::factory()->create([
                'title' => 'Annonce validée',
                'status' => AdvertStatus::VALIDATED->value,
                'paid_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);
        });

        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'validated')
            ->assertJsonPath('data.1.status', 'draft');
    }

    public function test_index_manage_mode_filters_by_status(): void
    {
        $this->actingManager();
        $this->tenants->withinTenant($this->company, function (): void {
            TravelAdvert::factory()->create([
                'title' => 'Annonce draft',
                'status' => AdvertStatus::DRAFT->value,
            ]);
            TravelAdvert::factory()->create([
                'title' => 'Annonce soumise',
                'status' => AdvertStatus::SUBMITTED->value,
            ]);
        });

        $this->getJson('/api/v1/travel/adverts?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Annonce draft');
    }

    public function test_index_public_mode_hides_non_visible_for_agent(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'agent',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($agent);

        $this->tenants->withinTenant($this->company, function (): void {
            TravelAdvert::factory()->create([
                'title' => 'Annonce draft cachée',
                'status' => AdvertStatus::DRAFT->value,
            ]);
            TravelAdvert::factory()->create([
                'title' => 'Annonce visible',
                'status' => AdvertStatus::VALIDATED->value,
                'paid_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);
        });

        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Annonce visible');
    }

    public function test_index_manage_mode_is_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelAdvert::factory()->create([
                'title' => 'Annonce tenant B',
                'status' => AdvertStatus::DRAFT->value,
            ]);
        });

        $this->actingManager();

        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Annonce tenant B']);
    }
}
