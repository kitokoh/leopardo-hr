<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-901 (#7746) — Gestion PRIVÉE du profil public d'une succursale et
 * publication en ligne des produits.
 *
 * Couvre : lecture du profil (défauts privés), mise à jour avec génération
 * de slug UNIQUE GLOBAL (suffixe en cas de collision cross-tenant), slug
 * explicite en collision → 422, branche d'un autre tenant → 404, écriture
 * réservée aux gérants (403), et bascule `is_published_online` d'un produit.
 */
class RestaurantBranchPublicProfileTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeTenant(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    private function actAsPrincipal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBranch(Company $company, array $attributes = []): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create($attributes)
        );
    }

    public function test_get_public_profile_returns_private_defaults(): void
    {
        $company = $this->makeTenant();
        $this->actAsPrincipal($company);
        $branch = $this->makeBranch($company);

        $this->getJson('/api/v1/restaurant/branches/'.$branch->id.'/public-profile')
            ->assertOk()
            ->assertJsonPath('data.id', $branch->id)
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.public_slug', null)
            ->assertJsonPath('data.establishment_type', null);
    }

    public function test_put_publishes_profile_and_generates_globally_unique_slug(): void
    {
        $companyA = $this->makeTenant();
        $this->actAsPrincipal($companyA);
        $branchA = $this->makeBranch($companyA, ['name' => 'Chez Leo']);

        $this->putJson('/api/v1/restaurant/branches/'.$branchA->id.'/public-profile', [
            'is_public' => true,
            'establishment_type' => 'brasserie',
            'cuisine_types' => ['africaine', 'grillades'],
            'public_description' => 'Brasserie de quartier.',
            'latitude' => 4.0511,
            'longitude' => 9.7679,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.public_slug', 'chez-leo')
            ->assertJsonPath('data.establishment_type', 'brasserie')
            ->assertJsonPath('data.cuisine_types.0', 'africaine');

        // Même nom sur un AUTRE tenant : le slug généré reçoit un suffixe
        // (unicité GLOBALE cross-tenant).
        $companyB = $this->makeTenant();
        $this->actAsPrincipal($companyB);
        $branchB = $this->makeBranch($companyB, ['name' => 'Chez Leo']);

        $this->putJson('/api/v1/restaurant/branches/'.$branchB->id.'/public-profile', [
            'is_public' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.public_slug', 'chez-leo-2');
    }

    public function test_put_rejects_explicit_slug_already_taken_on_another_tenant(): void
    {
        $companyA = $this->makeTenant();
        $this->actAsPrincipal($companyA);
        $branchA = $this->makeBranch($companyA, ['name' => 'Le Baobab']);

        $this->putJson('/api/v1/restaurant/branches/'.$branchA->id.'/public-profile', [
            'is_public' => true,
            'public_slug' => 'le-baobab',
        ])->assertOk();

        $companyB = $this->makeTenant();
        $this->actAsPrincipal($companyB);
        $branchB = $this->makeBranch($companyB);

        $this->putJson('/api/v1/restaurant/branches/'.$branchB->id.'/public-profile', [
            'is_public' => true,
            'public_slug' => 'le-baobab',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug']);

        // Format non slugifié refusé.
        $this->putJson('/api/v1/restaurant/branches/'.$branchB->id.'/public-profile', [
            'public_slug' => 'Pas Un Slug!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['public_slug']);
    }

    public function test_put_public_profile_on_other_tenant_branch_returns_404(): void
    {
        $companyA = $this->makeTenant();
        $otherBranch = $this->makeBranch($this->makeTenant());
        $this->actAsPrincipal($companyA);

        $this->putJson('/api/v1/restaurant/branches/'.$otherBranch->id.'/public-profile', [
            'is_public' => true,
        ])->assertStatus(404);

        $this->getJson('/api/v1/restaurant/branches/'.$otherBranch->id.'/public-profile')
            ->assertStatus(404);
    }

    public function test_put_public_profile_requires_manager_role(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($employee);

        $this->putJson('/api/v1/restaurant/branches/'.$branch->id.'/public-profile', [
            'is_public' => true,
        ])->assertStatus(403);
    }

    public function test_patch_product_publication_toggles_flag(): void
    {
        $company = $this->makeTenant();
        $this->actAsPrincipal($company);

        /** @var RestaurantProduct $product */
        $product = app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantProduct => RestaurantProduct::factory()->create(['branch_id' => null])
        );

        $this->patchJson('/api/v1/restaurant/products/'.$product->id.'/publication', [
            'is_published_online' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_published_online', true);

        $refreshed = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ?RestaurantProduct => RestaurantProduct::query()->find($product->id)
        );
        $this->assertNotNull($refreshed);
        $this->assertTrue((bool) $refreshed->is_published_online);

        // Payload manquant → validation stricte.
        $this->patchJson('/api/v1/restaurant/products/'.$product->id.'/publication', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_published_online']);
    }

    public function test_patch_product_publication_on_other_tenant_returns_404(): void
    {
        $companyA = $this->makeTenant();

        /** @var RestaurantProduct $otherProduct */
        $otherProduct = app(TenantManager::class)->withinTenant(
            $this->makeTenant(),
            fn (): RestaurantProduct => RestaurantProduct::factory()->create(['branch_id' => null])
        );

        $this->actAsPrincipal($companyA);

        $this->patchJson('/api/v1/restaurant/products/'.$otherProduct->id.'/publication', [
            'is_published_online' => true,
        ])->assertStatus(404);
    }
}
