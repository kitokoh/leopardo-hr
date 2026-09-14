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
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-907 (#6110) — Cycle de vie des annonces payantes : soumission avec
 * prix calculé serveur, paiement (référence `ADV-`, idempotent), validation
 * par `travel.manage`, rejet motivé (invisible), RBAC, isolation cross-tenant.
 *
 * #7420 : contrat rétabli — champs `content` / `price_minor` / statuts
 * `submitted → paid → validated` (les champs `body_redacted`, `total_minor`,
 * `character_count`, `payment_id` et les statuts `draft`/`published` venaient
 * d'un schéma legacy jamais implémenté, d'où les QueryException en cascade).
 * Le rejet passe par `POST /adverts/{id}/reject` (`reason`), pas par
 * `validate` avec `approved=false`.
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

    /**
     * @return array{0: TravelAdvertType, 1: TravelAdvertPosition}
     */
    private function makeCatalog(Company $company, int $image = 50000, int $perChar = 25): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $image, $perChar): array {
            $type = TravelAdvertType::query()->create([
                'company_id' => $company->id,
                'code' => 'image_banner',
                'label' => 'Bannière',
            ]);
            $position = TravelAdvertPosition::query()->create([
                'company_id' => $company->id,
                'code' => 'home_top',
                'label' => 'Accueil haut',
            ]);
            TravelAdvertPrice::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'price_per_image_minor' => $image,
                'price_per_character_minor' => $perChar,
                'currency' => 'XAF',
            ]);

            return [$type, $position];
        });
    }

    private function submit(Company $company, array $overrides = []): int
    {
        [$type, $position] = $this->makeCatalog($company);

        $response = $this->postJson('/api/v1/travel/adverts', array_merge([
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'Promo Douala',
            'content' => str_repeat('a', 20),
        ], $overrides));

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    public function test_submission_prices_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advertId = $this->submit($company);

        // 20 caractères × 25, sans image (0 × 50000) = 500 unités mineures.
        $this->getJson("/api/v1/travel/adverts/{$advertId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.price_minor', 500)
            ->assertJsonPath('data.visible', false);
    }

    public function test_submission_rejects_unknown_tenant_references_and_missing_tariff(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $foreignType = app(TenantManager::class)->withinTenant($other, fn () => TravelAdvertType::query()->create([
            'company_id' => $other->id,
            'code' => 'foreign',
            'label' => 'Étranger',
        ]));
        $position = app(TenantManager::class)->withinTenant($company, fn () => TravelAdvertPosition::query()->create([
            'company_id' => $company->id,
            'code' => 'home_top',
            'label' => 'Accueil',
        ]));

        // Type d'un autre tenant → 422.
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $foreignType->id,
            'advert_position_id' => $position->id,
            'title' => 'X',
            'content' => 'Y',
        ])->assertStatus(422);

        // Type du tenant mais AUCUN tarif configuré → 422 (prix serveur).
        $localType = app(TenantManager::class)->withinTenant($company, fn () => TravelAdvertType::query()->create([
            'company_id' => $company->id,
            'code' => 'no_price',
            'label' => 'Sans tarif',
        ]));
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $localType->id,
            'advert_position_id' => $position->id,
            'title' => 'X',
            'content' => 'Y',
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
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate")->assertStatus(422);

        // Paiement : statut paid + référence de paiement matérialisée.
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $paidReference = TravelAdvert::query()->findOrFail($advertId)->payment_reference;

        // Paiement idempotent : rejeu → même référence, pas de second effet.
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])->assertOk();
        self::assertSame($paidReference, TravelAdvert::query()->findOrFail($advertId)->payment_reference);

        // Validation par un rôle hors `travel.manage` (manager de département) → 403.
        $this->login($company, role: 'manager', managerRole: 'dept');
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate")->assertStatus(403);

        // Validation par principal → validée, donc visible, avec expiration.
        $this->login($company, role: 'manager', managerRole: 'principal');
        $this->postJson("/api/v1/travel/adverts/{$advertId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.expires_at', fn ($v) => $v !== null);

        $advert = TravelAdvert::query()->findOrFail($advertId);
        self::assertNotNull($advert->validated_at);
        self::assertSame(AdvertStatus::VALIDATED, $advert->status);
        self::assertTrue($advert->isVisible());
        self::assertGreaterThan(now()->addDays(29), $advert->expires_at);
    }

    public function test_rejected_advert_stays_invisible(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advertId = $this->submit($company);
        $this->postJson("/api/v1/travel/adverts/{$advertId}/pay", ['provider' => 'cash'])->assertOk();

        $this->postJson("/api/v1/travel/adverts/{$advertId}/reject", ['reason' => 'contenu inapproprié'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $advert = TravelAdvert::query()->findOrFail($advertId);
        self::assertSame('contenu inapproprié', $advert->rejected_reason);
        self::assertFalse($advert->isVisible());

        // La vitrine publique (acteur non gestion) ne contient pas l'annonce rejetée.
        $this->login($company, role: 'employee', managerRole: null);
        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonMissing(['id' => $advertId]);
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
        [$type, $position] = $this->makeCatalog($company);

        $this->login($company, role: 'employee', managerRole: null);

        // Requête valide (les référentiels existent) mais rôle insuffisant : la
        // policy de création tranche en 403.
        $this->postJson('/api/v1/travel/adverts', [
            'advert_type_id' => $type->id,
            'advert_position_id' => $position->id,
            'title' => 'X',
            'content' => 'Y',
        ])->assertStatus(403);
    }
}
