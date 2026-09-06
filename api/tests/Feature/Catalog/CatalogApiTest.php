<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6881) — API privée de gestion du catalogue :
 * CRUD catégories + produits, publication, RBAC deny-by-default,
 * isolation tenant (404 cross-tenant) et gate feature flag b2b_catalog.
 */
class CatalogApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = [
            'company_id' => $company->id,
            'status' => 'active',
        ];

        if ($managerRole === 'employee') {
            $attributes['role'] = 'employee';
        } else {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create($attributes);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeCategory(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/catalog/categories', array_merge([
            'name' => 'Machines industrielles',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/catalog/products', array_merge([
            'name' => 'Machine CNC 3 axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    public function test_feature_flag_gate_returns_403_when_disabled(): void
    {
        /** @var Employee $manager */
        $manager = $this->employee($this->companyNoFlag, 'principal');
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/catalog/categories', ['name' => 'Machines'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_employee_cannot_manage_categories(): void
    {
        $this->actingAsUser($this->employeeA);

        $this->postJson('/api/v1/catalog/categories', ['name' => 'Machines'])
            ->assertStatus(403);

        $this->getJson('/api/v1/catalog/categories')
            ->assertStatus(200); // lecture autorisée aux membres du tenant
    }

    public function test_category_crud_and_auto_slug(): void
    {
        $category = $this->storeCategory($this->principalA, ['slug' => 'machines']);
        $this->assertSame('machines', $category['slug']);
        $this->assertSame($this->companyA->id, $category['company_id']);

        // Slug auto depuis le nom + suffixe en cas de collision (slug unique/tenant).
        $second = $this->storeCategory($this->principalA, ['name' => 'Machines industrielles']);
        $this->assertSame('machines-industrielles', $second['slug']);

        // Slug explicite déjà pris → 422 (validation unique par tenant).
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/categories', ['name' => 'Autre', 'slug' => 'machines'])
            ->assertStatus(422);

        // Même nom (slug auto) → collision gérée côté serveur : suffixe -2.
        $auto = $this->storeCategory($this->principalA, ['name' => 'Machines']);
        $this->assertSame('machines-2', $auto['slug']);

        // Mise à jour + réordonnancement (position).
        $this->actingAsUser($this->principalA);
        $this->putJson("/api/v1/catalog/categories/{$category['id']}", [
            'name' => 'Machines outillage',
            'position' => 3,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Machines outillage')
            ->assertJsonPath('data.position', 3);

        // Liste paginée.
        $this->getJson('/api/v1/catalog/categories?q=outillage&per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Suppression.
        $this->actingAsUser($this->principalA);
        $this->deleteJson("/api/v1/catalog/categories/{$category['id']}")
            ->assertStatus(200);
        $this->getJson("/api/v1/catalog/categories/{$category['id']}")
            ->assertStatus(404);
    }

    public function test_product_crud_status_filters_and_publish_flow(): void
    {
        $category = $this->storeCategory($this->principalA);
        $product = $this->storeProduct($this->principalA, [
            'category_id' => $category['id'],
            'slug' => 'machine-cnc',
        ]);

        $this->assertSame(CatalogProductStatus::Draft->value, $product['status']);
        $this->assertSame(1_250_000, $product['price_minor']);
        $this->assertSame('DZD', $product['currency']);

        // Filtre par statut + catégorie.
        $this->getJson('/api/v1/catalog/products?status=draft&category_id='.$category['id'])
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Publication puis dépublication (Actions dédiées).
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/catalog/products/{$product['id']}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->postJson("/api/v1/catalog/products/{$product['id']}/unpublish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        // Mise à jour.
        $this->putJson("/api/v1/catalog/products/{$product['id']}", [
            'name' => 'Machine CNC 5 axes',
            'price_minor' => 2_000_000,
            'currency' => 'DZD',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.price_minor', 2_000_000);

        // Suppression.
        $this->deleteJson("/api/v1/catalog/products/{$product['id']}")
            ->assertStatus(200);
    }

    public function test_product_validation_rejects_bad_payloads(): void
    {
        $this->actingAsUser($this->principalA);

        // Prix négatif → 422.
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Produit invalide',
            'price_minor' => -5,
            'currency' => 'DZD',
        ])->assertStatus(422);

        // Devise non ISO (minuscules) → 422.
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Produit invalide',
            'price_minor' => 100,
            'currency' => 'dzd',
        ])->assertStatus(422);

        // Catégorie d'un AUTRE tenant → 422 (Rule::exists scoped company_id).
        $otherCategory = $this->storeCategory($this->principalB);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Produit cross-tenant',
            'price_minor' => 100,
            'currency' => 'DZD',
            'category_id' => $otherCategory['id'],
        ])->assertStatus(422);
    }

    public function test_rbac_and_tenant_isolation_on_products(): void
    {
        $productA = $this->storeProduct($this->principalA);

        // Employé du tenant A : lecture OK, gestion 403.
        $this->actingAsUser($this->employeeA);
        $this->getJson("/api/v1/catalog/products/{$productA['id']}")
            ->assertStatus(200);
        $this->putJson("/api/v1/catalog/products/{$productA['id']}", [
            'name' => 'Pirate',
            'price_minor' => 1,
            'currency' => 'DZD',
        ])->assertStatus(403);

        // Principal du tenant B : ressource de A introuvable (404, fail-closed).
        $this->actingAsUser($this->principalB);
        $this->getJson("/api/v1/catalog/products/{$productA['id']}")
            ->assertStatus(404);
        $this->postJson("/api/v1/catalog/products/{$productA['id']}/publish")
            ->assertStatus(404);
        $this->deleteJson("/api/v1/catalog/products/{$productA['id']}")
            ->assertStatus(404);
    }
}
