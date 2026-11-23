<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Profil commercial & publication vitrine B2B (#9617).
 *
 * ## Ce que ce test protège
 *
 * `trade_company_profiles` portait `is_public` depuis #8802, et
 * `TradeDiscoverableProvider` le lisait. Mais `routes/modules/trade.php`
 * n'exposait **aucune** route de profil : `is_public` restait `false` et
 * **aucun moyen ne permettait de le passer à `true`**. `/entreprises` ne
 * pouvait donc jamais rien afficher.
 *
 * Les cas couverts sont ceux qui casseraient silencieusement :
 *
 *  - publication séparée de la description (enregistrer un champ commercial ne
 *    doit pas publier l'entreprise) ;
 *  - fail-closed (publier sans slug ni nom = 422) ;
 *  - slug unique GLOBAL (le resolver résout slug → tenant sans connaître
 *    l'appelant) ;
 *  - RBAC `admin` (un chargé d'affaires ne publie pas l'entreprise) ;
 *  - au moins une direction commerciale.
 */
class TradeCompanyProfileTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createCompany(bool $enableTrade = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableTrade) {
            $company->setFeature('trade', true);
            $company->save();
        }

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trade/profile')->assertStatus(401);
        $this->putJson('/api/v1/trade/profile', [])->assertStatus(401);
        $this->putJson('/api/v1/trade/profile/publication', [])->assertStatus(401);
    }

    public function test_profile_is_null_before_creation(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/trade/profile')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_profile_requires_at_least_one_direction(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Un profil sans direction n'est pas une entreprise commerciale : il
        // n'apparaîtrait dans aucun filtre de la vitrine.
        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => false,
            'export_enabled' => false,
            'domestic_scope' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['import_enabled']);
    }

    public function test_manager_can_create_profile(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => true,
            'export_enabled' => true,
            'hq_country' => 'CM',
            'default_currency' => 'XAF',
            'default_incoterm' => 'FOB',
            'product_categories' => ['Café', 'Cacao'],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.import_enabled', true)
            ->assertJsonPath('data.export_enabled', true)
            ->assertJsonPath('data.default_incoterm', 'FOB')
            ->assertJsonPath('data.is_public', false);
    }

    /**
     * #9755 — créer un profil SANS `hq_country` ne doit pas partir en 500.
     *
     * `trade_company_profiles.hq_country` est NOT NULL ; le contrôleur passait
     * `null` (le champ est facultatif dans le payload) et PostgreSQL refusait
     * l'insertion. Le repli doit venir du TENANT, pas d'un `'DZ'` codé en dur :
     * une société camerounaise dont le siège n'est pas saisi a son siège au
     * Cameroun, pas en Algérie.
     */
    public function test_profile_creation_without_hq_country_falls_back_to_tenant_country(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.hq_country', 'CM')
            ->assertJsonPath('data.default_currency', 'XAF');

        $stored = TradeCompanyProfile::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('CM', $stored->hq_country);
        $this->assertSame('XAF', $stored->default_currency);
    }

    /**
     * #9755 — un `hq_country` EXPLICITE reste prioritaire sur celui du tenant.
     *
     * Le repli ne doit jamais écraser une saisie : un tenant camerounais peut
     * avoir son siège opérationnel en Côte d'Ivoire.
     */
    public function test_explicit_hq_country_wins_over_tenant_country(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => true,
            'hq_country' => 'CI',
            'default_currency' => 'XOF',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.hq_country', 'CI')
            ->assertJsonPath('data.default_currency', 'XOF');
    }

    public function test_invalid_incoterm_is_rejected(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => true,
            'default_incoterm' => 'XYZ',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_incoterm']);
    }

    public function test_saving_profile_does_not_publish_it(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', [
            'import_enabled' => true,
            'public_slug' => 'tentative',
            'public_name' => 'Tentative',
        ])->assertStatus(200);

        // `public_slug` n'est pas un champ du formulaire de profil : il est
        // ignoré, et l'entreprise reste non publiée. C'est l'invariant qui
        // empêche une publication par inadvertance.
        $this->assertFalse(
            TradeCompanyProfile::query()->where('company_id', $company->id)->first()?->is_public,
        );
    }

    public function test_publishing_requires_slug_and_name(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);

        $this->putJson('/api/v1/trade/profile/publication', ['is_public' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug', 'public_name']);
    }

    public function test_publishing_exposes_public_url(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);

        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => 'cafe-du-cameroun',
            'public_name' => 'Café du Cameroun',
            'public_city' => 'Douala',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.public_url', '/entreprises/cafe-du-cameroun');
    }

    public function test_slug_is_unique_globally(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);
        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);
        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => 'partage',
            'public_name' => 'A',
        ])->assertStatus(200);

        // Second tenant, même slug : refusé. `TradeProfileResolver` résout un
        // slug vers un tenant sans connaître l'appelant — deux entreprises ne
        // peuvent donc pas partager une adresse publique.
        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);
        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);

        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => 'partage',
            'public_name' => 'B',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug']);
    }

    public function test_unpublishing_clears_public_url(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile', ['import_enabled' => true])->assertStatus(200);
        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => 'temporaire',
            'public_name' => 'Temporaire',
        ])->assertStatus(200);

        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => false,
            'public_slug' => 'temporaire',
            'public_name' => 'Temporaire',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.public_url', null);
    }

    public function test_publishing_without_profile_is_rejected(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->putJson('/api/v1/trade/profile/publication', [
            'is_public' => true,
            'public_slug' => 'orphelin',
            'public_name' => 'Orphelin',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_public']);
    }
}
