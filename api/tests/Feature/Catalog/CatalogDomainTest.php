<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Domain\Policies\CatalogCategoryPolicy;
use App\Modules\Catalog\Domain\Policies\CatalogProductPolicy;
use App\Modules\Catalog\Domain\Support\CatalogFeatures;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6880) — socle domaine : migrations tenant idempotentes,
 * unicité du slug par tenant, scope tenant, feature flag `b2b_catalog` et
 * RBAC deny-by-default (gestion réservée principal/rh du tenant).
 */
class CatalogDomainTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employeeA = $employeeA;
    }

    private function category(Company $company, string $slug = 'machines'): CatalogCategory
    {
        /** @var CatalogCategory $category */
        $category = CatalogCategory::query()->create([
            'company_id' => $company->id,
            'name' => 'Machines',
            'slug' => $slug,
        ]);

        return $category;
    }

    private function product(Company $company, CatalogCategory $category, string $slug = 'machine-cnc'): CatalogProduct
    {
        /** @var CatalogProduct $product */
        $product = CatalogProduct::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Machine CNC',
            'slug' => $slug,
            'description' => 'Découpeuse CNC 3 axes.',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
            'status' => CatalogProductStatus::Draft,
        ]);

        return $product;
    }

    public function test_catalog_tables_exist_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('catalog_categories'));
        $this->assertTrue(Schema::hasTable('catalog_products'));

        $schema = DB::selectOne(
            'SELECT table_schema FROM information_schema.tables WHERE table_name = ? LIMIT 1',
            ['catalog_products']
        );
        $this->assertSame('shared_tenants', $schema->table_schema ?? null, 'catalog_products absente du schéma tenant');
    }

    public function test_category_and_product_are_created_with_company_id_not_null(): void
    {
        $category = $this->category($this->companyA);
        $product = $this->product($this->companyA, $category);

        $this->assertSame($this->companyA->id, $category->company_id);
        $this->assertSame($this->companyA->id, $product->company_id);
        $this->assertSame(CatalogProductStatus::Draft, $product->status);
        $this->assertSame(1_250_000, $product->price_minor);

        $nullCount = DB::table('catalog_products')->whereNull('company_id')->count();
        $this->assertSame(0, $nullCount);
    }

    public function test_slug_is_unique_per_tenant_only(): void
    {
        $this->category($this->companyA, 'machines');

        // Même slug chez un AUTRE tenant → autorisé (isolation).
        $this->category($this->companyB, 'machines');

        // Même slug chez le MÊME tenant → contrainte unique (savepoint #4978).
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->category($this->companyA, 'machines');
        });
    }

    public function test_product_slug_is_unique_per_tenant_only(): void
    {
        $categoryA = $this->category($this->companyA);
        $categoryB = $this->category($this->companyB);

        $this->product($this->companyA, $categoryA, 'machine-cnc');
        $this->product($this->companyB, $categoryB, 'machine-cnc');

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($categoryA): void {
            $this->product($this->companyA, $categoryA, 'machine-cnc');
        });
    }

    public function test_tenant_scope_scopes_to_current_company(): void
    {
        $categoryA = $this->category($this->companyA);
        $this->product($this->companyA, $categoryA);

        $categoryB = $this->category($this->companyB);
        $this->product($this->companyB, $categoryB);

        // Contexte tenant A → seuls les produits de A sont visibles.
        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $this->assertSame(1, CatalogProduct::query()->count());

        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');
    }

    public function test_b2b_catalog_feature_flag_defaults_disabled_and_can_be_enabled(): void
    {
        $this->assertSame('b2b_catalog', CatalogFeatures::B2B_CATALOG);
        $this->assertFalse($this->companyA->hasFeature(CatalogFeatures::B2B_CATALOG));

        $this->companyA->setFeature(CatalogFeatures::B2B_CATALOG, true);
        $this->companyA->save();

        $fresh = Company::query()->findOrFail($this->companyA->id);
        $this->assertTrue($fresh->hasFeature(CatalogFeatures::B2B_CATALOG));
    }

    public function test_category_management_reserved_to_principal_of_same_tenant(): void
    {
        $category = $this->category($this->companyA);
        $policy = new CatalogCategoryPolicy;

        $this->assertTrue($policy->create($this->principalA));
        $this->assertTrue($policy->update($this->principalA, $category));
        $this->assertTrue($policy->view($this->principalA, $category));

        // Employé lambda : lecture autorisée, gestion refusée (deny-by-default).
        $this->assertTrue($policy->viewAny($this->employeeA));
        $this->assertFalse($policy->create($this->employeeA));
        $this->assertFalse($policy->update($this->employeeA, $category));
        $this->assertFalse($policy->delete($this->employeeA, $category));
    }

    public function test_product_policy_denies_cross_tenant_management(): void
    {
        $categoryA = $this->category($this->companyA);
        $productA = $this->product($this->companyA, $categoryA);
        $categoryB = $this->category($this->companyB);
        $productB = $this->product($this->companyB, $categoryB);
        $policy = new CatalogProductPolicy;

        // Le principal de A gère les produits de A…
        $this->assertTrue($policy->update($this->principalA, $productA));
        $this->assertTrue($policy->publish($this->principalA, $productA));

        // … mais pas ceux de B (isolation tenant, 404/403 côté API plus tard).
        $this->assertFalse($policy->update($this->principalA, $productB));
        $this->assertFalse($policy->delete($this->principalA, $productB));

        // Lecture cross-tenant refusée également (scope company_id).
        $this->assertFalse($policy->view($this->principalA, $productB));
    }
}
