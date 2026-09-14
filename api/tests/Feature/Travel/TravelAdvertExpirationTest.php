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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-908 (#6111) — Expiration & renouvellement des annonces payantes :
 * annonce expirée invisible, commande d'expiration idempotente et
 * tenant-scopée (`--company`), archivage des vieilles expirées, renouvellement
 * payé qui repasse par la validation.
 *
 * #7420 : contrat rétabli — statuts `validated/expired/archived` (et non
 * `published`), champs `content_redacted` / `price_minor`, commande canonique
 * `travel:expire-adverts` (celle du module, seule enregistrée : elle seule
 * connaît `--company` et le scoping tenant).
 */
class TravelAdvertExpirationTest extends TestCase
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

    private function makeValidatedAdvert(Company $company, ?Carbon $expiresAt = null): TravelAdvert
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $expiresAt): TravelAdvert {
            // Code unique par appel : deux annonces dans un même test ne doivent
            // pas entrer en collision sur l'unicité (company_id, code).
            $suffix = substr(uniqid('', true), -6);

            $type = TravelAdvertType::query()->create([
                'company_id' => $company->id,
                'code' => 'image_banner_'.$suffix,
                'label' => 'Bannière',
            ]);
            $position = TravelAdvertPosition::query()->create([
                'company_id' => $company->id,
                'code' => 'home_top_'.$suffix,
                'label' => 'Accueil',
            ]);
            TravelAdvertPrice::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'price_per_image_minor' => 1000,
                'price_per_character_minor' => 10,
                'currency' => 'XAF',
            ]);

            return TravelAdvert::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'title' => 'Annonce test',
                'content_redacted' => 'Contenu',
                'price_minor' => 1080,
                'currency' => 'XAF',
                'status' => AdvertStatus::VALIDATED,
                'payment_reference' => 'ADV-TEST0001',
                'paid_at' => now()->subDays(5),
                'validated_by_user_id' => 1,
                'validated_at' => now()->subDays(5),
                'validity_days' => 30,
                'starts_at' => now()->subDays(5),
                'expires_at' => $expiresAt ?? now()->addDays(25),
            ]);
        });
    }

    public function test_expired_advert_is_invisible_and_job_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $this->makeValidatedAdvert($company, now()->subDay());

        Artisan::call('travel:expire-adverts', ['--company' => (string) $company->id]);

        self::assertSame(1, TravelAdvert::query()->where('status', AdvertStatus::EXPIRED->value)->count());
        self::assertSame(0, TravelAdvert::query()->where('status', AdvertStatus::VALIDATED->value)->count());

        // Mode gestion : la liste filtrée par statut est cohérente.
        $this->getJson('/api/v1/travel/adverts?status=validated')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/travel/adverts?status=expired')->assertOk()->assertJsonCount(1, 'data');

        // Rejeu du job : idempotent, aucune régression de statut.
        Artisan::call('travel:expire-adverts', ['--company' => (string) $company->id]);
        self::assertSame(1, TravelAdvert::query()->where('status', AdvertStatus::EXPIRED->value)->count());
    }

    public function test_old_expired_adverts_are_archived(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $this->makeValidatedAdvert($company, now()->subDays(100));

        Artisan::call('travel:expire-adverts', ['--company' => (string) $company->id]);

        self::assertSame(1, TravelAdvert::query()->where('status', AdvertStatus::ARCHIVED->value)->count());
        self::assertSame(0, TravelAdvert::query()->where('status', AdvertStatus::EXPIRED->value)->count());
    }

    public function test_expiry_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $advertA = $this->makeValidatedAdvert($companyA, now()->subDay());
        $advertB = $this->makeValidatedAdvert($companyB, now()->subDay());

        Artisan::call('travel:expire-adverts', ['--company' => (string) $companyA->id]);

        self::assertSame(AdvertStatus::EXPIRED, $advertA->refresh()->status);
        self::assertSame(AdvertStatus::VALIDATED, $advertB->refresh()->status);
    }

    public function test_renewal_republishes_after_validation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advert = $this->makeValidatedAdvert($company, now()->subDay());
        Artisan::call('travel:expire-adverts', ['--company' => (string) $company->id]);
        self::assertSame(AdvertStatus::EXPIRED, $advert->refresh()->status);

        // Renouvellement par un principal : nouveau paiement (référence) et
        // expiration prolongée ; l'annonce repasse par la validation.
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $renewed = $advert->refresh();
        self::assertNotSame('ADV-TEST0001', $renewed->payment_reference);
        self::assertGreaterThan(now()->addDays(29), $renewed->expires_at);

        // Rejeu immédiat : idempotent (même référence, pas de second effet).
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew")->assertOk();
        self::assertSame($renewed->payment_reference, $advert->refresh()->payment_reference);

        // Validation → de nouveau visible sur la liste publique.
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated');

        $this->login($company, role: 'employee', managerRole: null);
        $this->getJson('/api/v1/travel/adverts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $advert->id);
    }

    public function test_renewal_requires_manage_and_valid_state(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $advert = $this->makeValidatedAdvert($company, now()->subDay());
        Artisan::call('travel:expire-adverts', ['--company' => (string) $company->id]);

        // Un rôle hors `travel.manage` (manager de département) ne peut pas
        // renouveler — 404 (convention du module : on ne révèle pas l'existence
        // de la ressource à un acteur non autorisé, cf. `pay`/`renew`).
        $this->login($company, role: 'manager', managerRole: 'dept');
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew")->assertStatus(404);

        // Une annonce en brouillon ne peut pas être renouvelée.
        $this->login($company, role: 'manager', managerRole: 'principal');
        $draft = $this->makeValidatedAdvert($company);
        $draft->forceFill(['status' => AdvertStatus::DRAFT, 'expires_at' => null])->save();
        $this->postJson("/api/v1/travel/adverts/{$draft->id}/renew")->assertStatus(422);
    }
}
