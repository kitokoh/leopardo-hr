<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910).
 *
 * `GET /api/v1/establishments` : liste unifi\u00e9e LECTURE SEULE des
 * \u00e9tablissements de toutes les verticales activ\u00e9es du tenant.
 *
 * Ce que ces tests prouvent, et que des assertions sur l'impl\u00e9mentation ne
 * prouveraient pas :
 *  - l'agr\u00e9gation traverse VRAIMENT deux modules distincts (le registre par
 *    module fonctionne, il ne rend pas qu'une verticale) ;
 *  - un module D\u00c9SACTIV\u00c9 ne d\u00e9clare rien, m\u00eame quand ses tables contiennent
 *    encore des lignes \u2014 c'est le crit\u00e8re d'acceptation de l'issue, et le
 *    cas o\u00f9 une impl\u00e9mentation naive \u00e9chouerait ;
 *  - l'isolation tenant tient : les \u00e9tablissements d'une autre soci\u00e9t\u00e9
 *    n'apparaissent jamais, ni par lecture directe ni par \u00e9num\u00e9ration.
 */
class EstablishmentsHubTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, bool>  $features
     * @param  array<string, mixed>  $attributes
     */
    private function companyWithFeatures(array $features, array $attributes = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'country' => 'CM',
            'currency' => 'XAF',
        ], $attributes));

        foreach ($features as $key => $enabled) {
            $company->setFeature($key, $enabled);
        }

        $company->save();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function branch(Company $company, array $attributes = []): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create($attributes)
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function station(Company $company, array $attributes = []): FuelStation
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): FuelStation => FuelStation::query()->create(array_merge([
                'company_id' => $company->id,
                'code' => 'ST-'.uniqid(),
                'name' => 'Station',
                'status' => 'active',
            ], $attributes))
        );
    }

    private function actorFor(Company $company): Employee
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): Employee => Employee::factory()->create(['company_id' => $company->id])
        );
    }

    /**
     * Le hub est le SEUL endroit o\u00f9 deux modules se rencontrent sans se
     * conna\u00eetre : si le registre par module \u00e9tait cass\u00e9 (switch central
     * oubli\u00e9, tag absent), une seule verticale appara\u00eetrait.
     */
    public function test_hub_aggregates_restaurant_and_fuel_establishments(): void
    {
        $company = $this->companyWithFeatures(['restaurantmanager' => true, 'fuel_station' => true]);
        $this->branch($company, ['name' => 'Succursale Bonapriso', 'code' => 'BON']);
        $this->station($company, ['name' => 'Station Akwa', 'code' => 'AKW']);

        $response = $this->actingAs($this->actorFor($company))
            ->getJson('/api/v1/establishments')
            ->assertOk();

        $verticals = array_column($response->json('data'), 'vertical');

        $this->assertContains('restaurant', $verticals);
        $this->assertContains('fuel', $verticals);

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Succursale Bonapriso', $names);
        $this->assertContains('Station Akwa', $names);

        // Le meta d\u00e9clare les verticales activ\u00e9es : c'est ce que le frontend
        // consomme pour afficher (ou masquer) l'entr\u00e9e de navigation.
        $metaVerticals = $response->json('meta.verticals');
        $this->assertContains('restaurant', $metaVerticals);
        $this->assertContains('fuel', $metaVerticals);
        $this->assertSame(2, $response->json('meta.total'));
    }

    /**
     * Crit\u00e8re d'acceptation explicite : \u00ab un module d\u00e9sactiv\u00e9 n'appara\u00eet
     * pas \u00bb. Les lignes existent toujours en base \u2014 c'est justement ce qui
     * rend le test significatif : une impl\u00e9mentation qui se contenterait de
     * lire les tables afficherait la station malgr\u00e9 le module coup\u00e9.
     */
    public function test_disabled_vertical_is_absent_even_though_its_rows_exist(): void
    {
        $company = $this->companyWithFeatures(['restaurantmanager' => true, 'fuel_station' => false]);
        $this->branch($company, ['name' => 'Succursale Visible']);
        $this->station($company, ['name' => 'Station Cachee']);

        $response = $this->actingAs($this->actorFor($company))
            ->getJson('/api/v1/establishments')
            ->assertOk();

        $verticals = array_column($response->json('data'), 'vertical');
        $names = array_column($response->json('data'), 'name');

        $this->assertSame(['restaurant'], array_values(array_unique($verticals)));
        $this->assertContains('Succursale Visible', $names);
        $this->assertNotContains('Station Cachee', $names, 'module fuel d\u00e9sactiv\u00e9 \u2192 invisible');
        $this->assertNotContains('fuel', $response->json('meta.verticals'));
        // Le compteur ne doit pas non plus trahir la verticale coup\u00e9e.
        $this->assertSame(1, $response->json('meta.total'));
    }

    /**
     * Isolation tenant : la soci\u00e9t\u00e9 B a ses propres \u00e9tablissements, la
     * soci\u00e9t\u00e9 A ne doit en voir AUCUN. Deux tenants avec la M\u00caME feature
     * active isolent la variable : si la requ\u00eate oubliait `company_id`, les
     * deux jeux appara\u00eetraient et le test \u00e9chouerait.
     */
    public function test_hub_only_exposes_the_acting_tenant_establishments(): void
    {
        $tenantA = $this->companyWithFeatures(['restaurantmanager' => true], ['name' => 'Tenant A']);
        $tenantB = $this->companyWithFeatures(['restaurantmanager' => true], ['name' => 'Tenant B']);

        $this->branch($tenantA, ['name' => 'Branche A']);
        $this->branch($tenantB, ['name' => 'Branche B']);

        $responseA = $this->actingAs($this->actorFor($tenantA))
            ->getJson('/api/v1/establishments')
            ->assertOk();

        $namesA = array_column($responseA->json('data'), 'name');
        $this->assertContains('Branche A', $namesA);
        $this->assertNotContains('Branche B', $namesA, 'fuite cross-tenant interdite');
        $this->assertSame(1, $responseA->json('meta.total'));
    }

    /**
     * Aucune verticale activ\u00e9e \u2192 le hub r\u00e9pond quand m\u00eame 200 avec une
     * liste vide : c'est ce qui permet \u00e0 la page de distinguer \u00ab rien \u00e0
     * afficher \u00bb d'une erreur, et au menu de masquer son entr\u00e9e.
     */
    public function test_hub_returns_empty_list_when_no_vertical_is_enabled(): void
    {
        $company = $this->companyWithFeatures(['restaurantmanager' => false, 'fuel_station' => false]);
        $this->branch($company, ['name' => 'Invisible']);

        $response = $this->actingAs($this->actorFor($company))
            ->getJson('/api/v1/establishments')
            ->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
        $this->assertSame([], $response->json('meta.verticals'));
    }

    /**
     * Chaque item expose EXACTEMENT le contrat sp\u00e9cifi\u00e9 par l'issue, et
     * rien de plus : ni `company_id`, ni identifiant interne de verticale
     * au-del\u00e0 de `id`, ni m\u00e9tadonn\u00e9e de base. Les liens doivent pointer vers
     * des pages de la verticale avec l'\u00e9tablissement pr\u00e9-s\u00e9lectionn\u00e9.
     */
    public function test_item_contract_is_stable_and_links_target_the_vertical_screens(): void
    {
        $company = $this->companyWithFeatures(['restaurantmanager' => true, 'fuel_station' => true]);
        $branch = $this->branch($company, ['name' => 'Succursale Contrat']);
        $station = $this->station($company, ['name' => 'Station Contrat']);

        $response = $this->actingAs($this->actorFor($company))
            ->getJson('/api/v1/establishments')
            ->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertSame(
                ['vertical', 'id', 'name', 'code', 'status', 'href_stats', 'href_team', 'staff_count'],
                array_keys($item),
                'le contrat d\'item est fig\u00e9 par l\'issue #7910'
            );
            $this->assertArrayNotHasKey('company_id', $item);
        }

        $restaurant = null;
        $fuel = null;

        /** @var array<string, mixed> $item */
        foreach ($response->json('data') as $item) {
            if (($item['vertical'] ?? null) === 'restaurant') {
                $restaurant = $item;
            }

            if (($item['vertical'] ?? null) === 'fuel') {
                $fuel = $item;
            }
        }

        $this->assertNotNull($restaurant);
        $this->assertNotNull($fuel);

        $this->assertSame('/restaurant/reports?branch='.$branch->id, $restaurant['href_stats'] ?? null);
        $this->assertSame('/restaurant/team?branch='.$branch->id, $restaurant['href_team'] ?? null);
        $this->assertSame('/fuel/sales?station='.$station->id, $fuel['href_stats'] ?? null);
        $this->assertSame('/fuel/team?station='.$station->id, $fuel['href_team'] ?? null);
    }
}
