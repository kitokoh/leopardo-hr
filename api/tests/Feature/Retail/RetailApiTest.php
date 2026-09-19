<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7672) — API privée de gestion du module vendeur générique :
 * CRUD catégories + produits, SKU unique par tenant, publication, RBAC
 * deny-by-default, isolation tenant (404 cross-tenant) et gate feature
 * flag retail.
 */
class RetailApiTest extends TestCase
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
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyA->setFeature('retail', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('retail', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
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

        return $this->postJson('/api/v1/retail/categories', array_merge([
            'name' => 'Boissons fraiches',
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

        return $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    public function test_feature_flag_gate_returns_403_when_disabled(): void
    {
        /** @var Employee $manager */
        $manager = $this->employee($this->companyNoFlag, 'principal');
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/retail/categories', ['name' => 'Boissons'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->getJson('/api/v1/retail/products')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_employee_cannot_manage_but_can_read(): void
    {
        $this->actingAsUser($this->employeeA);

        $this->postJson('/api/v1/retail/categories', ['name' => 'Boissons'])
            ->assertStatus(403);

        $this->postJson('/api/v1/retail/products', [
            'name' => 'Pirate',
            'sku' => 'SKU-PIRATE',
            'price_minor' => 1,
        ])->assertStatus(403);

        $this->getJson('/api/v1/retail/categories')
            ->assertStatus(200); // lecture autorisée aux membres du tenant

        $this->getJson('/api/v1/retail/products')
            ->assertStatus(200);
    }

    public function test_category_crud_and_auto_slug(): void
    {
        $category = $this->storeCategory($this->principalA, ['slug' => 'boissons']);
        $this->assertSame('boissons', $category['slug']);
        $this->assertSame($this->companyA->id, $category['company_id']);

        // Slug auto depuis le nom + suffixe en cas de collision (slug unique/tenant).
        $second = $this->storeCategory($this->principalA, ['name' => 'Boissons fraiches']);
        $this->assertSame('boissons-fraiches', $second['slug']);

        // Slug explicite déjà pris → 422 (validation unique par tenant).
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/categories', ['name' => 'Autre', 'slug' => 'boissons'])
            ->assertStatus(422);

        // Même nom (slug auto) → collision gérée côté serveur : suffixe -2.
        $auto = $this->storeCategory($this->principalA, ['name' => 'Boissons']);
        $this->assertSame('boissons-2', $auto['slug']);

        // Mise à jour + réordonnancement (position).
        $this->actingAsUser($this->principalA);
        $this->putJson("/api/v1/retail/categories/{$category['id']}", [
            'name' => 'Boissons chaudes',
            'position' => 3,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Boissons chaudes')
            ->assertJsonPath('data.position', 3);

        // Liste paginée.
        $this->getJson('/api/v1/retail/categories?q=chaudes&per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Suppression.
        $this->actingAsUser($this->principalA);
        $this->deleteJson("/api/v1/retail/categories/{$category['id']}")
            ->assertStatus(200);
        $this->getJson("/api/v1/retail/categories/{$category['id']}")
            ->assertStatus(404);
    }

    public function test_product_crud_sku_and_publish_flow(): void
    {
        $category = $this->storeCategory($this->principalA);
        $product = $this->storeProduct($this->principalA, [
            'category_id' => $category['id'],
            'slug' => 'jus-bissap',
            'barcode' => '6181234567890',
            'cost_minor' => 800,
        ]);

        $this->assertSame(RetailProductStatus::Draft->value, $product['status']);
        $this->assertSame(1_500, $product['price_minor']);
        $this->assertSame(800, $product['cost_minor']);
        $this->assertSame('XOF', $product['currency']);
        $this->assertSame('SKU-BISSAP-50', $product['sku']);

        // Filtre par statut + catégorie.
        $this->getJson('/api/v1/retail/products?status=draft&category_id='.$category['id'])
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Publication puis dépublication.
        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->postJson("/api/v1/retail/products/{$product['id']}/unpublish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        // Mise à jour.
        $this->putJson("/api/v1/retail/products/{$product['id']}", [
            'name' => 'Jus de bissap 1L',
            'price_minor' => 2_500,
            'currency' => 'XOF',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.price_minor', 2_500)
            ->assertJsonPath('data.sku', 'SKU-BISSAP-50');

        // Suppression.
        $this->deleteJson("/api/v1/retail/products/{$product['id']}")
            ->assertStatus(200);
    }

    public function test_sku_unique_per_tenant(): void
    {
        $this->storeProduct($this->principalA, ['sku' => 'SKU-001']);

        // Même SKU dans le même tenant → 422 (validation unique par tenant).
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Doublon',
            'sku' => 'SKU-001',
            'price_minor' => 100,
        ])->assertStatus(422);

        // Même SKU dans un AUTRE tenant → 201 (unicité scoped company_id).
        $this->storeProduct($this->principalB, ['sku' => 'SKU-001', 'currency' => 'MAD']);
    }

    public function test_product_validation_rejects_bad_payloads(): void
    {
        $this->actingAsUser($this->principalA);

        // SKU manquant → 422.
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit invalide',
            'price_minor' => 100,
        ])->assertStatus(422);

        // Prix négatif → 422.
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit invalide',
            'sku' => 'SKU-NEG',
            'price_minor' => -5,
        ])->assertStatus(422);

        // Devise non ISO (minuscules) → 422.
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit invalide',
            'sku' => 'SKU-CUR',
            'price_minor' => 100,
            'currency' => 'xof',
        ])->assertStatus(422);

        // Statut hors enum → 422.
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit invalide',
            'sku' => 'SKU-STA',
            'price_minor' => 100,
            'status' => 'hidden',
        ])->assertStatus(422);

        // Catégorie d'un AUTRE tenant → 422 (Rule::exists scoped company_id).
        $otherCategory = $this->storeCategory($this->principalB);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit cross-tenant',
            'sku' => 'SKU-CROSS',
            'price_minor' => 100,
            'category_id' => $otherCategory['id'],
        ])->assertStatus(422);
    }

    public function test_rbac_and_tenant_isolation_on_products(): void
    {
        $productA = $this->storeProduct($this->principalA);

        // Employé du tenant A : lecture OK, gestion 403.
        $this->actingAsUser($this->employeeA);
        $this->getJson("/api/v1/retail/products/{$productA['id']}")
            ->assertStatus(200);
        $this->putJson("/api/v1/retail/products/{$productA['id']}", [
            'name' => 'Pirate',
            'price_minor' => 1,
        ])->assertStatus(403);
        $this->postJson("/api/v1/retail/products/{$productA['id']}/publish")
            ->assertStatus(403);

        // Principal du tenant B : ressource de A introuvable (404, fail-closed).
        $this->actingAsUser($this->principalB);
        $this->getJson("/api/v1/retail/products/{$productA['id']}")
            ->assertStatus(404);
        $this->postJson("/api/v1/retail/products/{$productA['id']}/publish")
            ->assertStatus(404);
        $this->deleteJson("/api/v1/retail/products/{$productA['id']}")
            ->assertStatus(404);
    }
}
