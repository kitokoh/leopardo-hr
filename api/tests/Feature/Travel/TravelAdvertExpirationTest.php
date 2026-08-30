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
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-908 (#6111) — Expiration & renouvellement des annonces payantes :
 * annonce expirée invisible, job d'expiration idempotent, archivage des
 * vieilles expirées, renouvellement payé qui requalifie (republiée).
 */
class TravelAdvertExpirationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function login(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
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

    private function makePublishedAdvert(Company $company, ?\Illuminate\Support\Carbon $expiresAt = null): TravelAdvert
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($expiresAt): TravelAdvert {
            $type = TravelAdvertType::query()->create([
                'company_id' => $company->id,
                'code' => 'image_banner',
                'name' => 'Bannière',
            ]);
            $position = TravelAdvertPosition::query()->create([
                'company_id' => $company->id,
                'code' => 'home_top',
                'name' => 'Accueil',
            ]);
            TravelAdvertPrice::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'price_image_minor' => 1000,
                'price_character_minor' => 10,
                'currency' => 'XAF',
            ]);

            return TravelAdvert::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $type->id,
                'advert_position_id' => $position->id,
                'title' => 'Annonce test',
                'body_redacted' => 'Contenu',
                'character_count' => 8,
                'price_image_minor' => 1000,
                'price_character_minor' => 10,
                'total_minor' => 1080,
                'currency' => 'XAF',
                'status' => TravelAdvert::STATUS_PUBLISHED,
                'published_at' => now()->subDays(5),
                'expires_at' => $expiresAt ?? now()->addDays(25),
                'validated_by_user_id' => 1,
                'validated_at' => now()->subDays(5),
            ]);
        });
    }

    public function test_expired_advert_is_invisible_and_job_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $this->makePublishedAdvert($company, now()->subDay());

        // Encore visible dans la liste published ? Non : le job expire d'abord.
        Artisan::call('travel:expire-adverts');
        $this->assertSame(1, TravelAdvert::query()->where('status', TravelAdvert::STATUS_EXPIRED)->count());
        $this->assertSame(0, TravelAdvert::query()->where('status', TravelAdvert::STATUS_PUBLISHED)->count());

        // L'annonce expirée n'apparaît plus dans la liste published.
        $this->getJson('/api/v1/travel/adverts?status=published')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/travel/adverts?status=expired')->assertJsonCount(1, 'data');

        // Rejeu du job : idempotent, aucune régression de statut.
        Artisan::call('travel:expire-adverts');
        $this->assertSame(1, TravelAdvert::query()->where('status', TravelAdvert::STATUS_EXPIRED)->count());
    }

    public function test_old_expired_adverts_are_archived(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $this->makePublishedAdvert($company, now()->subDays(100));

        Artisan::call('travel:expire-adverts');

        $this->assertSame(1, TravelAdvert::query()->where('status', TravelAdvert::STATUS_ARCHIVED)->count());
    }

    public function test_renewal_republishes_with_new_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $advert = $this->makePublishedAdvert($company, now()->subDay());
        Artisan::call('travel:expire-adverts');
        $this->assertSame(TravelAdvert::STATUS_EXPIRED, $advert->refresh()->status);

        // Renouvellement par un principal : nouveau paiement + republiée.
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew", ['provider' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.expires_at', fn ($v) => $v !== null);

        $renewals = TravelPayment::query()->where('advert_id', $advert->id)->count();
        $this->assertSame(1, $renewals, 'une seule ligne de paiement de renouvellement');

        // Rejeu du renew : idempotent (clé unique) — toujours une ligne.
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew", ['provider' => 'cash'])->assertOk();
        $this->assertSame(1, TravelPayment::query()->where('advert_id', $advert->id)->count());

        // L'annonce est de nouveau visible.
        $this->getJson('/api/v1/travel/adverts?status=published')->assertJsonCount(1, 'data');
    }

    public function test_renewal_requires_manage_and_valid_state(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $advert = $this->makePublishedAdvert($company, now()->subDay());
        Artisan::call('travel:expire-adverts');

        // Un agent ne peut pas renouveler.
        $this->login($company, managerRole: 'agent');
        $this->postJson("/api/v1/travel/adverts/{$advert->id}/renew", ['provider' => 'cash'])
            ->assertStatus(403);

        // Une annonce en draft ne peut pas être renouvelée.
        $this->login($company, managerRole: 'principal');
        $draft = $this->makePublishedAdvert($company);
        $draft->forceFill(['status' => TravelAdvert::STATUS_DRAFT, 'expires_at' => null])->save();
        $this->postJson("/api/v1/travel/adverts/{$draft->id}/renew", ['provider' => 'cash'])
            ->assertStatus(422);
    }
}
