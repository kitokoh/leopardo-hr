<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale PharmaManager — PHARMA-001 (#7798) + PHARMA-002 (#7799).
 *
 * Couvre : manifest résolu par le catalogue (allowlist fail-closed), auth
 * 401, solution inactive 403 PHARMACY_SOLUTION_INACTIVE, RBAC (écriture
 * manager, lecture employé), CRUD produit, filtres, unicité code-barres
 * par tenant (le même code-barres reste autorisé dans deux tenants),
 * archivage, isolation cross-tenant 404.
 */
class PharmacyProductTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $managerA;

    private Employee $lambdaA;

    private Employee $managerB;

    private function baseUrl(): string
    {
        return '/api/v1/pharmacy';
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
        $this->companyA = $companyA;

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

    public function test_solution_catalogue_resolves_pharmacy_manifest(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue($catalogue->has('pharmacy'));

        $manifest = $catalogue->resolve('pharmacy');
        $this->assertSame('pharmacy', $manifest->code());
        $this->assertSame(['rh'], $manifest->requiredModules());
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/products')->assertStatus(401);
        $this->postJson($this->baseUrl().'/products', [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403_fail_closed(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl().'/products')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_can_read_but_not_write(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->postJson($this->baseUrl().'/products', ['name' => 'Doliprane 1000'])->assertStatus(201);

        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/products')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->postJson($this->baseUrl().'/products', ['name' => 'Interdit'])->assertStatus(403);
    }

    public function test_manager_can_crud_and_filter_products(): void
    {
        Sanctum::actingAs($this->managerA);
        $url = $this->baseUrl();

        $productId = $this->postJson($url.'/products', [
            'name' => 'Amoxicilline 500mg gélules',
            'dci' => 'amoxicilline',
            'form' => 'gélule',
            'dosage' => '500 mg',
            'barcode' => '6131234567890',
            'internal_code' => 'AMX-500',
            'category' => 'medicament',
            'prescription_required' => true,
            'purchase_price' => 250.50,
            'sale_price' => 380.00,
            'tax_rate' => 9.00,
            'min_stock_level' => 20,
        ])->assertStatus(201)
            ->assertJsonPath('data.prescription_required', true)
            ->assertJsonPath('data.sale_price', '380.00')
            ->json('data.id');

        $this->postJson($url.'/products', ['name' => 'Crème hydratante', 'category' => 'parapharmacie'])
            ->assertStatus(201);

        // Recherche par DCI + filtre catégorie.
        $this->getJson($url.'/products?search=amoxicilline')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
        $this->getJson($url.'/products?category=parapharmacie')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
        $this->getJson($url.'/products?prescription_required=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Mise à jour.
        $this->putJson($url.'/products/'.$productId, ['sale_price' => 395.00])
            ->assertStatus(200)
            ->assertJsonPath('data.sale_price', '395.00');

        // Archivage (jamais de suppression : traçabilité réglementaire).
        $this->patchJson($url.'/products/'.$productId.'/archive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        $this->getJson($url.'/products?status=archived')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_barcode_unique_per_tenant_but_allowed_across_tenants(): void
    {
        Sanctum::actingAs($this->managerA);
        $this->postJson($this->baseUrl().'/products', [
            'name' => 'Produit A',
            'barcode' => '6130000000001',
        ])->assertStatus(201);

        // Doublon dans le même tenant → 422.
        $this->postJson($this->baseUrl().'/products', [
            'name' => 'Produit A bis',
            'barcode' => '6130000000001',
        ])->assertStatus(422)->assertJsonValidationErrors(['barcode']);

        // Même code-barres dans un AUTRE tenant → autorisé.
        Sanctum::actingAs($this->managerB);
        $this->postJson($this->baseUrl().'/products', [
            'name' => 'Produit B',
            'barcode' => '6130000000001',
        ])->assertStatus(201);
    }

    public function test_cross_tenant_product_is_invisible_404(): void
    {
        Sanctum::actingAs($this->managerA);
        $productId = $this->postJson($this->baseUrl().'/products', ['name' => 'Secret A'])
            ->assertStatus(201)
            ->json('data.id');

        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/products/'.$productId)->assertStatus(404);
        $this->putJson($this->baseUrl().'/products/'.$productId, ['name' => 'Vol'])->assertStatus(404);

        // Le tenant B ne voit pas les produits du tenant A dans la liste.
        $this->getJson($this->baseUrl().'/products')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        // La donnée du tenant A est intacte.
        $this->assertSame(1, PharmacyProduct::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('name', 'Secret A')
            ->count());
    }
}
