<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7398 — `DELETE /api/v1/travel/adverts/{travelAdvert}`.
 *
 * La route appelait `TravelAdvertController@destroyAdvert`, méthode absente :
 * toute suppression d'annonce répondait 500 `Call to undefined method`. Le
 * contrat couvert ici : 204 sans corps, annonce réellement supprimée,
 * isolation tenant (404 cross-tenant, indiscernable d'un inexistant) et RBAC
 * (rôles opérationnels uniquement, `TravelAdvertPolicy::delete`).
 */
class TravelAdvertDestroyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

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

    /**
     * Crée une annonce du tenant.
     *
     * Les référentiels (type/position) sont insérés via le query builder :
     * les modèles `TravelAdvertType`/`TravelAdvertPosition` déclarent encore
     * la colonne `label` alors que le schéma porte `name` (désynchronisation
     * d'une fusion antérieure, hors périmètre de l'audit route → méthode
     * #7398). Seule l'annonce elle-même est créée via son modèle.
     */
    private function makeAdvert(Company $company, Employee $author): TravelAdvert
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $author): TravelAdvert {
            $timestamp = now();

            $typeId = DB::table('travel_advert_types')->insertGetId([
                'company_id' => $company->id,
                'code' => 'image_banner',
                'name' => 'Bannière',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $positionId = DB::table('travel_advert_positions')->insertGetId([
                'company_id' => $company->id,
                'code' => 'home_top',
                'name' => 'Accueil haut',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return TravelAdvert::query()->create([
                'company_id' => $company->id,
                'advert_type_id' => $typeId,
                'advert_position_id' => $positionId,
                'title' => 'Promo saison sèche',
                'content_redacted' => 'Offre valable sur les billets Douala-Yaoundé.',
                'price_minor' => 25000,
                'currency' => 'XAF',
                'status' => AdvertStatus::SUBMITTED->value,
                'validity_days' => 30,
                'created_by_user_id' => $author->id,
            ]);
        });
    }

    public function test_manager_deletes_own_advert(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $manager = $this->login($company);
        $advert = $this->makeAdvert($company, $manager);

        $this->deleteJson("/api/v1/travel/adverts/{$advert->id}")->assertStatus(204);

        self::assertNull(TravelAdvert::query()->find($advert->id));
    }

    public function test_delete_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $author = $this->login($company);
        $advert = $this->makeAdvert($company, $author);

        // Employé sans rôle opérationnel : policy `delete` → 404, l'annonce reste.
        $this->login($company, role: 'employee', managerRole: null);

        $this->deleteJson("/api/v1/travel/adverts/{$advert->id}")->assertStatus(404);

        self::assertNotNull(TravelAdvert::query()->find($advert->id));
    }

    public function test_cross_tenant_delete_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $authorA = $this->login($companyA);
        $advert = $this->makeAdvert($companyA, $authorA);

        $this->login($companyB);

        $this->deleteJson("/api/v1/travel/adverts/{$advert->id}")->assertStatus(404);

        self::assertNotNull(TravelAdvert::query()->find($advert->id));
    }

    public function test_delete_requires_authentication(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $author = $this->login($company);
        $advert = $this->makeAdvert($company, $author);

        app('auth')->forgetGuards();

        $this->deleteJson("/api/v1/travel/adverts/{$advert->id}")->assertStatus(401);

        self::assertNotNull(TravelAdvert::query()->find($advert->id));
    }
}
