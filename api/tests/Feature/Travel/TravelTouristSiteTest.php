<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-909 (#6112) — Annuaire des sites touristiques : CRUD tenant-scoped,
 * recherche par ville (annuaire consultable), géo bornée, isolation
 * cross-tenant.
 */
class TravelTouristSiteTest extends TestCase
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

    private function makeCity(Company $company, string $name = 'Yaoundé'): TravelCity
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $name): TravelCity {
            TravelCountry::query()->create([
                'company_id' => $company->id,
                'iso2' => 'CM',
                // `iso3` est NOT NULL sans defaut en base : l'INSERT echouait
                // donc sur une violation de contrainte, ce qui avortait la
                // transaction externe et faisait echouer les TROIS tests du
                // fichier avec un `SQLSTATE[25P02] In failed sql transaction`
                // trompeur, a des lignes sans rapport (refs #8396).
                'iso3' => 'CMR',
                'name' => 'Cameroun',
            ]);

            return TravelCity::query()->create([
                'company_id' => $company->id,
                'country_iso2' => 'CM',
                'name' => $name,
            ]);
        });
    }

    public function test_site_crud_and_search_by_city(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $city = $this->makeCity($company);

        $created = $this->postJson('/api/v1/travel/tourist-sites', [
            'name' => 'Chutes de la Lobé',
            'description_redacted' => 'Cascades',
            'city_id' => $city->id,
            'latitude' => 2.8765,
            'longitude' => 9.9000,
        ])->assertStatus(201);

        // `POST` ne renvoie que l'identifiant (contrat documente dans
        // `api/openapi.yaml` : « Site cree (id) »). On lit donc le statut par
        // la surface qui l'expose, la liste — la valeur attendue est `active`,
        // seul defaut possible (`TravelRecordStatus` = active|disabled).
        $siteId = (int) $created->json('data.id');

        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$city->id)
            ->assertOk()
            ->assertJsonPath('data.0.status', 'active');

        // Recherche par ville.
        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$city->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Recherche par nom (insensible à la casse).
        $this->getJson('/api/v1/travel/tourist-sites?search=lobé')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/travel/tourist-sites?search=inexistant')->assertJsonCount(0, 'data');

        // Mise à jour.
        // Seule transition reelle : `active` -> `disabled`. `archived` n'est
        // pas une valeur de l'enum et faisait echouer le PUT en 422 (ou en
        // ValueError selon l'ordre de validation) — refs #8396.
        // Le PUT ne renvoie que l'identifiant (contrat OpenAPI) : la valeur
        // effective se verifie par la lecture.
        $this->putJson("/api/v1/travel/tourist-sites/{$siteId}", ['status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.id', $siteId);

        $this->getJson("/api/v1/travel/tourist-sites/{$siteId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            // La mise a jour partielle ne doit PAS avoir efface les champs
            // omis — c'est ce que faisait `forceFill` sur toutes les cles.
            ->assertJsonPath('data.city_id', $city->id)
            ->assertJsonPath('data.name', 'Chutes de la Lobé');

        $this->deleteJson("/api/v1/travel/tourist-sites/{$siteId}")->assertStatus(204);
    }

    public function test_site_geo_and_city_are_validated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        // Latitude hors bornes → 422.
        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X', 'latitude' => 91])
            ->assertStatus(422);

        // Ville d'un autre tenant → 422.
        $foreignCity = $this->makeCity($other, 'Douala');
        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X', 'city_id' => $foreignCity->id])
            ->assertStatus(422);

        // Filtre par ville d'un autre tenant : la liste reste VIDE et le
        // statut est 200. C'est le comportement fail-closed correct — un 422
        // dirait « cette ville existe mais pas chez vous », donc divulguerait
        // l'existence d'une ressource d'un autre tenant. Le test attendait 422
        // (refs #8396 : assertion inversee, pas un defaut de code).
        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$foreignCity->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_site_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->login($companyA);
        $site = app(TenantManager::class)->withinTenant($companyA, fn () => TravelTouristSite::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Mont Cameroun',
            'status' => 'active',
        ]));

        $this->login($companyB);
        $this->getJson("/api/v1/travel/tourist-sites/{$site->id}")->assertStatus(404);
        $this->getJson('/api/v1/travel/tourist-sites')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company, role: 'employee', managerRole: null);

        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X'])->assertStatus(403);
    }
}
