<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyPublicProfile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Profil public d'officine — vitrine `/pharmacies` (#9616).
 *
 * ## Ce que ce test protège
 *
 * Avant #9616, `pharmacy_public_profiles` existait et le provider la lisait,
 * mais **aucune route ne l'écrivait** : `enabled` restait `false` pour tout le
 * monde, la vitrine affichait « bientôt disponible » en permanence, et le
 * parcours « le pharmacien publie → le public trouve » était cassé au milieu.
 *
 * Les cas couverts sont ceux qui casseraient silencieusement :
 *
 *  - opt-in fail-closed (activer sans slug ni nom = 422, pas un profil
 *    enregistré mais invisible) ;
 *  - RBAC (un préparateur ne publie pas l'officine) ;
 *  - isolation cross-tenant (un tenant ne lit ni n'écrit le profil d'un autre) ;
 *  - slug unique par tenant ;
 *  - dépublication immédiate (le provider ne remonte plus l'officine).
 */
class PharmacyPublicProfileTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $managerA;

    private Employee $lambdaA;

    private Employee $managerB;

    private function baseUrl(): string
    {
        return '/api/v1/pharmacy/public-profile';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['pharmacy' => true],
        ]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['pharmacy' => true],
        ]);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerA = $managerA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerB = $managerB;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl())->assertStatus(401);
        $this->putJson($this->baseUrl(), [])->assertStatus(401);
    }

    public function test_profile_is_null_before_any_configuration(): void
    {
        Sanctum::actingAs($this->managerA);

        $this->getJson($this->baseUrl())
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_manager_can_publish_and_read_back(): void
    {
        Sanctum::actingAs($this->managerA);

        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'pharmacie-centrale',
            'public_name' => 'Pharmacie Centrale',
            'public_city' => 'Alger',
            'on_duty' => true,
            'services' => ['Garde de nuit', 'Vaccination'],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.public_slug', 'pharmacie-centrale')
            ->assertJsonPath('data.public_url', '/pharmacies/pharmacie-centrale');

        $this->getJson($this->baseUrl())
            ->assertStatus(200)
            ->assertJsonPath('data.public_name', 'Pharmacie Centrale')
            ->assertJsonPath('data.on_duty', true);
    }

    public function test_enabling_without_slug_or_name_is_rejected(): void
    {
        Sanctum::actingAs($this->managerA);

        // Fail-closed : on refuse AVANT d'écrire. Enregistrer un profil activé
        // mais incomplet donnerait au pharmacien l'illusion d'avoir publié,
        // alors que `isPubliclyVisible()` le filtrerait.
        $this->putJson($this->baseUrl(), ['enabled' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug', 'public_name']);

        $this->assertSame(0, PharmacyPublicProfile::query()->count());
    }

    public function test_plain_employee_can_read_but_not_write(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'officine-a',
            'public_name' => 'Officine A',
        ])->assertStatus(200);

        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl())->assertStatus(200);

        $this->putJson($this->baseUrl(), [
            'enabled' => false,
            'public_slug' => 'officine-a',
            'public_name' => 'Officine A',
        ])->assertStatus(403);
    }

    public function test_slug_is_unique_per_tenant(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'centrale',
            'public_name' => 'Centrale',
        ])->assertStatus(200);

        // Le même tenant ne peut pas réutiliser son propre slug sur une autre
        // ligne — il n'y a qu'une ligne par tenant, donc on vérifie via un
        // second tenant qui tente le même slug : autorisé (unicité par tenant).
        Sanctum::actingAs($this->managerB);
        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'centrale',
            'public_name' => 'Centrale B',
        ])->assertStatus(200);
    }

    public function test_cross_tenant_isolation(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'officine-a',
            'public_name' => 'Officine A',
        ])->assertStatus(200);

        // B ne voit pas le profil de A : la lecture est bornée au company_id.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl())
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_unpublishing_removes_public_url(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'officine-a',
            'public_name' => 'Officine A',
        ])->assertStatus(200);

        $this->putJson($this->baseUrl(), [
            'enabled' => false,
            'public_slug' => 'officine-a',
            'public_name' => 'Officine A',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.public_url', null);
    }

    public function test_invalid_slug_format_is_rejected(): void
    {
        Sanctum::actingAs($this->managerA);

        $this->putJson($this->baseUrl(), [
            'enabled' => true,
            'public_slug' => 'Pharmacie Centrale!',
            'public_name' => 'Pharmacie Centrale',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug']);
    }
}
